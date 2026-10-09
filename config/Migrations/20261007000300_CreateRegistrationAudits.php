<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateRegistrationAudits extends BaseMigration
{
    public function change(): void
    {
        $this->table('registration_audits')
            ->addColumn('user_id', 'integer', ['null' => true])
            ->addColumn('resource', 'string', ['limit' => 30])
            ->addColumn('record_id', 'integer')
            ->addColumn('action', 'string', ['limit' => 30])
            ->addColumn('old_values', 'jsonb', ['null' => true])
            ->addColumn('new_values', 'jsonb')
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'RESTRICT'])
            ->addIndex(['resource', 'record_id', 'created_at'])
            ->create();
    }
}
