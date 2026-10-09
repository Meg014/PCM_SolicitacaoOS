<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class AddSharedExecutionFlow extends BaseMigration
{
    public function up(): void
    {
        $this->table('service_requests')
            ->addColumn('flow', 'string', ['limit' => 30, 'default' => 'SOMENTE_ABERTURA'])
            ->addColumn('completed_by', 'integer', ['null' => true])
            ->addColumn('completed_at', 'timestamp', ['null' => true])
            ->addColumn('closed_by', 'integer', ['null' => true])
            ->addColumn('closed_at', 'timestamp', ['null' => true])
            ->addForeignKey('completed_by', 'users', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('closed_by', 'users', 'id', ['delete' => 'RESTRICT'])
            ->addIndex(['asset_code', 'asset_branch', 'status'])->update();
        $this->table('request_items')->addColumn('recorded_by', 'integer', ['null' => true])
            ->addForeignKey('recorded_by', 'users', 'id', ['delete' => 'RESTRICT'])->update();
        // Autor nulo identifica uma adequação de dados feita pelo sistema, sem atribuí-la ao solicitante.
        $this->execute('ALTER TABLE request_audits ALTER COLUMN user_id DROP NOT NULL');
        $this->execute("INSERT INTO request_audits (request_id, user_id, action, old_values, new_values, created_at)
            SELECT id, NULL, 'STATUS_CHANGED', jsonb_build_object('status', status),
                jsonb_build_object('status', 'ATENDIDA', 'reason', 'Compatibilidade: pedido anterior de abertura já atendido'),
                CURRENT_TIMESTAMP AT TIME ZONE 'UTC' FROM service_requests WHERE status = 'OS_ABERTA'");
        $this->execute("UPDATE service_requests SET status = 'ATENDIDA' WHERE status = 'OS_ABERTA'");
    }

    public function down(): void
    {
        throw new RuntimeException('Esta migration preserva histórico e não admite reversão automática.');
    }
}
