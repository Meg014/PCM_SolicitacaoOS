<?php
declare(strict_types=1);
namespace App\Service;

use Cake\Datasource\ConnectionManager;

class TotvsOrdersService implements OrdersLookupInterface
{
    public function batch(array $numbers): array
    {
        $numbers = array_values(array_unique($numbers));
        if (!$numbers) { return []; }
        $params = [];
        foreach ($numbers as $i => $number) {
            if (!is_string($number) || $number === '' || strlen($number) > 100) { throw new \InvalidArgumentException('Número de O.S. inválido.'); }
            $params['number_' . $i] = $number;
        }
        $rows = $this->read(TotvsOrderQueries::batch(count($numbers)), $params);
        if (count($rows) > 100) { throw new OrdersUnavailableException(); }
        return $rows;
    }

    public function find(string $number, string $branch, string $asset): ?array
    {
        if ($number === '' || $asset === '' || max(strlen($number), strlen($branch), strlen($asset)) > 100) { return null; }
        $rows = $this->read(TotvsOrderQueries::DETAIL, ['number' => $number, 'branch' => $branch, 'exact_branch' => $branch, 'asset' => $asset]);
        if (count($rows) !== 1) { return null; }
        $row = $rows[0];
        if ($row['number'] !== $number || $row['asset_code'] !== $asset || ($branch !== '' && $row['branch'] !== $branch) ||
            (int)($row['identity_count'] ?? 0) !== 1 || (int)($row['asset_matches'] ?? 0) > 1 || (int)($row['service_matches'] ?? 0) > 1) { return null; }
        return $row;
    }

    protected function read(string $sql, array $params): array
    {
        try {
            $rows = ConnectionManager::get('protheus')->execute($sql, $params)->fetchAll('assoc');
            return array_map(fn($row) => array_map(fn($value) => is_string($value) ? rtrim($value, ' ') : $value, $row), $rows);
        } catch (\Throwable $e) { throw new OrdersUnavailableException(); }
    }
}
