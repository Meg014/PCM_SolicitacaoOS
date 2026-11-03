<?php
declare(strict_types=1);

namespace App\Service;

interface EmployeesLookupInterface
{
    public function search(string $term): array;

    public function find(string $registration): ?array;
}
