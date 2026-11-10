<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateMaintenanceTypes extends BaseMigration
{
    public function up(): void
    {
        $this->table('maintenance_types')
            ->addColumn('code', 'string', ['limit' => 30])
            ->addColumn('name', 'string', ['limit' => 150])
            ->addColumn('active', 'boolean', ['default' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['code'], ['unique' => true])
            ->addIndex(['name'], ['unique' => true])
            ->addIndex(['active', 'name'])->create();
        $rows = [];
        foreach (\App\Domain\Enum\MaintenanceType::cases() as $type) {
            $rows[] = ['code' => $type->value, 'name' => $type->label()];
        }
        $this->table('maintenance_types')->insert($rows)->saveData();
    }

    public function down(): void
    {
        throw new RuntimeException('Preserve os tipos de manutenção e seu histórico; desative os tipos sem uso.');
    }
}
