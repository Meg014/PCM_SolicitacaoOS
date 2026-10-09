<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateRequestAttachments extends BaseMigration
{
    public function change(): void
    {
        $this->table('request_attachments')
            ->addColumn('request_id', 'integer')
            ->addColumn('user_id', 'integer')
            ->addColumn('original_name', 'string', ['limit' => 200])
            ->addColumn('stored_name', 'string', ['limit' => 80])
            ->addColumn('mime_type', 'string', ['limit' => 30])
            ->addColumn('size', 'integer')
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('request_id', 'service_requests', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'RESTRICT'])
            ->addIndex(['stored_name'], ['unique' => true])
            ->addIndex(['request_id'])
            ->create();
    }
}
