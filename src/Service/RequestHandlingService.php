<?php
declare(strict_types=1);

namespace App\Service;

use App\Domain\Enum\RequestStatus;
use App\Domain\Enum\UserRole;
use Cake\Http\Exception\ConflictException;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\ORM\TableRegistry;

class RequestHandlingService
{
    public function handle(int $id, int $actorId, string $action, mixed $value = null): void
    {
        $tables = TableRegistry::getTableLocator();
        $requests = $tables->get('ServiceRequests');
        $requests->getConnection()->transactional(function () use ($tables, $requests, $id, $actorId, $action, $value): void {
            if (!$tables->get('Users')->exists(['id' => $actorId, 'active' => true, 'role' => UserRole::ADMIN_PCM->value])) {
                throw new ForbiddenException('Somente o PCM pode tratar solicitações.');
            }
            $locked = $requests->getConnection()->execute('SELECT id FROM service_requests WHERE id = :id FOR UPDATE', ['id' => $id])->fetch('assoc');
            if (!$locked) {
                throw new NotFoundException('Solicitação não encontrada.');
            }
            $request = $requests->get($id);
            $old = ['status' => $request->status, 'totvs_os_number' => $request->totvs_os_number];
            $now = new \Cake\I18n\DateTime('now', 'UTC');
            $pending = [RequestStatus::AGUARDANDO_PCM->value, RequestStatus::EM_ANALISE->value];
            if ($action === 'link') {
                if (!in_array($request->status, $pending, true)) {
                    throw new ConflictException('Não é possível vincular O.S. a uma solicitação encerrada.');
                }
                if (!is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > 100 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
                    throw new \InvalidArgumentException('Informe o número da O.S. TOTVS, com até 100 caracteres.');
                }
                // Serializa vínculos concorrentes do mesmo número; o índice também protege gravações diretas.
                $requests->getConnection()->execute('SELECT pg_advisory_xact_lock(hashtextextended(:os, 0))', ['os' => 'os:' . trim($value)]);
                $existing = $requests->find()->where(['ServiceRequests.id !=' => $id])
                    ->where(['btrim(ServiceRequests.totvs_os_number)' => trim($value)])->first();
                if ($existing) { throw new ConflictException('Esta O.S. já está vinculada à solicitação ' . $existing->number . '.'); }
                $state = match ($request->os_disposition) {
                    \App\Domain\Enum\OsDisposition::SOMENTE_ABERTURA->value => RequestStatus::ATENDIDA,
                    \App\Domain\Enum\OsDisposition::MANTER_ABERTA->value => RequestStatus::EM_EXECUCAO,
                    \App\Domain\Enum\OsDisposition::PODE_ENCERRAR->value => RequestStatus::AGUARDANDO_ENCERRAMENTO,
                    default => $request->flow === \App\Domain\Enum\RequestFlow::ACOMPANHAMENTO->value
                    ? ($tables->get('RequestItems')->exists(['request_id' => $id]) ? RequestStatus::EM_EXECUCAO : RequestStatus::OS_ABERTA)
                    : RequestStatus::ATENDIDA,
                };
                $changes = ['status' => $state->value, 'totvs_os_number' => trim($value), 'os_linked_by' => $actorId, 'os_linked_at' => $now];
                $event = 'TOTVS_OS_LINKED';
            } elseif ($action === 'cancel') {
                if (!in_array($request->status, RequestStatus::activeValues(), true)) {
                    throw new ConflictException('Esta solicitação já foi encerrada. O cancelamento não altera a O.S. no TOTVS.');
                }
                if (!is_string($value) || trim($value) === '' || mb_strlen(trim($value)) > 2000) {
                    throw new \InvalidArgumentException('Informe o motivo do cancelamento, com até 2.000 caracteres.');
                }
                $changes = ['status' => RequestStatus::CANCELADA->value, 'cancellation_reason' => trim($value), 'cancelled_by' => $actorId, 'cancelled_at' => $now];
                $event = 'REQUEST_CANCELLED';
            } else {
                throw new \InvalidArgumentException('Ação inválida.');
            }
            $changes['last_activity_at'] = $now;
            $request->patch($changes, ['guard' => false]);
            $requests->saveOrFail($request);
            $audits = $tables->get('RequestAudits');
            $snapshot = array_map(fn($v) => $v instanceof \DateTimeInterface ? $v->format(DATE_ATOM) : $v, $changes);
            foreach ([$event => $snapshot, 'STATUS_CHANGED' => ['status' => $request->status]] as $auditAction => $values) {
                $audits->saveOrFail($audits->newEntity([
                    'request_id' => $id, 'user_id' => $actorId, 'action' => $auditAction,
                    'old_values' => $auditAction === 'STATUS_CHANGED' ? ['status' => $old['status']] : $old,
                    'new_values' => $values, 'created_at' => $now,
                ]));
            }
        });
    }
}
