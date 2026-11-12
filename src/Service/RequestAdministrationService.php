<?php
declare(strict_types=1);

namespace App\Service;

use App\Domain\Enum\{UserRole, RequestFlow, OsDisposition, RequestStatus};
use Cake\ORM\TableRegistry;
use Cake\ORM\Exception\PersistenceFailedException;
use Cake\Http\Exception\{ForbiddenException, ConflictException, NotFoundException};

class RequestAdministrationService
{
    public static function authorize(int $actorId): void
    {
        if (!TableRegistry::getTableLocator()->get('Users')->exists(['id' => $actorId, 'active' => true, 'role' => UserRole::ADMIN_PCM->value])) {
            throw new ForbiddenException('Somente o administrador PCM pode editar ou excluir solicitações.');
        }
    }

    public function edit(int $id, int $actorId, array $data, AssetsLookupInterface $assets, ?OrdersLookupInterface $orders = null): void
    {
        $tables = TableRegistry::getTableLocator();
        $requests = $tables->get('ServiceRequests');
        $requests->getConnection()->transactional(function () use ($id, $actorId, $data, $assets, $orders, $tables, $requests): void {
            self::authorize($actorId);
            $this->lock($requests, $id);
            $request = $requests->get($id);
            $old = $request->toArray();
            $fields = ['unit', 'sector_id', 'asset_code', 'asset_branch', 'maintenance_type', 'os_disposition'];
            $request = $requests->patchEntity($request, array_intersect_key($data, array_flip($fields)), ['associated' => []]);
            if ($request->hasErrors()) { throw new PersistenceFailedException($request, 'Revise os campos indicados.'); }
            $asset = $assets->find($request->asset_code, (string)$request->asset_branch);
            if (!$asset || ($data['asset_label'] ?? null) !== $asset['description'] . ' (' . $asset['code'] . ')') {
                throw new \InvalidArgumentException('Selecione um Bem/Equipamento válido da lista.');
            }
            $changedAsset = $old['asset_code'] !== $asset['code'] || $old['asset_branch'] !== $asset['branch'];
            $osNumber = trim((string)$request->totvs_os_number);
            $validation = null;
            if ($osNumber !== '' && $changedAsset) {
                // Consulta por número, sem usar o Bem antigo incorreto como filtro.
                $rows = ($orders ?? new TotvsOrdersService())->batch([$osNumber]);
                $osBranch = trim((string)$request->totvs_os_branch);
                $matches = array_values(array_filter($rows, fn($row) => ($row['number'] ?? null) === $osNumber &&
                    ($osBranch === '' || ($row['branch'] ?? null) === $osBranch)));
                if (count($matches) !== 1 || !is_string($matches[0]['branch'] ?? null)) {
                    throw new ConflictException('Não foi possível validar a O.S. ' . $osNumber . ' no TOTVS: não encontrada ou ambígua.');
                }
                $order = $matches[0];
                if (($order['asset_code'] ?? null) !== $asset['code'] || ($asset['branch'] !== '' && $order['branch'] !== $asset['branch'])) {
                    throw new ConflictException('O Bem selecionado não corresponde ao Bem da O.S. ' . $osNumber . ' no TOTVS.');
                }
                $validation = ['totvs_os_number' => $request->totvs_os_number, 'totvs_asset_match' => true,
                    'totvs_order' => ['number' => $order['number'], 'branch' => $order['branch'], 'asset_code' => $order['asset_code']]];
            }
            if ($request->os_disposition !== $old['os_disposition']) {
                if (trim((string)$request->totvs_os_number) !== '' || !in_array($request->status, [RequestStatus::AGUARDANDO_PCM->value, RequestStatus::EM_ANALISE->value], true)) {
                    throw new ConflictException('O fluxo só pode mudar antes do vínculo da O.S., em uma solicitação aguardando PCM.');
                }
                $flow = $request->os_disposition === OsDisposition::SOMENTE_ABERTURA->value ? RequestFlow::SOMENTE_ABERTURA->value : RequestFlow::ACOMPANHAMENTO->value;
                if ($flow === RequestFlow::SOMENTE_ABERTURA->value && $tables->get('RequestItems')->exists(['request_id' => $id])) {
                    throw new ConflictException('Solicitações com apontamentos devem manter o acompanhamento.');
                }
                $request->flow = $flow;
            }
            foreach (['sector_id' => 'sectors', 'maintenance_type' => 'maintenance_types'] as $field => $table) {
                $column = $field === 'sector_id' ? 'id' : 'code';
                if ($request->get($field) !== $old[$field] && !$requests->getConnection()->execute("SELECT $column FROM $table WHERE $column = :value AND active = true FOR SHARE", ['value' => $request->get($field)])->fetch('assoc')) {
                    throw new \InvalidArgumentException('Selecione mão de obra e tipo de manutenção ativos.');
                }
            }
            if ((string)($data['service_action_id'] ?? '') !== (string)$request->service_action_id || (string)($data['service_component_id'] ?? '') !== (string)$request->service_component_id) {
                $request->patch(ServiceSelection::resolve($data['service_action_id'] ?? null, $data['service_component_id'] ?? null, true));
            }
            // Snapshot obtido do TOTVS; asset_description é protegido contra dados do cliente.
            $request->patch(['asset_code' => $asset['code'], 'asset_branch' => $asset['branch'], 'asset_description' => $asset['description']], ['guard' => false]);
            $before = $after = [];
            foreach (array_merge($fields, ['flow', 'asset_description', 'description', 'service_action_id', 'service_action_name', 'service_component_id', 'service_component_name', 'service_id', 'service_name']) as $field) {
                if (($old[$field] ?? null) !== $request->get($field)) {
                    $before[$field] = $old[$field] ?? null;
                    $after[$field] = $request->get($field);
                }
            }
            if (!$after) { return; }
            if ($validation !== null) {
                foreach (['asset_code', 'asset_branch', 'asset_description'] as $field) {
                    $before[$field] = $old[$field];
                    $after[$field] = $request->get($field);
                }
                $before['totvs_os_number'] = $request->totvs_os_number;
                $after += $validation;
            }
            $requests->saveOrFail($request);
            $audits = $tables->get('RequestAudits');
            $audits->saveOrFail($audits->newEntity(['request_id' => $id, 'user_id' => $actorId, 'action' => 'REQUEST_UPDATED',
                'old_values' => $before, 'new_values' => $after,
                'created_at' => new \Cake\I18n\DateTime('now', 'UTC')]));
        });
    }

