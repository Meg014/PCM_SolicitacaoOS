<?php
declare(strict_types=1);

namespace App\Service;

use App\Domain\Enum\RequestFlow;
use App\Domain\Enum\RequestStatus;
use App\Model\Entity\ServiceRequest;
use Cake\ORM\Exception\PersistenceFailedException;
use Cake\ORM\TableRegistry;

class RequestCreationService
{
    public function __construct(private AssetsLookupInterface $assets) {}

    public function create(array $data, int $userId): ServiceRequest
    {
        $attachments = new AttachmentService();
        $files = $attachments->validate($data['photos'] ?? null);
        $locator = TableRegistry::getTableLocator();
        $requests = $locator->get('ServiceRequests');
        // Uma única escolha no formulário; flow permanece apenas para compatibilidade interna.
        $choice = is_string($data['os_disposition'] ?? null) ? \App\Domain\Enum\OsDisposition::tryFrom($data['os_disposition']) : null;
        $data['flow'] = $choice === \App\Domain\Enum\OsDisposition::SOMENTE_ABERTURA ? RequestFlow::SOMENTE_ABERTURA->value : RequestFlow::ACOMPANHAMENTO->value;
        $entity = $requests->newEntity(array_intersect_key($data, array_flip([
            'sector_id', 'asset_code', 'asset_branch', 'maintenance_type', 'description', 'unit', 'flow', 'os_disposition',
        ])));
        try {
            $raw = $data['items'] ?? [];
            if (is_array($raw)) {
                $raw = array_filter($raw, fn($row) => !is_array($row) || count(array_filter($row, fn($v) => !is_scalar($v) || trim((string)$v) !== '')) > 0);
            }
            if ($entity->flow === RequestFlow::SOMENTE_ABERTURA->value && $raw) {
                throw new \InvalidArgumentException('Apontamentos são permitidos apenas no fluxo com acompanhamento.');
            }
            $items = RequestItemPreparation::prepare($raw);
        } catch (\InvalidArgumentException $e) {
            $entity->setError('items', $e->getMessage());
            $items = [];
        }
        if ($entity->hasErrors()) { throw new PersistenceFailedException($entity, 'Revise os campos da solicitação.'); }
        $asset = $this->assets->find($entity->asset_code, (string)$entity->asset_branch);
        if ($asset === null) {
            $entity->setError('asset_code', 'Este equipamento não foi encontrado. Pesquise e selecione um item da lista.');
            throw new PersistenceFailedException($entity, 'Selecione um equipamento válido.');
        }
        try {
            return $requests->getConnection()->transactional(function () use ($locator, $requests, $entity, $items, $asset, $userId, $attachments, $files, $data): ServiceRequest {
                $connection = $requests->getConnection();
                // Evita criações concorrentes sem confirmação para o mesmo Bem + filial.
                $connection->execute('SELECT pg_advisory_xact_lock(hashtextextended(:bem, 0))', [
                    'bem' => json_encode([$asset['code'], $asset['branch']], JSON_THROW_ON_ERROR),
                ]);
                $active = ActiveAssetRequests::find($asset['code'], $asset['branch']);
                $reason = $data['duplicate_reason'] ?? '';
                if (!is_string($reason) || mb_strlen($reason) > 2000) {
                    $entity->setError('duplicate_reason', 'Use até 2.000 caracteres para explicar o novo pedido.');
                    throw new PersistenceFailedException($entity, 'Revise o motivo informado.');
                }
                if ($active && !in_array($data['confirm_existing'] ?? null, [true, 1, '1'], true)) {
                    $entity->setError('confirm_existing', 'Já existe solicitação ativa para este equipamento. Confira o alerta e confirme se deseja criar outra.');
                    throw new PersistenceFailedException($entity, 'Confirme a nova solicitação.');
                }
                $sector = $connection->execute('SELECT id FROM sectors WHERE id = :id AND active = true FOR SHARE', ['id' => $entity->sector_id])->fetch('assoc');
                if (!$sector) {
                    $entity->setError('sector_id', 'Selecione uma mão de obra ativa da lista.');
                    throw new PersistenceFailedException($entity, 'Selecione uma mão de obra válida.');
                }
                if (!$locator->get('Users')->exists(['id' => $userId, 'active' => true])) {
                    throw new \Cake\Http\Exception\ForbiddenException('Sua conta não está ativa. Entre novamente.');
                }
                $id = (int)$connection->execute("SELECT nextval(pg_get_serial_sequence('service_requests', 'id')) AS id")->fetch('assoc')['id'];
                $now = new \Cake\I18n\DateTime('now', 'UTC');
                $entity->patch([
                    'id' => $id, 'number' => sprintf('SOL-%s-%06d', $now->setTimezone('America/Sao_Paulo')->format('Y'), $id),
                    'user_id' => $userId, 'status' => RequestStatus::AGUARDANDO_PCM->value,
                    'asset_code' => $asset['code'], 'asset_branch' => $asset['branch'],
                    'asset_description' => $asset['description'], 'totvs_os_number' => null,
                ], ['guard' => false]);
                $requests->saveOrFail($entity);
                foreach ($items as $item) {
                    $item->patch(['request_id' => $id, 'created_at' => $now, 'recorded_by' => $userId]);
                    $locator->get('RequestItems')->saveOrFail($item);
                }
                $audits = $locator->get('RequestAudits');
                $audits->saveOrFail($audits->newEntity([
                    'request_id' => $id, 'user_id' => $userId, 'action' => 'REQUEST_CREATED',
                    'new_values' => [
                        'number' => $entity->number, 'user_id' => $userId, 'sector_id' => $entity->sector_id,
                        'asset_code' => $asset['code'], 'asset_branch' => $asset['branch'], 'asset_description' => $asset['description'],
                        'maintenance_type' => $entity->maintenance_type, 'description' => $entity->description, 'unit' => $entity->unit,
                        'status' => $entity->status, 'flow' => $entity->flow, 'os_disposition' => $entity->os_disposition,
                        'items' => array_map([RequestItemPreparation::class, 'snapshot'], $items),
                    ], 'created_at' => $now,
                ]));
                if ($active) {
                    $audits->saveOrFail($audits->newEntity([
                        'request_id' => $id, 'user_id' => $userId, 'action' => 'DUPLICATE_CONFIRMED',
                        'new_values' => ['active_requests' => array_map(fn($r) => $r->id, $active), 'reason' => trim($reason)], 'created_at' => $now,
                    ]));
                }
                $attachments->save($files, $id, $userId);
                return $entity;
            });
        } catch (\Throwable $e) {
            $attachments->cleanup();
            throw $e;
        }
    }
}
