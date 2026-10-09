<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddOsDisposition extends BaseMigration
{
    public function change(): void
    {
        // Sem inferir a intenção do solicitante a partir de apontamentos anteriores.
        $this->table('service_requests')->addColumn('os_disposition', 'string', ['limit' => 20, 'null' => true])->update();
    }
}
