<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class MapLegacyOsDisposition extends BaseMigration
{
    public function up(): void
    {
        // Acompanhamentos sem conclusão registrada continuam abertos; não presume fim pelo número de itens.
        $mapping = "CASE WHEN flow = 'SOMENTE_ABERTURA' THEN 'SOMENTE_ABERTURA'
            WHEN status IN ('AGUARDANDO_ENCERRAMENTO', 'ENCERRADA') THEN 'PODE_ENCERRAR' ELSE 'MANTER_ABERTA' END";
        $this->execute("INSERT INTO request_audits (request_id, user_id, action, old_values, new_values, created_at)
            SELECT id, NULL, 'OS_DISPOSITION_CHANGED', jsonb_build_object('os_disposition', NULL),
                jsonb_build_object('os_disposition', $mapping, 'reason', 'Mapeamento compatível do fluxo anterior'),
                CURRENT_TIMESTAMP AT TIME ZONE 'UTC' FROM service_requests WHERE os_disposition IS NULL");
        $this->execute("UPDATE service_requests SET os_disposition = $mapping WHERE os_disposition IS NULL");
    }

    public function down(): void
    {
        throw new RuntimeException('O mapeamento preserva histórico e não admite reversão automática.');
    }
}
