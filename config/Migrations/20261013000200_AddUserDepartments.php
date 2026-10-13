<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddUserDepartments extends BaseMigration
{
    public function change(): void
    {
        $this->table('user_departments')
            ->addColumn('name', 'string', ['limit' => 150])
            ->addColumn('active', 'boolean', ['default' => true])
            ->addColumn('created_at', 'datetime', ['null' => true])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addIndex(['name'], ['unique' => true])->create();
        // Sem atribuição presumida para funcionários existentes.
        $this->table('users')->addColumn('department_id', 'integer', ['null' => true])
            ->addIndex(['department_id'])
            ->addForeignKey('department_id', 'user_departments', 'id', ['delete' => 'RESTRICT'])->update();
    }
}
