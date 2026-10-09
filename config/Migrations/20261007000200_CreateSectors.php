<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateSectors extends BaseMigration
{
    public function change(): void
    {
        $this->table('sectors')
            ->addColumn('code', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('name', 'string', ['limit' => 150])
            ->addColumn('active', 'boolean', ['default' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['name'], ['unique' => true])
            ->addIndex(['active', 'name'])
            ->create();
    }
}
