<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddRequestHandling extends BaseMigration
{
    public function change(): void
    {
        $this->table('service_requests')
            ->addColumn('assigned_to', 'integer', ['null' => true])
            ->addColumn('assigned_at', 'timestamp', ['null' => true])
            ->addColumn('os_linked_by', 'integer', ['null' => true])
            ->addColumn('os_linked_at', 'timestamp', ['null' => true])
            ->addColumn('cancelled_by', 'integer', ['null' => true])
            ->addColumn('cancelled_at', 'timestamp', ['null' => true])
            ->addColumn('cancellation_reason', 'text', ['null' => true])
            ->addForeignKey('assigned_to', 'users', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('os_linked_by', 'users', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('cancelled_by', 'users', 'id', ['delete' => 'RESTRICT'])
            ->addIndex(['sector_id', 'created_at'])
            ->update();
    }
}
