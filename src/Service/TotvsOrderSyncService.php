<?php
declare(strict_types=1);
namespace App\Service;

use App\Domain\Enum\RequestFlow;
use App\Domain\Enum\RequestStatus;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

class TotvsOrderSyncService
{
    public function __construct(private OrdersLookupInterface $orders) {}

    public function sync(): array
    {
        $requests = TableRegistry::getTableLocator()->get('ServiceRequests');
        $result = ['checked' => 0, 'changed' => 0, 'warnings' => 0, 'errors' => 0];
        $after = 0;
        do {
            $rows = $requests->find()->where(['id >' => $after, 'flow' => RequestFlow::ACOMPANHAMENTO->value,
                'status IN' => RequestStatus::activeValues(), 'totvs_os_number IS NOT' => null])
                ->orderBy(['id' => 'ASC'])->limit(50)->all()->toList();
            if (!$rows) { break; }
            $after = end($rows)->id;
            try { $orders = $this->orders->batch(array_map(fn($r) => trim($r->totvs_os_number), $rows)); }
            catch (\Throwable $e) {
                Log::error('Sincronização TOTVS indisponível. Nenhuma solicitação deste lote foi alterada.');
                $result['errors']++; break; // Não repetir timeouts em todos os lotes.
            }
            foreach ($rows as $row) {
                $branch = (string)($row->totvs_os_branch ?? $row->asset_branch);
                $matches = array_values(array_filter($orders, fn($os) => ($os['number'] ?? '') === trim($row->totvs_os_number) &&
                    ($branch === '' || ($os['branch'] ?? null) === $branch)));
                $result['checked']++;
                if (count($matches) !== 1 || ($matches[0]['asset_code'] ?? null) !== $row->asset_code ||
                    !is_string($matches[0]['branch'] ?? null) || TotvsOrderState::resolve($matches[0]) === null) {
                    Log::warning('Sincronização TOTVS inconclusiva para solicitação ID ' . $row->id . ': O.S./filial/Bem ausente, ambígua ou status não confirmado.');
                    $result['warnings']++; continue;
                }
                try {
                    $changed = $requests->getConnection()->transactional(function () use ($requests, $row, $matches): bool {
                        $requests->getConnection()->execute('SELECT id FROM service_requests WHERE id = :id FOR UPDATE', ['id' => $row->id]);
                        $current = $requests->get($row->id); $os = $matches[0];
                        if ($current->flow !== RequestFlow::ACOMPANHAMENTO->value || !in_array($current->status, RequestStatus::activeValues(), true) ||
                            $current->totvs_os_number !== $row->totvs_os_number || $current->asset_code !== $row->asset_code ||
                            $current->asset_branch !== $row->asset_branch || $current->totvs_os_branch !== $row->totvs_os_branch) { return false; }
                        $now = new DateTime('now', 'UTC');
                        $newStatus = TotvsOrderState::resolve($os) === 'CLOSED' ? RequestStatus::ENCERRADA->value : RequestStatus::EM_EXECUCAO->value;
                        $old = ['status' => $current->status, 'totvs_status' => $current->totvs_status,
                            'totvs_finished' => $current->totvs_finished, 'totvs_os_branch' => $current->totvs_os_branch];
                        $change = $current->status !== $newStatus || $current->totvs_status !== $os['situation'] ||
                            $current->totvs_finished !== $os['finished'] || $current->totvs_os_branch !== $os['branch'];
                        $current->patch(['status' => $newStatus, 'totvs_status' => $os['situation'], 'totvs_finished' => $os['finished'],
                            'totvs_os_branch' => $os['branch'], 'totvs_checked_at' => $now], ['guard' => false]);
                        if ($change) { $current->set('last_activity_at', $now); }
                        if ($newStatus === RequestStatus::ENCERRADA->value) {
                            $current->patch(['closed_by' => null, 'closed_at' => $now, 'totvs_closed_detected_at' => $now], ['guard' => false]);
                        }
                        $requests->saveOrFail($current);
                        if (!$change) { return false; }
                        $audits = TableRegistry::getTableLocator()->get('RequestAudits');
                        $audits->saveOrFail($audits->newEntity(['request_id' => $current->id, 'user_id' => null,
                            'action' => $newStatus === RequestStatus::ENCERRADA->value ? 'TOTVS_AUTO_CLOSED' : 'TOTVS_STATUS_SYNCED',
                            'old_values' => $old, 'new_values' => ['status' => $newStatus, 'totvs_os_number' => $current->totvs_os_number,
                                'totvs_os_branch' => $os['branch'], 'totvs_status' => $os['situation'], 'totvs_finished' => $os['finished'],
                                'source' => 'SISTEMA / SINCRONIZAÇÃO TOTVS', 'synced_at' => $now->format(DATE_ATOM)], 'created_at' => $now]));
                        return true;
                    });
                    if ($changed) { $result['changed']++; }
                } catch (\Throwable $e) {
                    Log::error('Falha ao persistir sincronização TOTVS da solicitação ID ' . $row->id . '. Alteração revertida.');
                    $result['errors']++;
                }
            }
        } while (count($rows) === 50);
        return $result;
    }
}
