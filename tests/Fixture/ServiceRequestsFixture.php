<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use App\Domain\Enum\MaintenanceType;
use App\Domain\Enum\RequestStatus;
use Cake\TestSuite\Fixture\TestFixture;

class ServiceRequestsFixture extends TestFixture
{
    public function init(): void
    {
        foreach ([1 => 3, 2 => 2] as $id => $userId) {
            $this->records[] = [
                'id' => $id, 'number' => sprintf('SOL-2026-%06d', $id), 'user_id' => $userId, 'sector_id' => 1,
                'asset_code' => 'BEM01', 'asset_branch' => '01', 'asset_description' => 'Bomba de água',
                'maintenance_type' => MaintenanceType::CORRETIVA->value, 'description' => 'Pedido de teste ' . $id,
                'status' => RequestStatus::AGUARDANDO_PCM->value, 'totvs_os_number' => null, 'flow' => 'SOMENTE_ABERTURA',
                'created_at' => '2026-10-07 12:00:00', 'updated_at' => '2026-10-07 12:00:00',
            ];
        }
        parent::init();
    }
}