    public function delete(int $id, int $actorId): void
    {
        $tables = TableRegistry::getTableLocator();
        $requests = $tables->get('ServiceRequests');
        // Stage files reversibly; failed database deletion restores the original names.
        $staged = [];
        try {
            $requests->getConnection()->transactional(function () use ($id, $actorId, $tables, $requests, &$staged): void {
                self::authorize($actorId);
                $this->lock($requests, $id);
                $request = $requests->get($id);
                if (trim((string)$request->totvs_os_number) !== '') { throw new ConflictException('Não é possível excluir uma solicitação com O.S. TOTVS vinculada.'); }
                foreach ($tables->get('RequestAttachments')->find()->where(['request_id' => $id]) as $attachment) {
                    $name = $attachment->stored_name;
                    if (basename($name) !== $name || str_contains($name, '\\')) { throw new \RuntimeException('Caminho de anexo inválido.'); }
                    $path = AttachmentService::root() . DIRECTORY_SEPARATOR . $name;
                    if (!file_exists($path)) { continue; }
                    $temporary = $path . '.deleting-' . bin2hex(random_bytes(8));
                    if (!rename($path, $temporary)) { throw new \RuntimeException('Não foi possível remover os anexos.'); }
                    $staged[$path] = $temporary;
                }
                foreach (['RequestAttachments', 'RequestItems', 'RequestAudits'] as $child) { $tables->get($child)->deleteAll(['request_id' => $id]); }
                $requests->deleteOrFail($request);
            });
        } catch (\Throwable $e) {
            foreach ($staged as $path => $temporary) { rename($temporary, $path); }
            throw $e;
        }
        foreach ($staged as $temporary) {
            if (!unlink($temporary)) { throw new \RuntimeException('Registro excluído, mas não foi possível remover um arquivo físico. Contate o suporte.'); }
        }
    }

    private function lock(\Cake\ORM\Table $requests, int $id): void
    {
        if (!$requests->getConnection()->execute('SELECT id FROM service_requests WHERE id = :id FOR UPDATE', ['id' => $id])->fetch('assoc')) {
            throw new NotFoundException('Solicitação não encontrada.');
        }
    }
}
