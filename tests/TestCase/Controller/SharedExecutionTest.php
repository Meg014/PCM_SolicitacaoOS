<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Domain\Enum\RequestFlow;
use App\Domain\Enum\RequestStatus;
use App\Domain\Enum\UserRole;
use App\Service\AssetsLookupInterface;
use App\Service\RequestCreationService;
use App\Service\RequestExecutionService;
use App\Service\RequestHandlingService;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class SharedExecutionTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Sectors', 'app.RegistrationAudits', 'app.ServiceRequests', 'app.RequestItems', 'app.RequestAudits', 'app.RequestAttachments'];

    public function setUp(): void
    {
        parent::setUp();
        $this->mockService(AssetsLookupInterface::class, fn() => $this->assets());
        \Cake\Datasource\ConnectionManager::get('test')->execute("SELECT setval(pg_get_serial_sequence('service_requests', 'id'), (SELECT MAX(id) FROM service_requests))");
    }

    private function assets(): AssetsLookupInterface
    {
        $fake = $this->createMock(AssetsLookupInterface::class);
        $fake->method('find')->willReturnCallback(fn($code, $branch) => $code === 'BEM02' && $branch === '01'
            ? ['code' => $code, 'branch' => $branch, 'description' => 'Centrífuga'] : null);
        return $fake;
    }

    private function data(string $flow = 'ACOMPANHAMENTO'): array
    {
        return ['os_disposition' => $flow === 'SOMENTE_ABERTURA' ? 'SOMENTE_ABERTURA' : 'MANTER_ABERTA', 'flow' => $flow, 'unit' => 'USINA', 'asset_code' => 'BEM02', 'asset_branch' => '01',
            'sector_id' => 1, 'maintenance_type' => 'CORRETIVA', 'description' => 'Motor com vibração', 'items' => []];
    }

    private function request(): mixed { return TableRegistry::getTableLocator()->get('ServiceRequests')->get(1); }

    private function prepareTracking(): void
    {
        TableRegistry::getTableLocator()->get('ServiceRequests')->updateAll(['flow' => RequestFlow::ACOMPANHAMENTO->value], ['id' => 1]);
        (new RequestHandlingService())->handle(1, 1, 'link', '004564');
    }

    private function item(string $executor = 'Carlos', string $date = '2026-10-07'): array
    {
        return ['executor' => $executor, 'service_date' => $date, 'start_time' => '08:00', 'end_time' => '09:30', 'description' => 'Revisão do motor'];
    }

    private function postAction(string $action, array $data = []): void
    {
        $this->enableCsrfToken('pcm_solicitacao_os_csrf'); $this->post('/solicitacoes/1/' . $action, $data);
    }

    public function testBothFlowsCanStartWithoutItemsAndDispositionIsMandatory(): void
    {
        foreach (RequestFlow::cases() as $flow) {
            $request = (new RequestCreationService($this->assets()))->create($this->data($flow->value) + ['confirm_existing' => '1'], 3);
            $this->assertSame($flow->value, $request->flow);
            $this->assertSame(RequestStatus::AGUARDANDO_PCM->value, $request->status);
            $this->assertSame(0, TableRegistry::getTableLocator()->get('RequestItems')->find()->where(['request_id' => $request->id])->count());
        }
        $this->session(['Auth' => 3]);
        $data = $this->data(); unset($data['os_disposition']);
        $this->enableCsrfToken('pcm_solicitacao_os_csrf'); $this->post('/solicitacoes/nova', $data);
        $this->assertResponseOk(); $this->assertResponseContains('os_disposition');
        $this->assertSame(4, TableRegistry::getTableLocator()->get('ServiceRequests')->find()->count());
    }

    public function testOpeningOnlyIsAttendedAndCannotTrackOrClose(): void
    {
        (new RequestHandlingService())->handle(1, 1, 'link', '000123');
        $this->assertSame(RequestStatus::ATENDIDA->value, $this->request()->status);
        $this->session(['Auth' => 3]);
        $this->postAction('apontamentos', ['items' => [$this->item()]]);
        $this->assertSame(0, TableRegistry::getTableLocator()->get('RequestItems')->find()->count());
        $this->session(['Auth' => 1]); $this->postAction('confirmar-encerramento', ['confirmed_closed' => '1']);
        $this->assertSame(RequestStatus::ATENDIDA->value, $this->request()->status);
        $this->get('/solicitacoes/1'); $this->assertResponseContains('Isso não significa');
        $this->assertResponseNotContains('Continuar apontando nesta O.S.');
    }

    public function testSharedExecutionAcrossPeopleDaysAndCompleteClosure(): void
    {
        $this->prepareTracking();
        $this->assertSame(RequestStatus::OS_ABERTA->value, $this->request()->status);
        $this->session(['Auth' => 2]);
        $this->postAction('apontamentos', ['items' => [$this->item('Carlos', '2026-10-07'), $this->item('Marcos', '2026-10-08')]]);
        $this->assertRedirect('/solicitacoes/1');
        $this->assertSame(RequestStatus::EM_EXECUCAO->value, $this->request()->status);
        $this->session(['Auth' => 3]); $this->postAction('em-andamento');
        $this->postAction('apontamentos', ['items' => [$this->item('Ana', '2026-10-09')]]);
        $items = TableRegistry::getTableLocator()->get('RequestItems')->find()->orderBy(['position' => 'ASC'])->toArray();
        $this->assertSame(['Carlos', 'Marcos', 'Ana'], array_column($items, 'executor'));
        $this->assertSame([2, 2, 3], array_column($items, 'recorded_by'));
        $this->assertSame([1, 2, 3], array_column($items, 'position'));
        $this->assertSame('2026-10-08 11:00', $items[1]->started_at->format('Y-m-d H:i'));
        $this->postAction('concluir-servico');
        $this->assertSame(RequestStatus::AGUARDANDO_ENCERRAMENTO->value, $this->request()->status);
        $this->assertSame(3, $this->request()->completed_by); $this->assertNotNull($this->request()->completed_at);
        $this->postAction('apontamentos', ['items' => [$this->item()]]);
        $this->assertSame(3, TableRegistry::getTableLocator()->get('RequestItems')->find()->count());
        $this->session(['Auth' => 1]); $this->get('/solicitacoes/1');
        $this->assertResponseContains('Confirmar encerramento no TOTVS');
        $this->postAction('confirmar-encerramento');
        $this->assertSame(RequestStatus::AGUARDANDO_ENCERRAMENTO->value, $this->request()->status);
        $this->postAction('confirmar-encerramento', ['confirmed_closed' => '1']);
        $this->assertSame(RequestStatus::ENCERRADA->value, $this->request()->status);
        $this->assertSame(1, $this->request()->closed_by); $this->assertNotNull($this->request()->closed_at);
        $this->assertSame(3, $this->request()->user_id);
        $audits = TableRegistry::getTableLocator()->get('RequestAudits')->find()->where(['action' => 'STATUS_CHANGED'])->orderBy(['id' => 'ASC'])->toArray();
        $this->assertSame(['OS_ABERTA', 'EM_EXECUCAO', 'AGUARDANDO_ENCERRAMENTO', 'ENCERRADA'], array_map(fn($a) => $a->new_values['status'], $audits));
        $this->assertSame([1, 2, 3, 1], array_column($audits, 'user_id'));
        $this->get('/solicitacoes/1'); $this->assertResponseOk();
        $this->assertResponseContains('Marcos'); $this->assertResponseContains('08/10/2026 08:00');
        $this->assertResponseContains('PCM confirmou o encerramento');
    }

    public function testRequesterCanContinueAnotherPersonsTrackingButNotOpeningOnly(): void
    {
        TableRegistry::getTableLocator()->get('ServiceRequests')->updateAll(['user_id' => 2], ['id' => 1]);
        $this->session(['Auth' => 3]); $this->get('/solicitacoes/1'); $this->assertResponseCode(404);
        $this->prepareTracking(); $this->get('/solicitacoes/1'); $this->assertResponseOk();
        $this->postAction('apontamentos', ['items' => [$this->item()]]);
        $this->assertSame(2, $this->request()->user_id);
        $this->assertSame(3, TableRegistry::getTableLocator()->get('RequestItems')->find()->first()->recorded_by);
        $this->get('/acompanhamento'); $this->assertResponseContains('SOL-2026-000001');
        $this->get('/minhas-solicitacoes'); $this->assertResponseNotContains('SOL-2026-000001');
    }

    public function testAllOperationalRolesCanCreateEitherFlowAndCannotPerformPcmActions(): void
    {
        $users = TableRegistry::getTableLocator()->get('Users');
        foreach ([UserRole::SOLICITANTE, UserRole::MANTENEDOR, UserRole::ENCARREGADO, UserRole::GESTOR] as $role) {
            $users->updateAll(['role' => $role->value], ['id' => 2]);
            $this->session(['Auth' => 2]);
            foreach (RequestFlow::cases() as $flow) {
                $this->enableCsrfToken('pcm_solicitacao_os_csrf'); $this->post('/solicitacoes/nova', $this->data($flow->value) + ['confirm_existing' => '1']);
                $this->assertResponseCode(302);
            }
            foreach (['assumir', 'vincular-os', 'cancelar', 'confirmar-encerramento'] as $action) {
                $this->postAction($action, ['totvs_os_number' => '123', 'reason' => 'Teste', 'confirmed_closed' => '1']);
                $this->assertResponseCode(403);
            }
        }
    }

    public function testNewProfilesCanContinueSharedExecutionAndHaveDifferentDashboardAccess(): void
    {
        $this->prepareTracking();
        foreach ([UserRole::MANTENEDOR, UserRole::ENCARREGADO] as $role) {
            TableRegistry::getTableLocator()->get('Users')->updateAll(['role' => $role->value], ['id' => 2]);
            $this->session(['Auth' => 2]); $this->get('/solicitacoes/1'); $this->assertResponseOk();
            $this->postAction('apontamentos', ['items' => [$this->item($role->label())]]); $this->assertResponseCode(302);
            $this->get('/painel'); $this->assertResponseCode($role->hasDashboard() ? 200 : 403);
            $this->get('/usuarios'); $this->assertResponseCode(403);
            $this->get('/relatorios'); $this->assertResponseCode(403);
        }
        $this->postAction('concluir-servico');
        $this->assertSame(RequestStatus::AGUARDANDO_ENCERRAMENTO->value, $this->request()->status);
    }

    public function testInitialItemsMoveLinkedTrackingDirectlyToOngoing(): void
    {
        $data = $this->data(); $data['items'] = [$this->item()];
        $request = (new RequestCreationService($this->assets()))->create($data, 3);
        (new RequestHandlingService())->handle($request->id, 1, 'link', '456');
        $this->assertSame(RequestStatus::EM_EXECUCAO->value, TableRegistry::getTableLocator()->get('ServiceRequests')->get($request->id)->status);
    }

    public function testItemsInvalidOrBeforeOsCannotChangeRequest(): void
    {
        $this->session(['Auth' => 3]); $this->postAction('apontamentos', ['items' => [$this->item()]]);
        $this->assertSame(RequestStatus::AGUARDANDO_PCM->value, $this->request()->status);
        $this->prepareTracking();
        $this->assertSame(RequestStatus::OS_ABERTA->value, $this->request()->status);
        $invalid = $this->item(); $invalid['end_time'] = '07:59';
        $this->postAction('apontamentos', ['items' => [$this->item(), $invalid]]);
        $this->assertResponseOk(); $this->assertResponseContains('value="Carlos"');
        $this->assertResponseContains('value="07:59"');
        $this->assertSame(0, TableRegistry::getTableLocator()->get('RequestItems')->find()->count());
        $this->assertSame(RequestStatus::OS_ABERTA->value, $this->request()->status);
    }

    public function testAuthorizedUserCanConcludeTrackingWithoutMakingInitialItemsMandatory(): void
    {
        $this->prepareTracking();
        $this->session(['Auth' => 2]); $this->postAction('concluir-servico');
        $this->assertSame(RequestStatus::AGUARDANDO_ENCERRAMENTO->value, $this->request()->status);
        $this->assertSame(2, $this->request()->completed_by);
    }

    public function testLegacyItemsAndSystemMigrationAuditRemainVisible(): void
    {
        $items = TableRegistry::getTableLocator()->get('RequestItems');
        $item = $items->newEmptyEntity();
        $item->patch(['request_id' => 1, 'position' => 1, 'description' => 'Serviço antigo', 'created_at' => new \Cake\I18n\DateTime('now', 'UTC')]);
        $items->saveOrFail($item);
        $audits = TableRegistry::getTableLocator()->get('RequestAudits');
        $audits->saveOrFail($audits->newEntity(['request_id' => 1, 'user_id' => null, 'action' => 'STATUS_CHANGED',
            'old_values' => ['status' => 'OS_ABERTA'], 'new_values' => ['status' => 'ATENDIDA'], 'created_at' => new \Cake\I18n\DateTime('now', 'UTC')]));
        $this->session(['Auth' => 3]); $this->get('/solicitacoes/1'); $this->assertResponseOk();
        $this->assertResponseContains('Serviço antigo'); $this->assertResponseContains('Sistema');
        $this->assertResponseContains('Somente abertura da Ordem de Serviço');
    }

    public function testActiveAlertShowsDescriptionBranchesAndTerminalExclusions(): void
    {
        $this->session(['Auth' => 3]); $this->get('/api/solicitacoes-ativas?code=BEM01&branch=01');
        $this->assertResponseOk(); $this->assertContentType('application/json');
        $rows = json_decode((string)$this->_response->getBody(), true)['results'];
        $this->assertCount(2, $rows); $this->assertSame('Pedido de teste 1', $rows[0]['description']);
        $this->assertSame('Usuário 3', $rows[0]['requester']); $this->assertSame('Manutenção', $rows[0]['labor']);
        $this->assertNull($rows[1]['url']);
        $this->get('/api/solicitacoes-ativas?code=BEM01&branch=99'); $this->assertResponseContains('"results":[]');
        $table = TableRegistry::getTableLocator()->get('ServiceRequests');
        foreach ([RequestStatus::ATENDIDA, RequestStatus::ENCERRADA, RequestStatus::CANCELADA] as $status) {
            $table->updateAll(['status' => $status->value], ['id' => 1]);
            $this->get('/api/solicitacoes-ativas?code=BEM01&branch=01');
            $this->assertCount(1, json_decode((string)$this->_response->getBody(), true)['results']);
        }
        $table->updateAll(['flow' => 'ACOMPANHAMENTO', 'status' => 'AGUARDANDO_ENCERRAMENTO', 'totvs_os_number' => '004564'], ['id' => 2]);
        $this->get('/api/solicitacoes-ativas?code=BEM01&branch=01');
        $this->assertResponseContains('004564'); $this->assertResponseContains('/solicitacoes/2');
    }

    public function testNewRequestRequiresExplicitConfirmationButIsAllowedAndAudited(): void
    {
        $first = (new RequestCreationService($this->assets()))->create($this->data(), 2);
        $this->session(['Auth' => 3]); $this->enableCsrfToken('pcm_solicitacao_os_csrf');
        $this->post('/solicitacoes/nova', $this->data()); $this->assertResponseOk();
        $this->assertResponseContains('Já existe solicitação ativa');
        $this->assertSame(3, TableRegistry::getTableLocator()->get('ServiceRequests')->find()->count());
        $this->post('/solicitacoes/nova', $this->data() + ['confirm_existing' => '1', 'duplicate_reason' => 'Outro problema']);
        $this->assertResponseCode(302);
        $audit = TableRegistry::getTableLocator()->get('RequestAudits')->find()->where(['action' => 'DUPLICATE_CONFIRMED'])->firstOrFail();
        $this->assertSame([$first->id], $audit->new_values['active_requests']);
        $this->assertSame('Outro problema', $audit->new_values['reason']); $this->assertSame(3, $audit->user_id);
    }

    public function testClosureQueueComesFirstAndReportsKeepBothFlows(): void
    {
        TableRegistry::getTableLocator()->get('ServiceRequests')->updateAll(['flow' => 'ACOMPANHAMENTO', 'status' => 'AGUARDANDO_ENCERRAMENTO'], ['id' => 2]);
        $this->session(['Auth' => 1]); $this->get('/painel'); $this->assertResponseOk();
        $body = (string)$this->_response->getBody();
        $this->assertLessThan(strpos($body, 'SOL-2026-000001'), strpos($body, 'SOL-2026-000002'));
        $report = new \App\Service\RequestReportService();
        $this->assertSame(1, $report->query(['flow' => 'ACOMPANHAMENTO'])->count());
        $this->get('/relatorios?flow=ACOMPANHAMENTO'); $this->assertResponseOk();
        $this->assertResponseContains('SOL-2026-000002'); $this->assertResponseNotContains('SOL-2026-000001');
        $this->get('/painel?flow=ACOMPANHAMENTO'); $this->assertResponseOk();
        $this->assertResponseContains('SOL-2026-000002'); $this->assertResponseNotContains('SOL-2026-000001');
    }

    public function testLaborSearchIgnoresAccentsAndCaseWithoutChangingData(): void
    {
        $table = TableRegistry::getTableLocator()->get('Sectors'); $table->updateAll(['name' => 'Elétrica'], ['id' => 1]);
        $this->session(['Auth' => 3]);
        foreach (['eletrica', 'ELÉTRICA', 'ELETRICA'] as $term) {
            $this->get('/api/setores?q=' . rawurlencode($term)); $this->assertResponseOk();
            $this->assertSame('Elétrica', json_decode((string)$this->_response->getBody(), true)['results'][0]['name']);
        }
        $this->get('/api/setores?q=%25'); $this->assertResponseContains('"results":[]');
        $this->assertSame('Elétrica', $table->get(1)->name);
    }

    public function testAuditFailureRollsBackExecution(): void
    {
        $this->prepareTracking();
        $audits = TableRegistry::getTableLocator()->get('RequestAudits');
        $audits->getValidator()->add('action', 'reject', ['rule' => fn() => false]);
        try {
            (new RequestExecutionService())->handle(1, 2, 'items', [$this->item()]); $this->fail('Auditoria obrigatória.');
        } catch (\Cake\ORM\Exception\PersistenceFailedException $e) {
            $this->assertSame(RequestStatus::OS_ABERTA->value, $this->request()->status);
            $this->assertSame(0, TableRegistry::getTableLocator()->get('RequestItems')->find()->count());
        }
    }

    public function testInactiveAccountCannotExecute(): void
    {
        $this->prepareTracking(); $this->expectException(\Cake\Http\Exception\ForbiddenException::class);
        (new RequestExecutionService())->handle(1, 4, 'ongoing');
    }
}
