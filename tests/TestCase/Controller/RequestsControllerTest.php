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
        'app.Users', 'app.Sectors', 'app.RegistrationAudits',
        'app.ServiceRequests', 'app.RequestItems', 'app.RequestAudits',
    ];

    public function setUp(): void
    {
        parent::setUp();
        $this->mockService(AssetsLookupInterface::class, fn() => new class implements AssetsLookupInterface {
            public function search(string $term): array
            {
                return mb_strlen(trim($term)) < 2 ? [] : [['code' => 'BEM01', 'branch' => '01', 'description' => 'Bomba de água']];
            }

            public function find(string $code, string $branch): ?array
            {
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
            'unit' => 'USINA', 'asset_code' => 'BEM01', 'asset_branch' => '01', 'asset_label' => 'Bomba de água',
            'sector_id' => 1, 'sector_label' => 'Manutenção',
            'maintenance_type' => MaintenanceType::CORRETIVA->value,
            'description' => 'Vazamento na bomba',
            'items' => [['description' => 'Trocar vedação', 'executor' => 'José externo', 'service_date' => '2026-10-06', 'start_time' => '08:00', 'end_time' => '09:00'], ['description' => 'Verificar tubulação', 'executor' => 'Maria externa', 'service_date' => '2026-10-07', 'start_time' => '10:00', 'end_time' => '11:30']],
        ];
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
        $this->assertNull($request->totvs_os_number);
        $this->assertMatchesRegularExpression('/^SOL-[0-9]{4}-[0-9]{6,}$/', $request->number);
        $this->assertCount(2, $request->request_items);
        $this->assertSame([1, 2], array_column($request->request_items, 'position'));
        $this->assertCount(2, $request->request_audits);
        $this->assertSame('REQUEST_CREATED', $request->request_audits[0]->action);
        $this->assertCount(2, $request->request_audits[0]->new_values['items']);
    }

    public function testInvalidAssetAndWrongBranchAreRejected(): void
    {
        $this->session(['Auth' => 3]);
        foreach ([['asset_code' => 'INVALIDO'], ['asset_branch' => '99']] as $change) {
            $this->submit(array_replace($this->payload(), $change));
            $this->assertResponseOk();
            $this->assertResponseContains('Este equipamento não foi encontrado');
        }
        $this->assertSame(2, $this->requests()->find()->count());
    }

    public function testFreeTextCannotReplaceSelections(): void
    {
        $this->session(['Auth' => 3]);
        $this->submit(array_replace($this->payload(), ['asset_code' => '', 'sector_id' => '', 'asset_label' => 'Equipamento inventado', 'sector_label' => 'Setor inventado']));
        $this->assertResponseOk();
        $this->assertSame(2, $this->requests()->find()->count());
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
        foreach ([['items' => 'inválido'], ['items' => [['description' => ' ', 'executor' => 'José']]], ['maintenance_type' => 'INVALID'], ['description' => ' '], ['items' => array_fill(0, 51, ['description' => 'Item'])]] as $change) {
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
        $this->assertResponseContains('Vazamento na bomba');
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
        foreach (['/painel?unit=USINA', '/relatorios?unit=USINA'] as $url) {
            $this->get($url);
            $this->assertResponseOk();
            $this->assertResponseContains('SOL-2026-000001');
            $this->assertResponseNotContains('SOL-2026-000002');
            $this->assertResponseContains('/img/icon.png');
        }
        $this->get('/painel?unit=INVALID');
        $this->assertResponseContains('Selecione uma unidade válida');
    }

    public function testAuditFailureRollsBackRequestAndItems(): void
    {
        $audits = TableRegistry::getTableLocator()->get('RequestAudits');
        $audits->getValidator()->add('action', 'reject', ['rule' => fn() => false]);
        $fake = $this->createMock(AssetsLookupInterface::class);
        $fake->method('find')->willReturn(['code' => 'BEM01', 'branch' => '01', 'description' => 'Bomba de água']);
        try {
            (new \App\Service\RequestCreationService($fake))->create($this->payload(), 3);
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
        $this->assertResponseContains('06/10/2026 08:00');
        $this->assertResponseContains('07/10/2026 10:00');
        $this->assertResponseContains('José externo');
        $this->assertResponseContains('Maria externa');
    }

    public function testRequiredExecutionFieldsAndTimeValidation(): void
    {
        $this->session(['Auth' => 3]);
        foreach ([['executor' => ' '], ['service_date' => '2026-02-30'], ['start_time' => '25:00'], ['end_time' => '07:59']] as $change) {
            $data = $this->payload();
            $data['items'][0] = array_replace($data['items'][0], $change);
            $this->submit($data);
            $this->assertResponseOk();
            $this->assertSame(2, $this->requests()->find()->count());
        }
        foreach (['executor', 'service_date', 'start_time', 'end_time'] as $field) {
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
        $this->assertResponseContains('Não informados (registro anterior)');
    }

    public function testPcmActionsAndSingleHomeNavigation(): void
    {
        $this->session(['Auth' => 1]);
        $this->get('/solicitacoes/1');
        $this->assertResponseOk();
        $this->assertResponseContains('Ações do PCM');
        $this->assertResponseContains('Assumir solicitação');
        $this->assertResponseNotContains('Tratar solicitação');
        $this->assertResponseNotContains('Fila de O.S.');
        $this->assertResponseContains('Início');
        $this->requests()->updateAll(['status' => RequestStatus::EM_ANALISE->value], ['id' => 1]);
        $this->get('/solicitacoes/1');
        $this->assertResponseNotContains('Assumir solicitação');
        $this->assertResponseContains('Vincular O.S. TOTVS');
        $this->session(['Auth' => 2]);
        $this->get('/solicitacoes/1');
        $this->assertResponseNotContains('Ações do PCM');
    }

    public function testTouchFormHasOperationalFieldOrderAndAutomaticRequester(): void
    {
        $this->session(['Auth' => 3]); $this->get('/solicitacoes/nova'); $this->assertResponseOk();
        $body = (string)$this->_response->getBody(); $previous = -1;
        foreach (['name="unit"', 'name="asset_label"', 'name="sector_label"', 'name="maintenance_type"',
            'name="description"', 'name="os_disposition"', 'id="execution-initial"', 'id="photos"', '>Enviar solicitação<'] as $field) {
            $position = strpos($body, $field);
            $this->assertNotFalse($position, $field);
            $this->assertGreaterThan($previous, $position, $field); $previous = $position;
        }
        $this->assertResponseContains('Solicitante:'); $this->assertResponseContains('Usuário 3');
        $this->assertResponseNotContains('name="user_id"');
        $this->assertResponseContains('class="os-option"');
        $this->assertResponseContains('header-request-shortcut');
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
