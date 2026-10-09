<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateRequestAudits extends BaseMigration
{
    public function change(): void
    {
        $this->table('request_audits')
            ->addColumn('request_id', 'integer')
            ->addColumn('user_id', 'integer')
            ->addColumn('action', 'string', ['limit' => 50])
            ->addColumn('old_values', 'jsonb', ['null' => true])
            ->addColumn('new_values', 'jsonb')
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('request_id', 'service_requests', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'RESTRICT'])
            ->addIndex(['request_id', 'created_at'])
            ->create();
    }
}
