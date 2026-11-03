<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;

class TotvsEmployeesService implements EmployeesLookupInterface
{
    public function search(string $term): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2 || mb_strlen($term) > 60) {
            return [];
        }
        $pattern = '%' . strtr($term, ['[' => '[[]', '%' => '[%]', '_' => '[_]']) . '%';
        return $this->lookup(['name_term' => $pattern, 'registration_term' => $pattern], false);
    }

    public function find(string $registration): ?array
    {
        if ($registration === '' || strlen($registration) > 100) {
            return null;
        }
        $rows = $this->lookup(['registration' => $registration], true);
        // Ambiguous registration must never silently select a different employee.
        return count($rows) === 1 ? $rows[0] : null;
    }

    protected function functions(): array
    {
        return TableRegistry::getTableLocator()->get('AuthorizedFunctions')->find('list', keyField: 'code', valueField: 'name')
            ->where(['active' => true])->orderBy(['code' => 'ASC'])->toArray();
    }

    protected function read(string $sql, array $params): array
    {
        return ConnectionManager::get('protheus')->execute($sql, $params)->fetchAll('assoc');
    }

    private function lookup(array $params, bool $find): array
    {
        try {
            $functions = [];
            foreach ($this->functions() as $code => $name) {
                $code = AuthorizedFunctionCode::forTotvs((string)$code);
                if (isset($functions[$code]) && $functions[$code] !== $name) {
                    throw new \UnexpectedValueException('Funções equivalentes com descrições conflitantes.');
                }
                $functions[$code] = $name;
            }
            if (!$functions) {
                return [];
            }
            foreach (array_keys($functions) as $index => $code) {
                $params['function_' . $index] = (string)$code;
            }
            $rows = $this->read(TotvsEmployeeQueries::lookup(count($functions), $find), $params);
            $results = [];
            foreach ($rows as $row) {
                $code = rtrim((string)$row['function_code']);
                $registration = rtrim((string)$row['registration']);
                $name = rtrim((string)$row['name']);
                if (!isset($functions[$code]) || !in_array(trim((string)$row['situation']), TotvsEmployeeQueries::ALLOWED_SITUATIONS, true) || trim($registration) === '' || trim($name) === '') {
                    continue;
                }
                $results[] = ['registration' => $registration, 'name' => $name, 'function_code' => $code,
                    'function_name' => $functions[$code], 'label' => $registration . ' - ' . $name . ' - ' . $functions[$code]];
            }
            return $results;
        } catch (\Throwable $e) {
            throw new EmployeesUnavailableException();
        }
    }
}
