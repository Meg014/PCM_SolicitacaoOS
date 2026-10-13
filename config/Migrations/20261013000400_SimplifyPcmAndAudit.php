<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class SimplifyPcmAndAudit extends BaseMigration
{
    public function up(): void
    {
        $duplicates = $this->fetchAll("SELECT btrim(totvs_os_number) AS os, string_agg(number, ', ' ORDER BY id) AS requests
            FROM service_requests WHERE NULLIF(btrim(totvs_os_number), '') IS NOT NULL
            GROUP BY btrim(totvs_os_number) HAVING COUNT(*) > 1");
        if ($duplicates) {
            throw new RuntimeException('Regularize os vínculos duplicados antes da migration: ' .
                implode('; ', array_map(fn($row) => $row['os'] . ': ' . $row['requests'], $duplicates)));
        }
        $this->table('request_audits')
            ->addColumn('actor_snapshot', 'jsonb', ['null' => true])
            ->addColumn('old_status', 'string', ['limit' => 40, 'null' => true])
            ->addColumn('new_status', 'string', ['limit' => 40, 'null' => true])->update();
        $this->table('service_requests')->addColumn('last_item_at', 'datetime', ['null' => true])->update();
        // Antes desta rodada last_activity_at era atualizado somente por novos apontamentos.
        $this->execute('UPDATE service_requests SET last_item_at = last_activity_at WHERE last_activity_at IS NOT NULL');
        // Não inventa a identidade histórica: snapshots antigos permanecem NULL.
        $this->execute("INSERT INTO request_audits (request_id, user_id, action, old_values, new_values, old_status, new_status, created_at)
            SELECT id, NULL, 'PCM_FLOW_SIMPLIFIED', jsonb_build_object('status', status),
                jsonb_build_object('status', 'AGUARDANDO_PCM'), status, 'AGUARDANDO_PCM', timezone('UTC', now())
            FROM service_requests WHERE status = 'EM_ANALISE'");
        $this->execute("UPDATE service_requests SET status = 'AGUARDANDO_PCM', last_activity_at = timezone('UTC', now()),
            updated_at = timezone('UTC', now()) WHERE status = 'EM_ANALISE'");
        // Mantém assigned_to/assigned_at e seus eventos antigos apenas como histórico.
        $this->execute("CREATE UNIQUE INDEX service_requests_totvs_os_unique ON service_requests (btrim(totvs_os_number))
            WHERE NULLIF(btrim(totvs_os_number), '') IS NOT NULL");
    }

    public function down(): void
    {
        throw new RuntimeException('A simplificação do fluxo preserva auditoria e não permite rollback automático.');
    }
}
