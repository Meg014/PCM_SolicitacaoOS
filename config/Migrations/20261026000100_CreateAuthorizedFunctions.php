<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateAuthorizedFunctions extends BaseMigration
{
    public function change(): void
    {
        $this->table('authorized_functions')
            ->addColumn('code', 'string', ['limit' => 100])
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('active', 'boolean', ['default' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->addIndex(['code'], ['unique' => true])
            ->create();
    }
}
