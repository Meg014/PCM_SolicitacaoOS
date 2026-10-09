<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateRequestItems extends BaseMigration
{
    public function change(): void
    {
        $this->table('request_items')
            ->addColumn('request_id', 'integer')
            ->addColumn('description', 'text')
            ->addColumn('position', 'integer')
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('request_id', 'service_requests', 'id', ['delete' => 'RESTRICT'])
            ->addIndex(['request_id', 'position'], ['unique' => true])
            ->create();
    }
}
