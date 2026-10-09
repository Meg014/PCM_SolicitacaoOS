<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddPasswordChangeRequirement extends BaseMigration
{
    public function change(): void
    {
        $this->table('users')->addColumn('must_change_password', 'boolean', ['default' => true])->update();
    }
}
