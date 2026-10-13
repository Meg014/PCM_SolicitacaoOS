<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddRequestActivity extends BaseMigration
{
    public function change(): void
    {
        $this->table('service_requests')->addColumn('last_activity_at', 'datetime', ['null' => true])
            ->addIndex(['last_activity_at'])->update();
    }
}
