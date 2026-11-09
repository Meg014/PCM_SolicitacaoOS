<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateServicesAndAddServiceSnapshots extends BaseMigration
{
    public function change(): void
    {
        $this->table('services')
            ->addColumn('name', 'string', ['limit' => 150])
            ->addColumn('active', 'boolean', ['default' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['name'], ['unique' => true])
            ->addIndex(['active', 'name'])
            ->create();

        foreach (['service_requests', 'request_items'] as $tableName) {
            $this->table($tableName)
                ->addColumn('service_id', 'integer', ['null' => true])
                ->addColumn('service_name', 'string', ['limit' => 150, 'null' => true])
                ->addForeignKey('service_id', 'services', 'id', ['delete' => 'RESTRICT'])
                ->addIndex(['service_id'])
                ->update();
        }
    }
}
