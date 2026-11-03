<?php
declare(strict_types=1);

namespace App\Test\Support;

use App\Service\EmployeesLookupInterface;

final class FakeEmployees implements EmployeesLookupInterface
{
    public function search(string $term): array
    {
        return strlen(trim($term)) < 2 ? [] : [$this->find($term)];
    }

    public function find(string $registration): ?array
    {
        if (trim($registration) === '') { return null; }
        return ['registration' => $registration, 'name' => $registration, 'function_code' => '00005',
            'function_name' => 'Mecânico', 'label' => $registration . ' - ' . $registration . ' - Mecânico'];
    }
}
