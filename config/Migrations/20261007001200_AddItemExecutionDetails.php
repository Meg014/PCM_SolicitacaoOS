<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddItemExecutionDetails extends BaseMigration
{
    public function change(): void
    {
        $this->table('request_items')->addColumn('executor', 'string', ['limit' => 150, 'null' => true])
            ->addColumn('started_at', 'timestamp', ['null' => true])
            ->addColumn('ended_at', 'timestamp', ['null' => true])->update();
    }
}
