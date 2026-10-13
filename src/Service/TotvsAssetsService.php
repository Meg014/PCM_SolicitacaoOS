<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Datasource\ConnectionManager;

class TotvsAssetsService implements AssetsLookupInterface
{
    public function search(string $term): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2 || mb_strlen($term) > 60) {
            return [];
        }
        $term = '%' . strtr($term, ['~' => '~~', '%' => '~%', '_' => '~_', '[' => '~[']) . '%';

        return $this->read(TotvsAssetQueries::SEARCH, ['description_term' => $term]);
    }

    public function find(string $code, string $branch): ?array
    {
        if ($code === '' || strlen($code) > 100 || strlen($branch) > 100) {
            return null;
        }
        $rows = $this->read(TotvsAssetQueries::FIND, ['code' => $code, 'branch' => $branch]);

        return count($rows) === 1 ? $rows[0] : null;
    }

    protected function read(string $sql, array $params): array
    {
        try {
            return ConnectionManager::get('protheus')->execute($sql, $params)->fetchAll('assoc');
        } catch (\Throwable $e) {
            // Sem encadear exceções do driver: podem conter detalhes de infraestrutura.
            throw new AssetsUnavailableException();
        }
    }
}
