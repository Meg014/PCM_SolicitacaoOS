<?php
declare(strict_types=1);

namespace App\Test\Fixture;

class MaintenanceTypesFixture extends \Cake\TestSuite\Fixture\TestFixture
{
    public function init(): void
    {
        $this->records = [];
        foreach (\App\Domain\Enum\MaintenanceType::cases() as $i => $type) {
            $this->records[] = ['id' => $i + 1, 'code' => $type->value, 'name' => $type->label(),
                'active' => true, 'created_at' => '2026-11-10 12:00:00', 'updated_at' => '2026-11-10 12:00:00'];
        }
        parent::init();
    }
}
