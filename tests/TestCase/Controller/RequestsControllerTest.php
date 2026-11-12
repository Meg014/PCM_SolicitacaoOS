<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Domain\Enum\MaintenanceType;
use App\Domain\Enum\RequestStatus;
use App\Service\AssetsLookupInterface;
use App\Service\AssetsUnavailableException;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class RequestsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = [
        'app.MaintenanceTypes', 'app.Users', 'app.Sectors', 'app.Services', 'app.ServiceActions', 'app.ServiceComponents', 'app.RegistrationAudits',
        'app.ServiceRequests', 'app.RequestItems', 'app.RequestAudits', 'app.RequestAttachments',
    ];

    public function setUp(): void
    {
        parent::setUp();
        $this->mockService(\App\Service\EmployeesLookupInterface::class, fn() => new \App\Test\Support\FakeEmployees());
        $this->mockService(AssetsLookupInterface::class, fn() => new class implements AssetsLookupInterface {
            public function search(string $term): array
            {
                return mb_strlen(trim($term)) < 2 ? [] : [['code' => 'BEM01', 'branch' => '01', 'description' => 'Bomba de água']];
            }

            public function find(string $code, string $branch): ?array
            {
                if ($code === 'FAB 80 158' && $branch === '01') { return ['code' => $code, 'branch' => $branch, 'description' => 'Equipamento da fábrica']; }
                if ($code === 'BEM02' && $branch === '02') { return ['code' => $code, 'branch' => $branch, 'description' => 'Motor elétrico']; }
                return $code === 'BEM01' && $branch === '01'
                    ? ['code' => $code, 'branch' => $branch, 'description' => 'Bomba de água'] : null;
            }
        });
        \Cake\Datasource\ConnectionManager::get('test')->execute(
            "SELECT setval(pg_get_serial_sequence('service_requests', 'id'), (SELECT MAX(id) FROM service_requests))",
        );
    }

    private function payload(): array
    {
        return [
            'flow' => 'ACOMPANHAMENTO', 'os_disposition' => 'MANTER_ABERTA', 'confirm_existing' => '1',
            'unit' => 'USINA', 'asset_code' => 'BEM01', 'asset_branch' => '01', 'asset_label' => 'Bomba de água (BEM01)',
            'sector_id' => 1, 'sector_label' => 'Manutenção',
            'maintenance_type' => MaintenanceType::CORRETIVA->value,
            'service_component_id' => 1, 'service_action_id' => 1,
            'items' => [['description' => 'Trocar vedação', 'employee_registration' => 'José externo', 'service_date' => '2026-10-06', 'start_time' => '08:00', 'end_time' => '09:00'], ['description' => 'Verificar tubulação', 'employee_registration' => 'Maria externa', 'service_date' => '2026-10-07', 'start_time' => '10:00', 'end_time' => '11:30']],
        ];
    }

    public function testAdministrativeActionsRejectOperationalProfiles(): void
    {
        foreach ([2, 3] as $id) {
            $this->session(['Auth' => $id]);
            $this->get('/solicitacoes/1/editar');
            $this->assertResponseCode(403);
            $this->enableCsrfToken('pcm_solicitacao_os_csrf');
            $this->post('/solicitacoes/1/editar', $this->payload());
            $this->assertResponseCode(403);
            $this->post('/solicitacoes/1/excluir');
            $this->assertResponseCode(403);
        }
        $this->assertSame(2, $this->requests()->find()->count());
    }

    public function testAdministrativeEditPreservesIdentityAndChildrenAndAuditsChanges(): void
    {
        $this->session(['Auth' => 1]);
        $this->submit($this->payload());
        $original = $this->requests()->find()->orderBy(['id' => 'DESC'])->contain(['RequestItems', 'RequestAudits'])->firstOrFail();
        $this->get('/solicitacoes/' . $original->id . '/editar');
        $this->assertResponseOk();
        $data = array_replace($this->payload(), ['unit' => 'FABRICA', 'number' => 'FORJADO', 'totvs_os_number' => '999', 'status' => 'CANCELADA', 'user_id' => 2, 'items' => []]);
        $this->post('/solicitacoes/' . $original->id . '/editar', $data);
        $this->assertResponseCode(302);
        $updated = $this->requests()->get($original->id, contain: ['RequestItems', 'RequestAudits']);
        $this->assertSame('FABRICA', $updated->unit);
        foreach (['number', 'totvs_os_number', 'status', 'user_id'] as $field) { $this->assertSame($original->get($field), $updated->get($field)); }
        $this->assertEquals($original->request_items, $updated->request_items);
        $audit = end($updated->request_audits);
        $this->assertSame('REQUEST_UPDATED', $audit->action);
        $this->assertSame(1, $audit->user_id);
        $this->assertSame('ADMIN_PCM', $audit->actor_snapshot['role']);
        $this->assertSame(['unit' => 'USINA'], $audit->old_values);
        $this->assertSame(['unit' => 'FABRICA'], $audit->new_values);
        $this->assertNotNull($audit->created_at);
    }

    public function testAdministrativeEditRejectsInvalidAssetAndUnsafeFlow(): void
    {
        $this->session(['Auth' => 1]);
        $this->submit($this->payload());
        $original = $this->requests()->find()->orderBy(['id' => 'DESC'])->firstOrFail();
        foreach ([['asset_label' => 'Texto livre'], ['asset_code' => 'INVALIDO'], ['os_disposition' => 'SOMENTE_ABERTURA']] as $override) {
            $this->post('/solicitacoes/' . $original->id . '/editar', array_replace($this->payload(), $override));
            $this->assertResponseOk();
            $this->assertEquals($original->toArray(), $this->requests()->get($original->id)->toArray());
        }
        $this->requests()->updateAll(['totvs_os_number' => 'OS123'], ['id' => $original->id]);
        $this->post('/solicitacoes/' . $original->id . '/editar', array_replace($this->payload(), ['os_disposition' => 'PODE_ENCERRAR']));
        $this->assertResponseOk();
        $this->assertSame('OS123', $this->requests()->get($original->id)->totvs_os_number);
    }

    public function testAdministrativeDeleteRemovesChildrenAndPhysicalAttachments(): void
    {
        $this->session(['Auth' => 1]);
        $this->submit($this->payload());
        $request = $this->requests()->find()->orderBy(['id' => 'DESC'])->firstOrFail();
        $tables = TableRegistry::getTableLocator();
        $root = TMP . 'admin-delete-' . bin2hex(random_bytes(6));
        mkdir($root);
        \Cake\Core\Configure::write('Attachments.path', $root);
        file_put_contents($root . DS . 'test.jpg', 'test');
        try {
            $attachments = $tables->get('RequestAttachments');
            $attachments->saveOrFail($attachments->newEntity(['request_id' => $request->id, 'user_id' => 1, 'original_name' => 'test.jpg', 'stored_name' => 'test.jpg', 'mime_type' => 'image/jpeg', 'size' => 4, 'created_at' => new \Cake\I18n\DateTime()]));
            $this->get('/solicitacoes/' . $request->id);
            $this->assertResponseContains('Tem certeza que deseja excluir definitivamente');
            $this->get('/solicitacoes/' . $request->id . '/excluir');
            $this->assertResponseCode(404);
            $this->post('/solicitacoes/' . $request->id . '/excluir');
            $this->assertResponseCode(302);
            $this->assertFalse($this->requests()->exists(['id' => $request->id]));
            foreach (['RequestItems', 'RequestAttachments', 'RequestAudits'] as $table) { $this->assertSame(0, $tables->get($table)->find()->where(['request_id' => $request->id])->count()); }
            $this->assertFileDoesNotExist($root . DS . 'test.jpg');
            $this->assertSame([], array_values(array_diff(scandir($root), ['.', '..'])));
            $this->assertTrue($this->requests()->exists(['id' => 1]));
        } finally {
            foreach (glob($root . DS . '*') as $path) { unlink($path); }
            rmdir($root);
            \Cake\Core\Configure::delete('Attachments.path');
        }
    }

    public function testAdministrativeDeleteBlocksLinkedOrder(): void
    {
        $this->session(['Auth' => 1]);
        $this->requests()->updateAll(['totvs_os_number' => 'OS123'], ['id' => 1]);
        $this->enableCsrfToken('pcm_solicitacao_os_csrf');
        $this->post('/solicitacoes/1/excluir');
        $this->assertRedirect('/solicitacoes/1');
        $this->assertSame('OS123', $this->requests()->get(1)->totvs_os_number);
    }

    public function testAdministrativeEditAuditFailureRollsBackChanges(): void
    {
        $this->session(['Auth' => 1]);
        $this->submit($this->payload());
        $request = $this->requests()->find()->orderBy(['id' => 'DESC'])->firstOrFail();
        $audits = TableRegistry::getTableLocator()->get('RequestAudits');
        $audits->getValidator()->add('action', 'reject', ['rule' => fn() => false]);
        $assets = $this->createMock(AssetsLookupInterface::class);
        $assets->method('find')->willReturn(['code' => 'BEM01', 'branch' => '01', 'description' => 'Bomba de água']);
        try {
            (new \App\Service\RequestAdministrationService())->edit($request->id, 1, array_replace($this->payload(), ['unit' => 'FABRICA']), $assets);
            $this->fail('A falha de auditoria deve reverter a edição.');
        } catch (\Cake\ORM\Exception\PersistenceFailedException $e) {
            $this->assertTrue($e->getEntity()->hasErrors());
        }
        $this->assertSame('USINA', $this->requests()->get($request->id)->unit);
        $this->assertSame(0, $audits->find()->where(['action' => 'REQUEST_UPDATED'])->count());
    }

    public function testAdministrativeEditAllowsSafeFlowChangeBeforeLink(): void
    {
        $this->session(['Auth' => 1]);
        $data = array_replace($this->payload(), ['items' => [], 'os_disposition' => 'SOMENTE_ABERTURA']);
        $this->submit($data);
        $request = $this->requests()->find()->orderBy(['id' => 'DESC'])->firstOrFail();
        $this->post('/solicitacoes/' . $request->id . '/editar', array_replace($data, ['os_disposition' => 'MANTER_ABERTA']));
        $this->assertResponseCode(302);
        $updated = $this->requests()->get($request->id);
        $this->assertSame('ACOMPANHAMENTO', $updated->flow);
        $this->assertSame('MANTER_ABERTA', $updated->os_disposition);
        $this->assertSame($request->status, $updated->status);
    }

    public function testAdministrativeEditRebuildsServiceAndPreservesLinkedOrder(): void
    {
        $this->session(['Auth' => 1]);
        $this->submit($this->payload());
        $request = $this->requests()->find()->orderBy(['id' => 'DESC'])->firstOrFail();
        $this->requests()->updateAll(['totvs_os_number' => 'OS123'], ['id' => $request->id]);
        $this->post('/solicitacoes/' . $request->id . '/editar', array_replace($this->payload(), [
            'service_action_id' => 2, 'service_component_id' => 2, 'maintenance_type' => 'PREVENTIVA', 'totvs_os_number' => 'FORJADA',
        ]));
        $this->assertResponseCode(302);
        $updated = $this->requests()->get($request->id);
        $this->assertSame('Soldar chapa', $updated->description);
        $this->assertSame('PREVENTIVA', $updated->maintenance_type);
        $this->assertSame('OS123', $updated->totvs_os_number);
        $audit = TableRegistry::getTableLocator()->get('RequestAudits')->find()->where(['request_id' => $request->id, 'action' => 'REQUEST_UPDATED'])->firstOrFail();
        $this->assertSame('Limpeza geral', $audit->old_values['description']);
        $this->assertSame('Soldar chapa', $audit->new_values['description']);
    }

    public function testAdministrativeEditChangesVerifiedAssetWithAuditAndPreservesAttachments(): void
    {
        $this->session(['Auth' => 1]);
        $this->submit($this->payload());
        $original = $this->requests()->find()->orderBy(['id' => 'DESC'])->contain(['RequestItems'])->firstOrFail();
        $tables = TableRegistry::getTableLocator();
        $attachments = $tables->get('RequestAttachments');
        $attachment = $attachments->saveOrFail($attachments->newEntity(['request_id' => $original->id, 'user_id' => 1,
            'original_name' => 'original.jpg', 'stored_name' => 'original.jpg', 'mime_type' => 'image/jpeg', 'size' => 4, 'created_at' => new \Cake\I18n\DateTime()]));
        $this->post('/solicitacoes/' . $original->id . '/editar', array_replace($this->payload(), [
            'asset_code' => 'BEM02', 'asset_branch' => '02', 'asset_label' => 'Motor elétrico (BEM02)', 'asset_description' => 'FORJADO',
        ]));
        $this->assertRedirect('/solicitacoes/' . $original->id);
        $updated = $this->requests()->get($original->id, contain: ['RequestItems', 'RequestAttachments']);
        $this->assertSame('BEM02', $updated->asset_code);
        $this->assertSame('02', $updated->asset_branch);
        $this->assertSame('Motor elétrico', $updated->asset_description);
        $this->assertSame($original->number, $updated->number);
        $this->assertEquals($original->request_items, $updated->request_items);
        $this->assertSame($attachment->id, $updated->request_attachments[0]->id);
        $audit = $tables->get('RequestAudits')->find()->where(['request_id' => $original->id, 'action' => 'REQUEST_UPDATED'])->firstOrFail();
        $this->assertSame(['asset_code' => 'BEM01', 'asset_branch' => '01', 'asset_description' => 'Bomba de água'], $audit->old_values);
        $this->assertSame(['asset_code' => 'BEM02', 'asset_branch' => '02', 'asset_description' => 'Motor elétrico'], $audit->new_values);
        $this->assertSame(1, $audit->user_id);
        $this->assertNotNull($audit->created_at);
    }

    private function linkedAssetCorrection(): array
    {
        $this->session(['Auth' => 1]);
        $this->submit($this->payload());
        $request = $this->requests()->find()->orderBy(['id' => 'DESC'])->firstOrFail();
        $this->requests()->updateAll(['totvs_os_number' => '007404', 'status' => RequestStatus::EM_EXECUCAO->value], ['id' => $request->id]);
        return [$request->id, array_replace($this->payload(), ['asset_code' => 'FAB 80 158', 'asset_branch' => '01',
            'asset_label' => 'Equipamento da fábrica (FAB 80 158)', 'totvs_os_number' => 'FORJADA'])];
    }

    private function mockCorrectionOrder(array $rows): void
    {
        $orders = $this->createMock(\App\Service\OrdersLookupInterface::class);
        $orders->expects($this->once())->method('batch')->with(['007404'])->willReturn($rows);
        $orders->expects($this->never())->method('find');
        $this->mockService(\App\Service\OrdersLookupInterface::class, fn() => $orders);
    }

    public function testAdministrativeLinkedAssetCorrectionMatchesTotvsAndAuditsWithoutChangingOtherData(): void
    {
        [$id, $data] = $this->linkedAssetCorrection();
        $attachments = TableRegistry::getTableLocator()->get('RequestAttachments');
        $attachments->saveOrFail($attachments->newEntity(['request_id' => $id, 'user_id' => 1, 'original_name' => 'original.jpg',
            'stored_name' => 'original.jpg', 'mime_type' => 'image/jpeg', 'size' => 4, 'created_at' => new \Cake\I18n\DateTime()]));
        $before = $this->requests()->get($id, contain: ['RequestItems', 'RequestAttachments'])->toArray();
        $this->mockCorrectionOrder([['number' => '007404', 'branch' => '01', 'asset_code' => 'FAB 80 158']]);
        $this->post('/solicitacoes/' . $id . '/editar', $data);
        $this->assertRedirect('/solicitacoes/' . $id);
        $after = $this->requests()->get($id, contain: ['RequestItems', 'RequestAttachments'])->toArray();
        $this->assertSame('FAB 80 158', $after['asset_code']);
        $this->assertSame('Equipamento da fábrica', $after['asset_description']);
        $this->assertSame('007404', $after['totvs_os_number']);
        $excluded = array_flip(['asset_code', 'asset_branch', 'asset_description', 'updated_at']);
        $this->assertEquals(array_diff_key($before, $excluded), array_diff_key($after, $excluded));
        $audit = TableRegistry::getTableLocator()->get('RequestAudits')->find()->where(['request_id' => $id, 'action' => 'REQUEST_UPDATED'])->firstOrFail();
        $this->assertSame(1, $audit->user_id);
        $this->assertSame('ADMIN_PCM', $audit->actor_snapshot['role']);
        $this->assertNotNull($audit->created_at);
        $this->assertSame('BEM01', $audit->old_values['asset_code']);
        $this->assertSame('FAB 80 158', $audit->new_values['asset_code']);
        $this->assertSame('007404', $audit->old_values['totvs_os_number']);
        $this->assertSame('007404', $audit->new_values['totvs_os_number']);
        $this->assertTrue($audit->new_values['totvs_asset_match']);
        $this->assertEquals(['number' => '007404', 'branch' => '01', 'asset_code' => 'FAB 80 158'], $audit->new_values['totvs_order']);
    }

    public function testAdministrativeLinkedAssetCorrectionBlocksDifferentAsset(): void
    {
        [$id, $data] = $this->linkedAssetCorrection();
        $before = $this->requests()->get($id)->toArray();
        $this->mockCorrectionOrder([['number' => '007404', 'branch' => '01', 'asset_code' => 'OUTRO BEM']]);
        $this->post('/solicitacoes/' . $id . '/editar', $data);
        $this->assertResponseOk();
        $this->assertResponseContains('O Bem selecionado não corresponde ao Bem da O.S. 007404 no TOTVS.');
        $this->assertEquals($before, $this->requests()->get($id)->toArray());
        $this->assertSame(0, TableRegistry::getTableLocator()->get('RequestAudits')->find()->where(['request_id' => $id, 'action' => 'REQUEST_UPDATED'])->count());
    }

    public function testAdministrativeLinkedAssetCorrectionBlocksMissingAmbiguousOrWrongBranchOrder(): void
    {
        [$id, $data] = $this->linkedAssetCorrection();
        $before = $this->requests()->get($id)->toArray();
        $order = ['number' => '007404', 'branch' => '01', 'asset_code' => 'FAB 80 158'];
        foreach ([[], [$order, $order], [array_replace($order, ['branch' => '02'])]] as $rows) {
            $this->mockCorrectionOrder($rows);
            $this->post('/solicitacoes/' . $id . '/editar', $data);
            $this->assertResponseOk();
            $this->assertEquals($before, $this->requests()->get($id)->toArray());
        }
    }

    public function testAdministrativeLinkedAssetCorrectionBlocksUnavailableTotvs(): void
    {
        [$id, $data] = $this->linkedAssetCorrection();
        $before = $this->requests()->get($id)->toArray();
        $orders = $this->createMock(\App\Service\OrdersLookupInterface::class);
        $orders->expects($this->once())->method('batch')->with(['007404'])->willThrowException(new \App\Service\OrdersUnavailableException());
        $this->mockService(\App\Service\OrdersLookupInterface::class, fn() => $orders);
        $this->post('/solicitacoes/' . $id . '/editar', $data);
        $this->assertResponseOk();
        $this->assertResponseContains('Não foi possível consultar a Ordem de Serviço no TOTVS agora.');
        $this->assertEquals($before, $this->requests()->get($id)->toArray());
    }

    public function testAdministrativeLinkedAssetCorrectionRejectsNonAdminBeforeTotvsLookup(): void
    {
        [$id, $data] = $this->linkedAssetCorrection();
        $orders = $this->createMock(\App\Service\OrdersLookupInterface::class);
        $orders->expects($this->never())->method('batch');
        $this->mockService(\App\Service\OrdersLookupInterface::class, fn() => $orders);
        foreach ([2, 3] as $actorId) {
            $this->session(['Auth' => $actorId]);
            $this->post('/solicitacoes/' . $id . '/editar', $data);
            $this->assertResponseCode(403);
        }
        $request = $this->requests()->get($id);
        $this->assertSame('BEM01', $request->asset_code);
        $this->assertSame('007404', $request->totvs_os_number);
    }

    private function submit(array $data): void
    {
        $this->enableCsrfToken('pcm_solicitacao_os_csrf');
        $this->post('/solicitacoes/nova', $data);
    }

    private function requests(): \Cake\ORM\Table
    {
        return TableRegistry::getTableLocator()->get('ServiceRequests');
    }

    public function testAnonymousCannotAccessRequestsOrLookups(): void
    {
        foreach (['/solicitacoes/nova', '/minhas-solicitacoes', '/solicitacoes/1', '/api/bens?q=bo', '/api/setores?q=ma'] as $url) {
            $this->get($url);
            $this->assertRedirect('/entrar');
        }
    }

    public function testAllProfilesCanCreateRequests(): void
    {
        foreach ([1, 2, 3] as $id) {
            $this->session(['Auth' => $id]);
            $this->get('/solicitacoes/nova');
            $this->assertResponseOk();
            $this->assertResponseContains('Enviar solicitação');
            $this->submit($this->payload());
            $this->assertResponseCode(302);
        }
        $this->assertSame(5, $this->requests()->find()->count());
    }

    public function testCreatesOwnRequestWithVerifiedSnapshotAndItems(): void
    {
        $this->session(['Auth' => 3]);
        $data = $this->payload() + ['user_id' => 1, 'status' => RequestStatus::OS_ABERTA->value, 'number' => 'FORJADO', 'asset_description' => 'FORJADO', 'totvs_os_number' => '999'];
        $this->submit($data);
        $this->assertResponseCode(302);
        $request = $this->requests()->find()->orderBy(['id' => 'DESC'])->contain(['RequestItems', 'RequestAudits'])->firstOrFail();
        $this->assertSame(3, $request->user_id);
        $this->assertSame(RequestStatus::AGUARDANDO_PCM->value, $request->status);
        $this->assertSame('Bomba de água', $request->asset_description);
        $this->assertSame(1, $request->service_action_id);
        $this->assertSame('Limpar', $request->service_action_name);
        $this->assertSame('Limpeza geral', $request->description);
        $this->assertNull($request->totvs_os_number);
        $this->assertMatchesRegularExpression('/^SOL-[0-9]{4}-[0-9]{6,}$/', $request->number);
        $this->assertCount(2, $request->request_items);
        $this->assertSame([1, 2], array_column($request->request_items, 'position'));
        $this->assertCount(2, $request->request_audits);
        $this->assertSame('REQUEST_CREATED', $request->request_audits[0]->action);
        $this->assertCount(2, $request->request_audits[0]->new_values['items']);
    }

    public function testServiceSelectorsShowActiveCatalogAndRequireManualDescriptionForOther(): void
    {
        $this->session(['Auth' => 3]);
        $this->get('/solicitacoes/nova');
        $this->assertResponseOk();
        $this->assertResponseContains('Selecione a ação');
        $this->assertResponseContains('Limpar');
        $this->assertResponseNotContains('>Outro</option>');
        $this->assertResponseContains('data-service-selection');
        $this->assertResponseContains('service-selection.js');
    }

    public function testOtherServiceIsSavedAsManualSnapshotAndInactiveCatalogChoicesAreRejected(): void
    {
        $this->session(['Auth' => 3]);
        $data = array_replace($this->payload(), [
            'service_action_id' => 1, 'service_component_id' => 1,
            'items' => [],
        ]);
        $this->submit($data);
        $this->assertResponseCode(302);
        $request = $this->requests()->find()->orderBy(['id' => 'DESC'])->firstOrFail();
        $this->assertSame(1, $request->service_action_id);
        $this->assertSame('Limpar', $request->service_action_name);
        $this->assertSame('Limpeza geral', $request->description);

        TableRegistry::getTableLocator()->get('ServiceActions')->updateAll(['active' => false], ['id' => 1]);
        $this->assertSame('Limpeza geral', $this->requests()->get($request->id)->description);
        $this->get('/solicitacoes/nova');
        $this->assertResponseOk();
        $this->assertResponseNotContains('<option value="1">Limpar</option>');
        $this->submit($this->payload());
        $this->assertResponseOk();
        $this->assertResponseContains('Selecione uma opção ativa de ação');
        $this->assertSame(3, $this->requests()->find()->count());
    }

    public function testAppointmentAcceptsFreeTextWithoutServiceCatalogSelection(): void
    {
        $this->session(['Auth' => 3]);
        $data = $this->payload();
        $data['items'][0]['description'] = 'Solda reforçada na chapa, com acabamento manual.';
        $data['items'][0]['service_action_id'] = 999;
        $this->submit($data);
        $this->assertResponseCode(302);
        $item = TableRegistry::getTableLocator()->get('RequestItems')->find()->firstOrFail();
        $this->assertNull($item->service_action_id);
        $this->assertNull($item->service_component_id);
        $this->assertSame('Solda reforçada na chapa, com acabamento manual.', $item->description);
    }

    public function testInvalidAssetAndWrongBranchAreRejected(): void
    {
        $this->session(['Auth' => 3]);
        foreach ([['asset_code' => 'INVALIDO'], ['asset_branch' => '99']] as $change) {
            $this->submit(array_replace($this->payload(), $change));
            $this->assertResponseOk();
            $this->assertResponseContains('Selecione um Bem/Equipamento válido da lista.');
        }
        $this->assertSame(2, $this->requests()->find()->count());
    }

    public function testInactiveAssetIsRejectedForCreationButSavedRequestRemainsVisible(): void
    {
        $fake = $this->createMock(AssetsLookupInterface::class);
        $fake->expects($this->once())->method('search')->with('Bomba')->willReturn([]);
        // O filtro real de situação torna o Bem salvo indisponível para novas seleções.
        $fake->expects($this->once())->method('find')->with('BEM01', '01')->willReturn(null);
        $this->mockService(AssetsLookupInterface::class, fn() => $fake);
        $this->session(['Auth' => 3]);
        $this->get('/api/bens?q=Bomba'); $this->assertResponseOk();
        $this->assertSame([], json_decode((string)$this->_response->getBody(), true)['results']);
        $this->submit($this->payload()); $this->assertResponseOk();
        $this->assertResponseContains('Selecione um Bem/Equipamento válido da lista.');
        $this->assertSame(2, $this->requests()->find()->count());
        $this->get('/solicitacoes/1'); $this->assertResponseOk();
        $this->assertResponseContains('BEM01');
        $this->assertResponseContains($this->requests()->get(1)->asset_description);
    }

    public function testFreeTextCannotReplaceSelections(): void
    {
        $this->session(['Auth' => 3]);
        $this->submit(array_replace($this->payload(), ['asset_code' => '', 'sector_id' => '', 'asset_label' => 'Equipamento inventado', 'sector_label' => 'Setor inventado']));
        $this->assertResponseOk();
        $this->assertSame(2, $this->requests()->find()->count());
    }

    public function testManualEditAfterAssetSelectionCannotSave(): void
    {
        $this->session(['Auth' => 3]);
        foreach (['Texto livre', '', null] as $label) {
            $this->submit(array_replace($this->payload(), ['asset_label' => $label]));
            $this->assertResponseOk();
            $this->assertResponseContains('Selecione um Bem/Equipamento válido da lista.');
            $this->assertSame(2, $this->requests()->find()->count());
        }
    }

    public function testInactiveMaintenanceTypeIsHiddenAndRejectedWithoutChangingHistory(): void
    {
        $types = TableRegistry::getTableLocator()->get('MaintenanceTypes');
        $types->updateAll(['active' => false], ['code' => 'CORRETIVA']);
        $this->session(['Auth' => 3]);
        $this->get('/solicitacoes/nova');
        $this->assertResponseOk();
        $this->assertResponseNotContains('<option value="CORRETIVA">');
        $this->submit($this->payload());
        $this->assertResponseOk();
        $this->assertResponseContains('Selecione um tipo de manutenção ativo.');
        $this->assertSame(2, $this->requests()->find()->count());
        $this->get('/solicitacoes/1');
        $this->assertResponseOk();
        $this->assertResponseContains('Corretiva');
    }

    public function testNewMaintenanceTypeCanBeSelectedAndSaved(): void
    {
        $types = TableRegistry::getTableLocator()->get('MaintenanceTypes');
        $types->getConnection()->execute("SELECT setval(pg_get_serial_sequence('maintenance_types', 'id'), (SELECT MAX(id) FROM maintenance_types))");
        $type = $types->newEntity(['name' => 'Inspeção especial', 'active' => true]);
        $types->saveOrFail($type);
        $this->session(['Auth' => 3]);
        $this->get('/solicitacoes/nova');
        $this->assertResponseContains('Inspeção especial');
        $this->submit(array_replace($this->payload(), ['maintenance_type' => $type->code]));
        $this->assertResponseCode(302);
        $saved = $this->requests()->find()->orderByDesc('id')->firstOrFail();
        $this->assertSame($type->code, $saved->maintenance_type);
        $this->assertSame('BEM01', $saved->asset_code);
        $this->assertSame('01', $saved->asset_branch);
        $this->assertSame('Bomba de água', $saved->asset_description);
        $this->get('/solicitacoes/' . $saved->id);
        $this->assertResponseOk();
        $this->assertResponseContains('Inspeção especial');
    }

    public function testInactiveOrUnknownSectorIsRejected(): void
    {
        $this->session(['Auth' => 3]);
        $sectors = TableRegistry::getTableLocator()->get('Sectors');
        $sectors->updateAll(['active' => false], ['id' => 1]);
        foreach ([1, 99999] as $sectorId) {
            $this->submit(array_replace($this->payload(), ['sector_id' => $sectorId]));
            $this->assertResponseOk();
            $this->assertResponseContains('Selecione uma mão de obra ativa');
        }
        $this->assertSame(2, $this->requests()->find()->count());
    }

    public function testInvalidItemsTypeAndBlankDescriptionAreRejected(): void
    {
        $this->session(['Auth' => 3]);
        foreach ([['items' => 'inválido'], ['items' => [['description' => '', 'employee_registration' => 'José']]], ['maintenance_type' => 'INVALID'], ['service_action_id' => ''], ['service_component_id' => ''], ['items' => array_fill(0, 51, ['description' => 'Serviço'])]] as $change) {
            $this->submit(array_replace($this->payload(), $change));
            $this->assertResponseOk();
        }
        $this->assertSame(2, $this->requests()->find()->count());
    }

    public function testUnavailableTotvsDoesNotSaveAndKeepsForm(): void
    {
        $fake = $this->createMock(AssetsLookupInterface::class);
        $fake->method('find')->willThrowException(new AssetsUnavailableException());
        $this->mockService(AssetsLookupInterface::class, fn() => $fake);
        $this->session(['Auth' => 3]);
        $this->submit($this->payload());
        $this->assertResponseOk();
        $this->assertResponseContains('Não foi possível consultar os equipamentos');
        $this->assertResponseContains('Limpar');
        $this->assertSame(2, $this->requests()->find()->count());
    }

    public function testMyRequestsOnlyShowsOwnRecords(): void
    {
        $this->session(['Auth' => 3]);
        $this->get('/minhas-solicitacoes');
        $this->assertResponseOk();
        $this->assertResponseContains('SOL-2026-000001');
        $this->assertResponseNotContains('SOL-2026-000002');
    }

    public function testRequesterCannotReadSomeoneElsesRequest(): void
    {
        $this->session(['Auth' => 3]);
        $this->get('/solicitacoes/2');
        $this->assertResponseCode(404);
    }

    public function testManagerAndAdminCanReadRequestDetails(): void
    {
        foreach ([1, 2] as $id) {
            $this->session(['Auth' => $id]);
            $this->get('/solicitacoes/1');
            $this->assertResponseOk();
            $this->assertResponseContains('Aguardando PCM');
        }
    }

    public function testSectorLookupOnlyReturnsActiveRecords(): void
    {
        $this->session(['Auth' => 3]);
        $this->get('/api/setores?q=ma');
        $this->assertResponseOk();
        $rows = json_decode((string)$this->_response->getBody(), true)['results'];
        $this->assertCount(1, $rows);
        TableRegistry::getTableLocator()->get('Sectors')->updateAll(['active' => false], ['id' => 1]);
        $this->get('/api/setores?q=ma');
        $this->assertResponseContains('"results":[]');
    }

    public function testAssetLookupUsesFakeAndReturnsJson(): void
    {
        $this->session(['Auth' => 3]);
        $this->get('/api/bens?q=bo');
        $this->assertResponseOk();
        $this->assertContentType('application/json');
        $this->assertResponseContains('BEM01');
    }

    public function testAssetLookupUnavailableReturnsSafe503(): void
    {
        $fake = $this->createMock(AssetsLookupInterface::class);
        $fake->method('search')->willThrowException(new AssetsUnavailableException());
        $this->mockService(AssetsLookupInterface::class, fn() => $fake);
        $this->session(['Auth' => 3]);
        $this->get('/api/bens?q=bo');
        $this->assertResponseCode(503);
        $this->assertResponseNotContains('PROTHEUS_DB_PASSWORD');
    }

    public function testCsrfIsRequiredToCreateRequest(): void
    {
        $this->session(['Auth' => 3]);
        $this->post('/solicitacoes/nova', $this->payload());
        $this->assertResponseCode(403);
        $this->assertSame(2, $this->requests()->find()->count());
    }

    public function testUnitIsRequiredAndLimitedToValidChoices(): void
    {
        $this->session(['Auth' => 3]);
        foreach (['', 'OUTRA'] as $unit) {
            $this->submit(array_replace($this->payload(), ['unit' => $unit]));
            $this->assertResponseOk();
            $this->assertSame(2, $this->requests()->find()->count());
        }
        $data = $this->payload();
        unset($data['unit']);
        $this->submit($data);
        $this->assertResponseOk();
        $this->assertSame(2, $this->requests()->find()->count());
    }

    public function testFactoryUnitIsSavedAndAudited(): void
    {
        $this->session(['Auth' => 3]);
        $this->submit(array_replace($this->payload(), ['unit' => 'FABRICA']));
        $this->assertResponseCode(302);
        $request = $this->requests()->find()->orderBy(['id' => 'DESC'])->contain(['RequestAudits'])->firstOrFail();
        $this->assertSame('FABRICA', $request->unit);
        $this->assertSame('01', $request->asset_branch);
        $this->assertSame('FABRICA', $request->request_audits[0]->new_values['unit']);
        $this->get('/solicitacoes/' . $request->id);
        $this->assertResponseContains('Fábrica');
    }

    public function testUnitFiltersAndLegacyRecordsRemainUsable(): void
    {
        $this->session(['Auth' => 3]);
        $this->get('/solicitacoes/1');
        $this->assertResponseOk();
        $this->assertResponseContains('Não informada');
        $this->requests()->updateAll(['unit' => 'USINA'], ['id' => 1]);
        $service = new \App\Service\RequestReportService();
        $this->assertSame(1, $service->query(['unit' => 'USINA'])->count());
        $this->assertSame(1, $service->query(['unit' => 'UNSPECIFIED'])->count());
        $this->session(['Auth' => 1]);
        foreach (['/relatorios?unit=USINA'] as $url) {
            $this->get($url);
            $this->assertResponseOk();
            $this->assertResponseContains('SOL-2026-000001');
            $this->assertResponseNotContains('SOL-2026-000002');
            $this->assertResponseContains('/img/icon.png');
        }
        $this->get('/relatorios?unit=INVALID');
        $this->assertResponseContains('Selecione uma unidade válida');
    }

    public function testAuditFailureRollsBackRequestAndItems(): void
    {
        $audits = TableRegistry::getTableLocator()->get('RequestAudits');
        $audits->getValidator()->add('action', 'reject', ['rule' => fn() => false]);
        $fake = $this->createMock(AssetsLookupInterface::class);
        $fake->method('find')->willReturn(['code' => 'BEM01', 'branch' => '01', 'description' => 'Bomba de água']);
        try {
            (new \App\Service\RequestCreationService($fake, new \App\Test\Support\FakeEmployees()))->create($this->payload(), 3);
            $this->fail('A falha de auditoria deve impedir o envio.');
        } catch (\Cake\ORM\Exception\PersistenceFailedException $e) {
            $this->assertTrue($e->getEntity()->hasErrors());
        }
        $this->assertSame(2, $this->requests()->find()->count());
        $this->assertSame(0, TableRegistry::getTableLocator()->get('RequestItems')->find()->count());
        $this->assertSame(0, $audits->find()->count());
    }

    public function testExecutionDetailsOnDifferentDaysAreSavedAndShown(): void
    {
        $this->session(['Auth' => 3]);
        $this->submit($this->payload());
        $this->assertResponseCode(302);
        $request = $this->requests()->find()->orderBy(['id' => 'DESC'])->contain(['RequestItems', 'RequestAudits'])->firstOrFail();
        $this->assertSame('José externo', $request->request_items[0]->executor);
        $this->assertSame('2026-10-06 11:00', $request->request_items[0]->started_at->setTimezone('UTC')->format('Y-m-d H:i'));
        $this->assertSame('2026-10-07 13:00', $request->request_items[1]->started_at->setTimezone('UTC')->format('Y-m-d H:i'));
        $this->assertSame('José externo', $request->request_audits[0]->new_values['items'][0]['executor']);
        $this->get('/solicitacoes/' . $request->id);
        $this->assertResponseOk();
        $this->assertResponseContains('06/10/2026');
        $this->assertResponseContains('07/10/2026');
        $this->assertResponseContains('José externo');
        $this->assertResponseContains('Maria externa');
    }

    public function testRequiredExecutionFieldsAndTimeValidation(): void
    {
        $this->session(['Auth' => 3]);
        foreach ([['employee_registration' => ' '], ['service_date' => '2026-02-30'], ['start_time' => '25:00'], ['end_time' => '07:59']] as $change) {
            $data = $this->payload();
            $data['items'][0] = array_replace($data['items'][0], $change);
            $this->submit($data);
            $this->assertResponseOk();
            $this->assertSame(2, $this->requests()->find()->count());
        }
        foreach (['employee_registration', 'service_date', 'start_time', 'end_time'] as $field) {
            $data = $this->payload();
            unset($data['items'][0][$field]);
            $this->submit($data);
            $this->assertResponseOk();
            $this->assertSame(2, $this->requests()->find()->count());
        }
    }

    public function testExistingItemWithoutExecutionDetailsIsStillReadable(): void
    {
        $items = TableRegistry::getTableLocator()->get('RequestItems');
        $item = $items->newEmptyEntity();
        $item->patch(['request_id' => 1, 'description' => 'Serviço anterior', 'position' => 1, 'created_at' => new \Cake\I18n\DateTime('now', 'UTC')]);
        $items->saveOrFail($item);
        $this->session(['Auth' => 3]);
        $this->get('/solicitacoes/1');
        $this->assertResponseOk();
        $this->assertResponseContains('Serviço anterior');
        $this->assertResponseContains('Não informada');
    }

    public function testPcmActionsAndSingleHomeNavigation(): void
    {
        $this->session(['Auth' => 1]);
        $this->get('/solicitacoes/1');
        $this->assertResponseOk();
        $this->assertResponseContains('Ações do PCM');
        $this->assertResponseNotContains('Assumir solicitação');
        $this->assertResponseNotContains('Tratar solicitação');
        $this->assertResponseNotContains('Fila de O.S.');
        $this->assertResponseContains('Início');
        $this->requests()->updateAll(['status' => RequestStatus::EM_ANALISE->value], ['id' => 1]);
        $this->get('/solicitacoes/1');
        $this->assertResponseNotContains('Assumir solicitação');
        $this->assertResponseContains('Vincular O.S. TOTVS');
        $this->session(['Auth' => 2]);
        $this->get('/solicitacoes/1');
        $this->assertResponseContains('Ações do PCM');
    }

    public function testTouchFormHasOperationalFieldOrderAndAutomaticRequester(): void
    {
        $this->session(['Auth' => 3]); $this->get('/solicitacoes/nova'); $this->assertResponseOk();
        $body = (string)$this->_response->getBody(); $previous = -1;
        foreach (['name="unit"', 'name="asset_label"', 'name="sector_id"', 'name="maintenance_type"',
            'name="service_action_id"', 'name="os_disposition"', 'id="execution-initial"', 'id="photos"', '>Enviar solicitação<'] as $field) {
            $position = strpos($body, $field);
            $this->assertNotFalse($position, $field);
            $this->assertGreaterThan($previous, $position, $field); $previous = $position;
        }
        $this->assertResponseContains('Solicitante:'); $this->assertResponseContains('Usuário 3');
        $this->assertResponseNotContains('name="user_id"');
        $this->assertResponseContains('class="os-option"');
        $this->assertResponseNotContains('header-request-shortcut');
        $this->assertResponseNotContains('user-scalable=no');
    }

    public function testSuccessfulSubmissionShowsPersistentClearConfirmationAndNextActions(): void
    {
        $this->session(['Auth' => 3]); $this->submit($this->payload()); $this->assertResponseCode(302);
        $this->assertFlashMessage('Solicitação enviada ao PCM.');
        $this->assertFlashElement('flash/request_created');
        $this->session(['Flash' => $_SESSION['Flash']]);
        $request = $this->requests()->find()->orderBy(['id' => 'DESC'])->firstOrFail();
        $this->get('/solicitacoes/' . $request->id); $this->assertResponseOk();
        $this->assertResponseContains('Solicitação enviada ao PCM');
        $this->assertResponseContains('Seu pedido <strong>' . $request->number . '</strong> foi registrado com sucesso.');
        $this->assertResponseContains('Ver solicitação'); $this->assertResponseContains('Criar outra solicitação');
        $this->assertResponseContains('role="status"');
        $this->assertResponseNotContains('onclick="this.classList.add');
    }
}
