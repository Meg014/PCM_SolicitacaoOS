<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Domain\Enum\OsDisposition;
use App\Domain\Enum\RequestStatus;
use App\Service\AssetsLookupInterface;
use App\Service\RequestCreationService;
use App\Service\RequestExecutionService;
use App\Service\RequestHandlingService;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class OsDispositionTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Sectors', 'app.RegistrationAudits', 'app.ServiceRequests', 'app.RequestItems', 'app.RequestAudits', 'app.RequestAttachments'];

    public function setUp(): void
    {
        parent::setUp();
        \Cake\Datasource\ConnectionManager::get('test')->execute("SELECT setval(pg_get_serial_sequence('service_requests', 'id'), (SELECT MAX(id) FROM service_requests))");
        $this->mockService(AssetsLookupInterface::class, fn() => $this->assets());
    }

    private function assets(): AssetsLookupInterface
    {
        $fake = $this->createMock(AssetsLookupInterface::class);
        $fake->method('find')->willReturn(['code' => 'BEM02', 'branch' => '01', 'description' => 'Motor']);
        return $fake;
    }
    private function syncClosed(int $id): void
    {
        $request = TableRegistry::getTableLocator()->get('ServiceRequests')->get($id);
        $fake = $this->createMock(\App\Service\OrdersLookupInterface::class);
        $fake->method('batch')->willReturn([['number' => $request->totvs_os_number, 'branch' => $request->asset_branch,
            'asset_code' => $request->asset_code, 'situation' => 'L', 'finished' => 'S']]);
        (new \App\Service\TotvsOrderSyncService($fake))->sync();
    }

    private function data(OsDisposition $choice): array
    {
        return ['os_disposition' => $choice->value, 'flow' => 'FORJADO', 'unit' => 'USINA', 'sector_id' => 1,
            'asset_code' => 'BEM02', 'asset_branch' => '01', 'maintenance_type' => 'CORRETIVA',
            'description' => 'Revisar motor', 'items' => [], 'confirm_existing' => '1'];
    }

    public function testThreeChoicesDetermineLinkStatusWithoutDeducingFromItems(): void
    {
        $table = TableRegistry::getTableLocator()->get('ServiceRequests');
        foreach (OsDisposition::cases() as $choice) {
            $request = (new RequestCreationService($this->assets()))->create($this->data($choice), 3);
            $this->assertSame($choice->value, $request->os_disposition);
            $this->assertSame($choice === OsDisposition::SOMENTE_ABERTURA ? 'SOMENTE_ABERTURA' : 'ACOMPANHAMENTO', $request->flow);
            (new RequestHandlingService())->handle($request->id, 1, 'link', '004564-' . $request->id);
            $expected = match ($choice) {
                OsDisposition::SOMENTE_ABERTURA => RequestStatus::ATENDIDA,
                OsDisposition::MANTER_ABERTA => RequestStatus::EM_EXECUCAO,
                OsDisposition::PODE_ENCERRAR => RequestStatus::AGUARDANDO_ENCERRAMENTO,
            };
            $this->assertSame($expected->value, $table->get($request->id)->status);
            $audit = TableRegistry::getTableLocator()->get('RequestAudits')->find()->where(['request_id' => $request->id, 'action' => 'REQUEST_CREATED'])->firstOrFail();
            $this->assertSame($choice->value, $audit->new_values['os_disposition']);
            $this->session(['Auth' => 1]); $this->get('/solicitacoes/' . $request->id); $this->assertResponseOk();
            $this->assertResponseContains($choice->heading());
        }
    }

    public function testRequiredChoiceRejectsInvalidAndMissingAndOnlyOneQuestionIsShown(): void
    {
        $this->session(['Auth' => 3]); $this->get('/solicitacoes/nova'); $this->assertResponseOk();
        $this->assertResponseContains('Como esta Ordem de Serviço deve seguir após a abertura?');
        $this->assertSame(3, substr_count((string)$this->_response->getBody(), 'name="os_disposition"'));
        $this->assertResponseNotContains('name="flow"');
        foreach ([null, '', 'INVALIDA', ['MANTER_ABERTA']] as $value) {
            $data = $this->data(OsDisposition::MANTER_ABERTA);
            if ($value === null) { unset($data['os_disposition']); } else { $data['os_disposition'] = $value; }
            $this->enableCsrfToken('pcm_solicitacao_os_csrf'); $this->post('/solicitacoes/nova', $data); $this->assertResponseOk();
            $this->assertSame(2, TableRegistry::getTableLocator()->get('ServiceRequests')->find()->count());
        }
    }

    public function testSharedContinuationChangesOrientationAndAuditsActor(): void
    {
        $request = (new RequestCreationService($this->assets()))->create($this->data(OsDisposition::MANTER_ABERTA), 3);
        (new RequestHandlingService())->handle($request->id, 1, 'link', '123');
        (new RequestExecutionService())->handle($request->id, 2, 'items', [[
            'executor' => 'Outro turno', 'service_date' => '2026-10-08', 'start_time' => '08:00', 'end_time' => '09:00', 'description' => 'Revisão',
        ]]);
        (new RequestExecutionService())->handle($request->id, 2, 'complete');
        $saved = TableRegistry::getTableLocator()->get('ServiceRequests')->get($request->id);
        $this->assertSame(3, $saved->user_id); $this->assertSame('PODE_ENCERRAR', $saved->os_disposition);
        $audit = TableRegistry::getTableLocator()->get('RequestAudits')->find()->where(['request_id' => $request->id, 'action' => 'OS_DISPOSITION_CHANGED'])->firstOrFail();
        $this->assertSame('MANTER_ABERTA', $audit->old_values['os_disposition']); $this->assertSame(2, $audit->user_id);
        $this->session(['Auth' => 1]); $this->get('/solicitacoes/' . $request->id);
        $this->assertResponseContains('Orientação atualizada: O.S. PODE SER ENCERRADA');
        $this->assertResponseNotContains('Confirmar encerramento no TOTVS');
        $this->syncClosed($request->id);
        $this->assertSame('ENCERRADA', TableRegistry::getTableLocator()->get('ServiceRequests')->get($request->id)->status);
    }

    public function testCanCloseChoiceWaitsForPcmConfirmationAndIsHighlightedBeforeLink(): void
    {
        $request = (new RequestCreationService($this->assets()))->create($this->data(OsDisposition::PODE_ENCERRAR), 3);
        $this->session(['Auth' => 1]); $this->get('/solicitacoes/' . $request->id); $this->assertResponseOk();
        $this->assertGreaterThanOrEqual(2, substr_count((string)$this->_response->getBody(), 'O.S. PODE SER ENCERRADA'));
        $this->get('/painel'); $this->assertResponseContains('O.S. PODE SER ENCERRADA');
        (new RequestHandlingService())->handle($request->id, 1, 'link', '456');
        $this->assertSame('AGUARDANDO_ENCERRAMENTO', TableRegistry::getTableLocator()->get('ServiceRequests')->get($request->id)->status);
        $this->session(['Auth' => 2]); $this->enableCsrfToken('pcm_solicitacao_os_csrf');
        $this->post('/solicitacoes/' . $request->id . '/confirmar-encerramento', ['confirmed_closed' => '1']);
        $this->assertResponseCode(404);
        $this->syncClosed($request->id);
        $this->assertSame('ENCERRADA', TableRegistry::getTableLocator()->get('ServiceRequests')->get($request->id)->status);
    }

    public function testEveryOperationalRoleCanChooseAnyOfThreeOptions(): void
    {
        foreach (\App\Domain\Enum\UserRole::cases() as $role) {
            TableRegistry::getTableLocator()->get('Users')->updateAll(['role' => $role->value], ['id' => 2]);
            $this->session(['Auth' => 2]);
            foreach (OsDisposition::cases() as $choice) {
                $this->enableCsrfToken('pcm_solicitacao_os_csrf'); $this->post('/solicitacoes/nova', $this->data($choice));
                $this->assertResponseCode(302);
            }
        }
        $this->assertSame(17, TableRegistry::getTableLocator()->get('ServiceRequests')->find()->count());
    }

    public function testQueueOrdersActivityAcrossActiveStatusesAndExcludesTerminal(): void
    {
        $table = TableRegistry::getTableLocator()->get('ServiceRequests');
        $priority = ['AGUARDANDO_ENCERRAMENTO', 'AGUARDANDO_PCM', 'EM_ANALISE', 'OS_ABERTA', 'EM_EXECUCAO', 'ATENDIDA', 'ENCERRADA', 'CANCELADA'];
        $id = 10; $expected = [];
        foreach ($priority as $status) {
            $pair = [];
            foreach (['2026-10-07 10:00:00', '2026-10-08 10:00:00'] as $date) {
                $record = $table->newEmptyEntity();
                $record->patch(['id' => $id, 'number' => 'FILA-' . $id, 'user_id' => 3, 'sector_id' => 1,
                    'unit' => 'USINA', 'flow' => 'ACOMPANHAMENTO', 'os_disposition' => 'MANTER_ABERTA',
                    'asset_code' => 'EQ', 'asset_branch' => '01', 'asset_description' => 'Equipamento',
                    'maintenance_type' => 'CORRETIVA', 'description' => 'Teste', 'status' => $status], ['guard' => false]);
                $table->saveOrFail($record);
                $table->updateAll(['created_at' => $date], ['id' => $id]); $pair[] = $id++;
            }
            $expected = array_merge($expected, array_reverse($pair));
        }
        $this->session(['Auth' => 1]); $this->get('/painel?unit=USINA'); $this->assertResponseOk();
        preg_match_all('/>FILA-(\d+)<\/a>/', (string)$this->_response->getBody(), $matches);
        // Nenhuma situação ativa recebe prioridade sobre a atividade mais recente.
        $expected = [19, 17, 13, 11, 18, 16, 12, 10];
        $this->assertSame($expected, array_map('intval', $matches[1]));
        $this->get('/relatorios?unit=USINA&status=EM_EXECUCAO'); $this->assertResponseOk();
        $this->assertResponseContains('FILA-19'); $this->assertResponseNotContains('FILA-20');
    }
}
