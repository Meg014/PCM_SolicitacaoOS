<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddTotvsOrderSync extends BaseMigration
{
    public function change(): void
    {
        $this->table('service_requests')
            ->addColumn('totvs_os_branch', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('totvs_status', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('totvs_finished', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('totvs_checked_at', 'datetime', ['null' => true])
            ->addColumn('totvs_closed_detected_at', 'datetime', ['null' => true])
            ->addIndex(['flow', 'status'])->update();
    }
}
