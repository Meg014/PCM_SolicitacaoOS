<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddRequestSoftDeletion extends BaseMigration
{
    public function change(): void
    {
        $this->table('service_requests')
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->addColumn('deleted_by', 'integer', ['null' => true])
            ->addColumn('deletion_reason', 'text', ['null' => true])
            ->addForeignKey('deleted_by', 'users', 'id', ['delete' => 'RESTRICT'])
            ->addIndex(['deleted_at'])->update();
    }
}
