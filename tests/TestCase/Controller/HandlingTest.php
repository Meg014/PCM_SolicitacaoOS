<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Domain\Enum\RequestStatus;
use App\Service\RequestHandlingService;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class HandlingTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Sectors', 'app.RegistrationAudits', 'app.ServiceRequests', 'app.RequestItems', 'app.RequestAudits'];

    private function postAction(string $action, array $data = []): void
    {
        $this->enableCsrfToken('pcm_solicitacao_os_csrf');
        $this->post('/solicitacoes/1/' . $action, $data);
    }

    private function record(): mixed
    {
        return TableRegistry::getTableLocator()->get('ServiceRequests')->get(1);
    }

    public function testDashboardPermissions(): void
    {
        $this->get('/painel');
        $this->assertRedirect('/entrar');
        foreach ([1, 2] as $id) {
            $this->session(['Auth' => $id]);
            $this->get('/painel');
            $this->assertResponseOk();
            $this->assertResponseContains('SOL-2026-000001');
            $this->assertResponseContains('SOL-2026-000002');
        }
        $this->session(['Auth' => 3]);
        $this->get('/painel');
        $this->assertResponseCode(403);
    }

    public function testManagerAndRequesterCannotHandle(): void
    {
        foreach ([2, 3] as $id) {
            $this->session(['Auth' => $id]);
            foreach (['assumir' => [], 'vincular-os' => ['totvs_os_number' => '123'], 'cancelar' => ['reason' => 'Recusada']] as $action => $data) {
                $this->postAction($action, $data);
                $this->assertResponseCode(403);
            }
        }
        $this->assertSame(RequestStatus::AGUARDANDO_PCM->value, $this->record()->status);
        $this->assertSame(0, TableRegistry::getTableLocator()->get('RequestAudits')->find()->count());
    }

    public function testAssignAndLinkAreAuditedAndVisibleToOwner(): void
    {
        $this->session(['Auth' => 1]);
        $this->postAction('assumir', ['assigned_to' => 2]);
        $this->assertRedirect('/solicitacoes/1');
        $this->assertSame(RequestStatus::EM_ANALISE->value, $this->record()->status);
        $this->assertSame(1, $this->record()->assigned_to);
        $this->assertNotNull($this->record()->assigned_at);
        $this->postAction('vincular-os', ['totvs_os_number' => ' 000123 ']);
        $this->assertRedirect('/solicitacoes/1');
        $this->assertSame('000123', $this->record()->totvs_os_number);
        $this->assertSame(RequestStatus::ATENDIDA->value, $this->record()->status);
        $this->assertSame(1, $this->record()->os_linked_by);
        $this->assertNotNull($this->record()->os_linked_at);
        $audits = TableRegistry::getTableLocator()->get('RequestAudits')->find()->orderBy(['id' => 'ASC'])->all()->toList();
        $this->assertSame(['REQUEST_ASSIGNED', 'STATUS_CHANGED', 'TOTVS_OS_LINKED', 'STATUS_CHANGED'], array_column($audits, 'action'));
        $this->assertSame(RequestStatus::EM_ANALISE->value, $audits[2]->old_values['status']);
        $this->session(['Auth' => 3]);
        $this->get('/solicitacoes/1');
        $this->assertResponseOk();
        $this->assertResponseContains('000123');
        $this->assertResponseContains('O.S. TOTVS vinculada');
        $this->assertResponseNotContains('Vincular O.S. TOTVS');
        $this->assertResponseNotContains('Ver registro de auditoria');
        $this->get('/solicitacoes/2');
        $this->assertResponseCode(404);
    }

    public function testRequiredOsAndCancellationReason(): void
    {
        $this->session(['Auth' => 1]);
        foreach (['', '   ', str_repeat('x', 101)] as $number) {
            $this->postAction('vincular-os', ['totvs_os_number' => $number]);
            $this->assertRedirect('/solicitacoes/1');
            $this->assertNull($this->record()->totvs_os_number);
        }
        foreach (['', '   ', str_repeat('x', 2001)] as $reason) {
            $this->postAction('cancelar', ['reason' => $reason]);
            $this->assertSame(RequestStatus::AGUARDANDO_PCM->value, $this->record()->status);
        }
        $this->assertSame(0, TableRegistry::getTableLocator()->get('RequestAudits')->find()->count());
    }

    public function testCancelRequiresReasonAndRecordsActor(): void
    {
        $this->session(['Auth' => 1]);
        $this->postAction('cancelar', ['reason' => 'Pedido duplicado']);
        $this->assertSame(RequestStatus::CANCELADA->value, $this->record()->status);
        $this->assertSame('Pedido duplicado', $this->record()->cancellation_reason);
        $this->assertSame(1, $this->record()->cancelled_by);
        $this->assertNotNull($this->record()->cancelled_at);
        $this->get('/solicitacoes/1');
        $this->assertResponseOk();
        $this->assertResponseContains('Pedido duplicado');
        $this->assertResponseContains('Ver registro de auditoria');
        $this->assertResponseNotContains('Assumir solicitação');
        $this->assertSame(2, TableRegistry::getTableLocator()->get('RequestAudits')->find()->count());
    }

    public function testRepeatedOrTerminalActionsDoNotOverwrite(): void
    {
        $this->session(['Auth' => 1]);
        $this->postAction('assumir');
        $this->postAction('assumir');
        $this->assertSame(2, TableRegistry::getTableLocator()->get('RequestAudits')->find()->count());
        $this->postAction('vincular-os', ['totvs_os_number' => '123']);
        $this->postAction('vincular-os', ['totvs_os_number' => '456']);
        $this->postAction('cancelar', ['reason' => 'Tentativa']);
        $this->assertSame('123', $this->record()->totvs_os_number);
        $this->assertSame(RequestStatus::ATENDIDA->value, $this->record()->status);
        $this->assertSame(4, TableRegistry::getTableLocator()->get('RequestAudits')->find()->count());
    }

    public function testDashboardFiltersAndNewestFirst(): void
    {
        $table = TableRegistry::getTableLocator()->get('ServiceRequests');
        $table->updateAll(['created_at' => '2026-10-06 03:00:00'], ['id' => 2]);
        $this->session(['Auth' => 2]);
        $this->get('/painel');
        $this->assertResponseOk();
        $this->assertResponseContains('aria-describedby="queue-scroll-help"');
        $this->assertResponseContains('Deslize a tabela para os lados');
        $this->assertResponseContains('class="button secondary"');
        $body = (string)$this->_response->getBody();
        $this->assertLessThan(strpos($body, 'SOL-2026-000002'), strpos($body, 'SOL-2026-000001'));
        $this->get('/painel?sector_id=1&start=2026-10-07&end=2026-10-07');
        $this->assertResponseOk();
        $this->assertResponseContains('SOL-2026-000001');
        $this->assertResponseNotContains('SOL-2026-000002');
        $this->get('/painel?status=' . RequestStatus::CANCELADA->value);
        $this->assertResponseNotContains('SOL-2026-000001');
        $this->get('/painel?start=2026-02-30');
        $this->assertResponseContains('Informe datas válidas');
    }

    public function testCsrfAndGetCannotMutate(): void
    {
        $this->session(['Auth' => 1]);
        $this->post('/solicitacoes/1/assumir');
        $this->assertResponseCode(403);
        $this->get('/solicitacoes/1/assumir');
        $this->assertResponseCode(404);
        $this->assertSame(RequestStatus::AGUARDANDO_PCM->value, $this->record()->status);
    }

    public function testAuditFailureRollsBackHandling(): void
    {
        $audits = TableRegistry::getTableLocator()->get('RequestAudits');
        $audits->getValidator()->add('action', 'reject', ['rule' => fn() => false]);
        try {
            (new RequestHandlingService())->handle(1, 1, 'assign');
            $this->fail('A falha de auditoria deve impedir a alteração.');
        } catch (\Cake\ORM\Exception\PersistenceFailedException $e) {
            $this->assertSame(RequestStatus::AGUARDANDO_PCM->value, $this->record()->status);
            $this->assertNull($this->record()->assigned_to);
            $this->assertSame(0, $audits->find()->count());
        }
    }

    public function testFrozenDurationIsConsistentAcrossScreens(): void
    {
        $requests = TableRegistry::getTableLocator()->get('ServiceRequests');
        $requests->updateAll([
            'status' => RequestStatus::ATENDIDA->value, 'totvs_os_number' => '123',
            'os_linked_by' => 1, 'os_linked_at' => '2026-10-07 13:11:00',
        ], ['id' => 1]);
        $requests->updateAll([
            'status' => RequestStatus::CANCELADA->value, 'cancelled_by' => 1,
            'cancelled_at' => '2026-10-07 12:12:00', 'cancellation_reason' => 'Duplicada',
        ], ['id' => 2]);
        $this->session(['Auth' => 1]);
        foreach (['/painel', '/relatorios', '/solicitacoes/1'] as $url) {
            $this->get($url);
            $this->assertResponseOk();
            $this->assertResponseContains('Tempo de atendimento');
            $this->assertResponseContains('1h 11min');
            $this->assertResponseNotContains('minutes ago');
        }
        $this->get('/solicitacoes/2');
        $this->assertResponseOk();
        $this->assertResponseContains('12 min');
        $this->session(['Auth' => 3]);
        $this->get('/minhas-solicitacoes');
        $this->assertResponseOk();
        $this->assertResponseContains('1h 11min');
    }
}
