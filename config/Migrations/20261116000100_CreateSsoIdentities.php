<?php
declare(strict_types=1);
use Migrations\BaseMigration;

class CreateSsoIdentities extends BaseMigration
{
    public function change(): void
    {
        $this->table('sso_identities')
            ->addColumn('user_id', 'integer')
            ->addColumn('issuer', 'string', ['limit' => 512])
            ->addColumn('subject', 'string', ['limit' => 255])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['issuer', 'subject'], ['unique' => true])
            ->addIndex(['user_id', 'issuer'], ['unique' => true])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'RESTRICT'])->create();
    }
}
