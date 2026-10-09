<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddRequestUnit extends BaseMigration
{
    public function change(): void
    {
        // Pedidos anteriores não recebem uma unidade presumida.
        $this->table('service_requests')->addColumn('unit', 'string', ['limit' => 10, 'null' => true])
            ->addIndex(['unit', 'created_at'])->update();
    }
}
