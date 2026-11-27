<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddChecklistRouteToServiceRequests extends BaseMigration
{
    public function change(): void
    {
        $this->table('service_requests')
            ->addColumn('checklist_route', 'text', ['null' => true])
            ->update();
    }
}
