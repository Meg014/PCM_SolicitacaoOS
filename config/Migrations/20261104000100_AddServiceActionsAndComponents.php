<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddServiceActionsAndComponents extends BaseMigration
{
    public function up(): void
    {
        $options = [
            'service_actions' => ['Limpar', 'Soldar', 'Trocar', 'Apertar', 'Ajustar', 'Produzir', 'Lubrificar', 'Substituir', 'Emendar', 'Reparar', 'Inspecionar', 'Alinhar', 'Regular', 'Manutenção'],
            'service_components' => ['Geral', 'Chapa', 'Parafuso', 'Motor', 'Cabo', 'Correia', 'Rolamento', 'Mancal', 'Eixo', 'Redutor', 'Corrente', 'Sensor', 'Painel', 'Estrutura', 'Tubulação', 'Válvula', 'Bomba'],
        ];
        foreach ($options as $table => $names) {
            $this->table($table)->addColumn('name', 'string', ['limit' => 150])
                ->addColumn('active', 'boolean', ['default' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['name'], ['unique' => true])->addIndex(['active', 'name'])->create();
            $this->table($table)->insert(array_map(fn($name) => ['name' => $name], $names))->saveData();
        }
        foreach (['service_requests', 'request_items'] as $table) {
            $this->table($table)
                ->addColumn('service_action_id', 'integer', ['null' => true])
                ->addColumn('service_component_id', 'integer', ['null' => true])
                ->addColumn('service_action_name', 'string', ['limit' => 150, 'null' => true])
                ->addColumn('service_component_name', 'string', ['limit' => 150, 'null' => true])
                ->addForeignKey('service_action_id', 'service_actions', 'id', ['delete' => 'RESTRICT'])
                ->addForeignKey('service_component_id', 'service_components', 'id', ['delete' => 'RESTRICT'])
                ->addIndex(['service_action_id'])->addIndex(['service_component_id'])->update();
        }
    }

    public function down(): void
    {
        // Dropping identities in use would destroy filterable history.
        foreach (['service_requests', 'request_items'] as $table) {
            $row = $this->fetchRow("SELECT COUNT(*) AS total FROM $table WHERE service_action_id IS NOT NULL OR service_component_id IS NOT NULL");
            if ((int)$row['total'] > 0) { throw new RuntimeException('Há ações/componentes utilizados. Preserve o histórico antes de reverter.'); }
        }
        foreach (['service_requests', 'request_items'] as $table) {
            $this->table($table)->dropForeignKey('service_action_id')->dropForeignKey('service_component_id')
                ->removeColumn('service_action_id')->removeColumn('service_component_id')
                ->removeColumn('service_action_name')->removeColumn('service_component_name')->update();
        }
        $this->table('service_actions')->drop()->save();
        $this->table('service_components')->drop()->save();
    }
}
