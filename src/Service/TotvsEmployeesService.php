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
            ->orderBy(['code' => 'ASC'])->toArray();
    }

    protected function read(string $sql, array $params): array
    {
        return ConnectionManager::get('protheus')->execute($sql, $params)->fetchAll('assoc');
    }

    private function lookup(array $params, bool $find): array
    {
        try {
            $rows = $this->read(TotvsEmployeeQueries::lookup($find), $params);
            // Local descriptions decorate labels only; they never determine eligibility.
            $functions = [];
            try {
                foreach ($this->functions() as $code => $name) {
                    $functions[AuthorizedFunctionCode::forTotvs((string)$code)] = $name;
                }
            } catch (\Throwable) {
                // Employee lookup remains available without the optional local catalog.
            }
            $results = [];
            foreach ($rows as $row) {
                $code = rtrim((string)$row['function_code']);
                $registration = rtrim((string)$row['registration']);
                $name = rtrim((string)$row['name']);
                $situation = trim((string)$row['situation']);
                if (!in_array($situation, TotvsEmployeeQueries::ALLOWED_SITUATIONS, true) || trim($registration) === '' || trim($name) === '') {
                    continue;
                }
                $functionName = $functions[$code] ?? '';
                $label = $registration . ' - ' . $name . ($functionName !== '' ? ' - ' . $functionName : '');
                $statusLabel = match ($situation) { 'F' => 'FÉRIAS', 'A' => 'AFASTADO', default => '' };
                $results[] = ['registration' => $registration, 'name' => $name, 'function_code' => $code,
                    'function_name' => $functionName, 'situation' => $situation,
                    'label' => $label . ($statusLabel !== '' ? ' - ' . $statusLabel : '')];
            }
            return $results;
        } catch (\Throwable $e) {
            throw new EmployeesUnavailableException();
        }
    }
}
