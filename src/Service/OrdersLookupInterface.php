<?php
declare(strict_types=1);
namespace App\Service;

interface OrdersLookupInterface
{
    public function batch(array $numbers): array;
    public function find(string $number, string $branch, string $asset): ?array;
}
