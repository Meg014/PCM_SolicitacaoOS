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
        if ($term === '') {
            return [];
        }
        $pattern = '%' . strtr($term, ['[' => '[[]', '%' => '[%]', '_' => '[_]']) . '%';
        return $this->lookup(['name_term' => $pattern, 'registration_term' => $pattern,
            'exact_registration' => $term, 'exact_name' => $term], false);
    }

    public function find(string $registration, ?string $selectionKey = null): ?array
    {
        if ($registration === '' || strlen($registration) > 100) {
            return null;
        }
        $rows = $this->lookup(['registration' => $registration], true);
        if ($selectionKey === null || $selectionKey === '') {
            return count($rows) === 1 ? $rows[0] : null;
        }
        $matches = array_values(array_filter($rows, fn($row) => $row['selection_key'] === $selectionKey));
        return count($matches) === 1 ? $matches[0] : null;
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
                $functionName = $functions[$code] ?? '';
                $label = $registration . ' - ' . $name . ($functionName !== '' ? ' - ' . $functionName : '');
                $results[] = ['registration' => $registration, 'name' => $name, 'function_code' => $code,
                    'function_name' => $functionName, 'label' => $label,
                    'selection_key' => hash('sha256', $registration . "\0" . $name . "\0" . $code)];
            }
            return $results;
        } catch (\Throwable $e) {
            throw new EmployeesUnavailableException();
        }
    }
}
