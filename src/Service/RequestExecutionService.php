<?php
declare(strict_types=1);

namespace App\Service;

use App\Domain\Enum\RequestFlow;
use App\Domain\Enum\RequestStatus;
use App\Domain\Enum\UserRole;
use Cake\Http\Exception\ConflictException;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\ORM\TableRegistry;

final class RequestExecutionService
{
    public function handle(int $id, int $actorId, string $action, mixed $data = null): void
    {
        $tables = TableRegistry::getTableLocator();
        $requests = $tables->get('ServiceRequests');
        $requests->getConnection()->transactional(function () use ($tables, $requests, $id, $actorId, $action, $data): void {
            $actor = $tables->get('Users')->find()->where(['id' => $actorId, 'active' => true])->first();
            if (!$actor || !UserRole::tryFrom($actor->role)) { throw new ForbiddenException('Sua conta não tem acesso ao acompanhamento.'); }
            if (!$requests->getConnection()->execute('SELECT id FROM service_requests WHERE id = :id FOR UPDATE', ['id' => $id])->fetch('assoc')) {
                throw new NotFoundException('Solicitação não encontrada.');
            }
            $request = $requests->find()->where(['ServiceRequests.id' => $id])->where(RequestAccess::conditions($actor->role, $actorId))->first();
            if (!$request) { throw new NotFoundException('Solicitação não encontrada.'); }
            if ($request->flow !== RequestFlow::ACOMPANHAMENTO->value || !$request->totvs_os_number ||
                !in_array($request->status, [RequestStatus::OS_ABERTA->value, RequestStatus::EM_EXECUCAO->value], true)) {
                throw new ConflictException('O acompanhamento fica disponível após o vínculo da O.S. e até a conclusão do serviço.');
            }
            $old = $request->status;
            $oldDisposition = $request->os_disposition;
            $now = new \Cake\I18n\DateTime('now', 'UTC');
            $values = [];
            if ($action === 'items') {
                $items = RequestItemPreparation::prepare($data, 1);
                $itemTable = $tables->get('RequestItems');
                $last = $itemTable->find()->where(['request_id' => $id])->orderBy(['position' => 'DESC'])->first();
                $position = $last ? $last->position : 0;
                foreach ($items as $item) {
                    $item->patch(['request_id' => $id, 'recorded_by' => $actorId, 'position' => ++$position, 'created_at' => $now]);
                    $itemTable->saveOrFail($item);
                    $values['items'][] = RequestItemPreparation::snapshot($item);
                }
                $request->set('status', RequestStatus::EM_EXECUCAO->value);
                $event = 'EXECUTION_ITEMS_ADDED';
            } elseif ($action === 'ongoing') {
                $request->set('status', RequestStatus::EM_EXECUCAO->value);
                $event = 'SERVICE_ONGOING';
            } elseif ($action === 'complete') {
                $request->patch(['status' => RequestStatus::AGUARDANDO_ENCERRAMENTO->value, 'completed_by' => $actorId, 'completed_at' => $now,
                    'os_disposition' => \App\Domain\Enum\OsDisposition::PODE_ENCERRAR->value], ['guard' => false]);
                $event = 'SERVICE_COMPLETED';
                $values += ['completed_by' => $actorId, 'completed_at' => $now->format(DATE_ATOM)];
            } else { throw new \InvalidArgumentException('Ação inválida.'); }
            $requests->saveOrFail($request);
            $audits = $tables->get('RequestAudits');
            $audits->saveOrFail($audits->newEntity(['request_id' => $id, 'user_id' => $actorId, 'action' => $event,
                'new_values' => $values + ['status' => $request->status], 'created_at' => $now]));
            if ($oldDisposition !== $request->os_disposition) {
                $audits->saveOrFail($audits->newEntity(['request_id' => $id, 'user_id' => $actorId, 'action' => 'OS_DISPOSITION_CHANGED',
                    'old_values' => ['os_disposition' => $oldDisposition], 'new_values' => ['os_disposition' => $request->os_disposition], 'created_at' => $now]));
            }
            if ($old !== $request->status) {
                $audits->saveOrFail($audits->newEntity(['request_id' => $id, 'user_id' => $actorId, 'action' => 'STATUS_CHANGED',
                    'old_values' => ['status' => $old], 'new_values' => ['status' => $request->status], 'created_at' => $now]));
            }
        });
    }
}
