<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddEmployeeSnapshotsToRequestItems extends BaseMigration
{
    public function change(): void
    {
        // Nullable for historical free-text records; never invent employee identities.
        $this->table('request_items')
            ->addColumn('employee_registration', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('employee_name', 'string', ['limit' => 150, 'null' => true])
            ->addColumn('employee_function_code', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('employee_function_name', 'string', ['limit' => 255, 'null' => true])
            ->update();
    }
}
