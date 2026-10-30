<?php
declare(strict_types=1);

namespace App\Service;

use App\Domain\Enum\UserRole;
use Cake\ORM\TableRegistry;

final class AuthorizedFunctionsImport
{
    /** Read the supplied two-column XLSX without converting identifiers to numbers. */
    public function read(string $path): array
    {
        $zip = new \ZipArchive();
        if (!is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx'
            || $zip->open($path) !== true) {
            throw new \InvalidArgumentException('Informe uma planilha XLSX válida.');
        }
        try {
            $xml = static function (string $entry) use ($zip): \SimpleXMLElement {
                $stat = $zip->statName($entry);
                if (!$stat || $stat['size'] > 5 * 1024 * 1024) {
                    throw new \InvalidArgumentException('Planilha ausente ou maior que o limite de leitura.');
                }
                $data = $zip->getFromName($entry);
                if ($data === false || stripos($data, '<!DOCTYPE') !== false || stripos($data, '<!ENTITY') !== false) {
                    throw new \InvalidArgumentException('Estrutura XML inválida.');
                }
                $document = @simplexml_load_string($data, \SimpleXMLElement::class, LIBXML_NONET);
                if ($document === false) {
                    throw new \InvalidArgumentException('Estrutura XML inválida.');
                }
                return $document;
            };
            $strings = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                foreach ($xml('xl/sharedStrings.xml')->si as $entry) {
                    $strings[] = implode('', array_map('strval', $entry->xpath('.//*[local-name()="t"]')));
                }
            }
            $rows = [];
            foreach ($xml('xl/worksheets/sheet1.xml')->sheetData->row as $row) {
                $values = [];
                foreach ($row->c as $cell) {
                    $column = preg_replace('/[0-9]+/', '', (string)$cell['r']);
                    if (!in_array($column, ['A', 'B'], true)) { continue; }
                    if (isset($cell->f)) {
                        throw new \InvalidArgumentException('Use valores nas colunas de função e descrição, sem fórmulas.');
                    }
                    $type = (string)$cell['t'];
                    $value = $type === 's' ? ($strings[(int)$cell->v] ?? '')
                        : ($type === 'inlineStr' ? implode('', array_map('strval', $cell->xpath('.//*[local-name()="t"]'))) : (string)$cell->v);
                    $values[$column] = trim($value);
                }
                if (!$values || !array_filter($values, static fn($v) => $v !== '')) { continue; }
                $rows[] = $values;
            }
            $header = array_shift($rows);
            if (($header['A'] ?? '') !== 'Funcao' || ($header['B'] ?? '') !== 'Descricao') {
                throw new \InvalidArgumentException('A primeira aba deve conter Funcao e Descricao nas colunas A e B.');
            }
            $result = [];
            foreach ($rows as $row) {
                $code = $row['A'] ?? '';
                $name = $row['B'] ?? '';
                if ($code === '' || $name === '' || mb_strlen($code) > 100 || mb_strlen($name) > 255) {
                    throw new \InvalidArgumentException('Cada função precisa de código e descrição válidos.');
                }
                // Prefix prevents PHP from casting numeric keys and losing identifier formatting.
                $key = 'code:' . $code;
                if (isset($result[$key]) && $result[$key]['name'] !== $name) {
                    throw new \InvalidArgumentException('Há descrições diferentes para o mesmo código na planilha.');
                }
                $result[$key] = ['code' => $code, 'name' => $name];
            }
            if (!$result) { throw new \InvalidArgumentException('A planilha não contém funções.'); }
            return array_values($result);
        } finally {
            $zip->close();
        }
    }

    public function import(string $path, int $actorId, bool $replace = false): array
    {
        $rows = $this->read($path);
        $tables = TableRegistry::getTableLocator();
        $table = $tables->get('AuthorizedFunctions');
        return $table->getConnection()->transactional(function () use ($tables, $table, $rows, $actorId, $replace): array {
            $actor = $tables->get('Users')->find()->where(['id' => $actorId, 'active' => true, 'role' => UserRole::ADMIN_PCM->value])->first();
            if (!$actor) { throw new \InvalidArgumentException('Informe o ID de um ADMIN_PCM ativo responsável pela importação.'); }
            $table->getConnection()->execute('LOCK TABLE authorized_functions IN SHARE ROW EXCLUSIVE MODE');
            $counts = ['created' => 0, 'updated' => 0, 'deactivated' => 0, 'unchanged' => 0];
            $registration = new RegistrationService();
            foreach ($rows as $row) {
                $entity = $table->find()->where(['code' => $row['code']])->first();
                $before = $entity?->toArray();
                if ($entity && $entity->name === $row['name'] && $entity->active) {
                    $counts['unchanged']++;
                    continue;
                }
                $entity = $entity ? $table->patchEntity($entity, $row + ['active' => true]) : $table->newEntity($row + ['active' => true]);
                $registration->save($table, $entity, $before, $actorId, 'AUTHORIZED_FUNCTION_IMPORTED');
                $counts[$before === null ? 'created' : 'updated']++;
            }
            if ($replace) {
                foreach ($table->find()->where(['active' => true, 'code NOT IN' => array_column($rows, 'code')]) as $entity) {
                    $before = $entity->toArray();
                    $entity->set('active', false);
                    $registration->save($table, $entity, $before, $actorId, 'AUTHORIZED_FUNCTION_DISABLED');
                    $counts['deactivated']++;
                }
            }
            return $counts;
        });
    }
}
