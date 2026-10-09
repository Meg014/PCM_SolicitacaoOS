<?php
declare(strict_types=1);

namespace App\Service;

use App\Domain\Enum\RequestStatus;
use Cake\ORM\TableRegistry;

final class ActiveAssetRequests
{
    public static function find(string $code, string $branch): array
    {
        return TableRegistry::getTableLocator()->get('ServiceRequests')->find()->contain(['Users', 'Sectors'])
            ->where(['ServiceRequests.asset_code' => $code, 'ServiceRequests.asset_branch' => $branch,
                'ServiceRequests.status IN' => RequestStatus::activeValues()])
            ->orderBy(['ServiceRequests.created_at' => 'ASC', 'ServiceRequests.id' => 'ASC'])->all()->toList();
    }
}
