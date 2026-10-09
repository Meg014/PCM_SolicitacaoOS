<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateServiceRequests extends BaseMigration
{
    public function change(): void
    {
        $this->table('service_requests')
            ->addColumn('number', 'string', ['limit' => 40])
            ->addColumn('user_id', 'integer')
            ->addColumn('sector_id', 'integer')
            ->addColumn('asset_code', 'string', ['limit' => 100])
            ->addColumn('asset_branch', 'string', ['limit' => 100, 'default' => ''])
            ->addColumn('asset_description', 'string', ['limit' => 500])
            ->addColumn('maintenance_type', 'string', ['limit' => 30])
            ->addColumn('description', 'text')
            ->addColumn('status', 'string', ['limit' => 30])
            ->addColumn('totvs_os_number', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('sector_id', 'sectors', 'id', ['delete' => 'RESTRICT'])
            ->addIndex(['number'], ['unique' => true])
            ->addIndex(['user_id', 'created_at'])
            ->addIndex(['status', 'created_at'])
            ->create();
    }
}
