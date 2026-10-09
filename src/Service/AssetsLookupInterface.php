<?php
declare(strict_types=1);

namespace App\Service;

interface AssetsLookupInterface
{
    public function search(string $term): array;

    public function find(string $code, string $branch): ?array;
}
