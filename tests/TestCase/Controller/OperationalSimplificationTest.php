<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Service\AssetsLookupInterface;
use App\Service\RequestCreationService;
use App\Service\RequestExecutionService;
use App\Service\RequestHandlingService;
use App\Service\RequestReportService;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class OperationalSimplificationTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.UserDepartments', 'app.Users', 'app.Sectors', 'app.RegistrationAudits',
        'app.ServiceRequests', 'app.RequestItems', 'app.RequestAudits', 'app.RequestAttachments'];

    private function table(string $name): \Cake\ORM\Table { return TableRegistry::getTableLocator()->get($name); }
    private function postForm(string $url, array $data = []): void
    {
        $this->enableCsrfToken('pcm_solicitacao_os_csrf'); $this->post($url, $data);
    }
    private function tracking(): void
    {
        $this->table('ServiceRequests')->updateAll(['flow' => 'ACOMPANHAMENTO', 'os_disposition' => 'MANTER_ABERTA'], ['id' => 1]);
    }
    private function item(): array
    {
        return ['executor' => 'Executante de campo', 'service_date' => '2026-10-13', 'start_time' => '08:00',
            'end_time' => '09:00', 'description' => 'Serviço registrado'];
    }

    public function testAssignmentRemovedAndLegacyOwnerDoesNotInterfereWithDirectLink(): void
    {
        $this->table('ServiceRequests')->updateAll(['assigned_to' => 2, 'assigned_at' => '2026-10-01 10:00:00'], ['id' => 1]);
        $this->session(['Auth' => 1]); $this->get('/solicitacoes/1'); $this->assertResponseOk();
        $this->assertResponseNotContains('Assumir'); $this->assertResponseNotContains('Assumida por');
        $this->assertResponseContains('Vincular O.S. TOTVS');
        $this->postForm('/solicitacoes/1/assumir'); $this->assertResponseCode(404);
        $this->postForm('/solicitacoes/1/vincular-os', ['totvs_os_number' => '123']); $this->assertRedirect('/solicitacoes/1');
        $record = $this->table('ServiceRequests')->get(1);
        $this->assertSame('ATENDIDA', $record->status); $this->assertSame(2, $record->assigned_to);
        $this->assertNotNull($record->last_activity_at);
    }

    public function testDuplicateOsIsRejectedWithRequestNumberAndDatabaseAlsoEnforcesUniqueness(): void
    {
        $service = new RequestHandlingService(); $service->handle(1, 1, 'link', ' 005874 ');
        $this->session(['Auth' => 1]); $this->postForm('/solicitacoes/2/vincular-os', ['totvs_os_number' => '005874']);
        $this->assertRedirect('/solicitacoes/2');
        $this->assertSession('Esta O.S. já está vinculada à solicitação SOL-2026-000001.', 'Flash.flash.0.message');
        $this->assertNull($this->table('ServiceRequests')->get(2)->totvs_os_number);
        $this->assertSame(2, $this->table('RequestAudits')->find()->count());
        $record = $this->table('ServiceRequests')->get(2); $record->set('totvs_os_number', '005874');
        $this->assertFalse($this->table('ServiceRequests')->save($record));
        $this->assertArrayHasKey('totvs_os_number', $record->getErrors());
        try {
            $this->table('ServiceRequests')->getConnection()->execute("UPDATE service_requests SET totvs_os_number = ' 005874 ' WHERE id = 2");
            $this->fail('O índice deve impedir gravação direta duplicada');
        } catch (\PDOException $e) { $this->assertSame('23505', $e->getPrevious()?->getCode() ?? (string)$e->getCode()); }
        $this->assertSame('005874', $this->table('ServiceRequests')->get(1)->totvs_os_number);
        $this->assertNull($this->table('ServiceRequests')->get(2)->totvs_os_number);
    }

    public function testMultipleNullOsAreAllowed(): void
    {
        $table = $this->table('ServiceRequests');
        $this->assertTrue((bool)$table->save($table->get(1))); $this->assertTrue((bool)$table->save($table->get(2)));
        $this->assertSame(2, $table->find()->where(['totvs_os_number IS' => null])->count());
    }

    public function testSnapshotsRecordEveryActionAndSurviveUserChanges(): void
    {
        $this->tracking(); $handling = new RequestHandlingService(); $execution = new RequestExecutionService();
        $handling->handle(1, 1, 'link', 'SNAPSHOT-OS');
        $execution->handle(1, 2, 'items', [$this->item()]);
        $execution->handle(1, 3, 'ongoing');
        $execution->handle(1, 2, 'complete');
        $fake = $this->createMock(\App\Service\OrdersLookupInterface::class);
        $fake->method('batch')->willReturn([['number' => 'SNAPSHOT-OS', 'branch' => '01', 'asset_code' => 'BEM01', 'situation' => 'L', 'finished' => 'S']]);
        (new \App\Service\TotvsOrderSyncService($fake))->sync();
        $audits = $this->table('RequestAudits')->find()->where(['request_id' => 1])->orderBy(['id' => 'ASC'])->all()->toList();
        $this->assertContains('TOTVS_OS_LINKED', array_column($audits, 'action'));
        $this->assertContains('EXECUTION_ITEMS_ADDED', array_column($audits, 'action'));
        $this->assertContains('SERVICE_ONGOING', array_column($audits, 'action'));
        $this->assertContains('OS_DISPOSITION_CHANGED', array_column($audits, 'action'));
        $this->assertContains('STATUS_CHANGED', array_column($audits, 'action'));
        $completed = $this->table('RequestAudits')->find()->where(['action' => 'SERVICE_COMPLETED'])->firstOrFail();
        $this->assertEquals(['id' => 2, 'name' => 'Usuário 2', 'username' => 'user2', 'email' => 'user2@example.test', 'role' => 'GESTOR'], $completed->actor_snapshot);
        $this->assertSame('EM_EXECUCAO', $completed->old_status);
        $this->assertSame('AGUARDANDO_ENCERRAMENTO', $completed->new_status);
        $this->assertNotNull($completed->created_at);
        $request = $this->table('ServiceRequests')->get(1);
        $this->assertSame(2, $request->completed_by); $this->assertNotNull($request->completed_at);
        $this->assertNotNull($request->last_activity_at); $this->assertNotNull($request->last_item_at);
        foreach ($audits as $audit) {
            $this->assertSame($audit->user_id, $audit->actor_snapshot['id']);
            $this->assertArrayHasKey('name', $audit->actor_snapshot); $this->assertNotNull($audit->new_status);
            $this->assertArrayNotHasKey('password', $audit->actor_snapshot);
        }
        $this->table('Users')->updateAll(['name' => 'Nome alterado', 'username' => 'matricula.alterada', 'email' => 'alterado@example.test'], ['id' => 2]);
        $this->session(['Auth' => 3]); $this->get('/solicitacoes/1'); $this->assertResponseOk();
        $this->assertResponseContains('Usuário 2 informou que o serviço foi concluído');
        $this->assertResponseContains('Usuário: user2');
        $this->assertResponseNotContains('Nome alterado'); $this->assertResponseNotContains('user2@example.test');
        $this->session(['Auth' => 1]); $this->get('/solicitacoes/1'); $this->assertResponseContains('user2@example.test');
    }

    public function testCreationSnapshotAndActivityAreRecorded(): void
    {
        $this->table('ServiceRequests')->getConnection()->execute("SELECT setval(pg_get_serial_sequence('service_requests', 'id'), (SELECT MAX(id) FROM service_requests))");
        $assets = $this->createMock(AssetsLookupInterface::class);
        $assets->method('find')->willReturn(['code' => 'NOVO', 'branch' => '01', 'description' => 'Bem novo']);
        $request = (new RequestCreationService($assets))->create(['unit' => 'USINA', 'asset_code' => 'NOVO', 'asset_branch' => '01',
            'sector_id' => 1, 'maintenance_type' => 'CORRETIVA', 'description' => 'Pedido', 'os_disposition' => 'MANTER_ABERTA', 'items' => [$this->item()]], 3);
        $audit = $this->table('RequestAudits')->find()->where(['request_id' => $request->id, 'action' => 'REQUEST_CREATED'])->firstOrFail();
        $this->assertSame('user3', $audit->actor_snapshot['username']); $this->assertSame('SOLICITANTE', $audit->actor_snapshot['role']);
        $this->assertNull($audit->old_status); $this->assertSame('AGUARDANDO_PCM', $audit->new_status);
        $this->assertNotNull($request->last_activity_at); $this->assertNull($request->last_item_at);
        $this->assertCount(1, $audit->new_values['items']);
    }

    public function testCancelAuditsReasonSnapshotAndActivity(): void
    {
        (new RequestHandlingService())->handle(1, 1, 'cancel', 'Pedido duplicado');
        $audit = $this->table('RequestAudits')->find()->where(['action' => 'REQUEST_CANCELLED'])->firstOrFail();
        $this->assertSame('Pedido duplicado', $audit->new_values['cancellation_reason']);
        $this->assertSame('user1', $audit->actor_snapshot['username']);
        $this->assertSame('AGUARDANDO_PCM', $audit->old_status); $this->assertSame('CANCELADA', $audit->new_status);
        $this->assertNotNull($this->table('ServiceRequests')->get(1)->last_activity_at);
    }

    public function testTerminalRecordsOnlyInReportsAndPersonalHistoryNotDashboard(): void
    {
        foreach (['ATENDIDA', 'ENCERRADA', 'CANCELADA'] as $state) {
            $this->table('ServiceRequests')->updateAll(['status' => $state], ['id' => 1]);
            $this->session(['Auth' => 1]); $this->get('/painel'); $this->assertResponseOk();
            $this->assertResponseNotContains('SOL-2026-000001'); $this->assertResponseContains('SOL-2026-000002');
            $this->assertResponseNotContains('Filtrar solicitações'); $this->assertResponseNotContains('name="status"');
            $this->get('/relatorios?status=' . $state); $this->assertResponseOk(); $this->assertResponseContains('SOL-2026-000001');
            $this->session(['Auth' => 3]); $this->get('/minhas-solicitacoes'); $this->assertResponseContains('SOL-2026-000001');
            $this->assertResponseNotContains('SOL-2026-000002');
        }
    }

    public function testReportFiltersByOsMaintenanceTypeAndRequesterDepartment(): void
    {
        $this->table('Users')->updateAll(['department_id' => 2], ['id' => 3]);
        $this->table('ServiceRequests')->updateAll(['status' => 'ENCERRADA', 'totvs_os_number' => 'REL-123'], ['id' => 1]);
        $report = new RequestReportService();
        $query = $report->query(['status' => 'ENCERRADA', 'totvs_os_number' => ' REL-123 ', 'department_id' => '2', 'maintenance_type' => 'CORRETIVA']);
        $this->assertSame(1, $query->count()); $this->assertSame(1, $query->contain(['Users', 'Sectors'])->firstOrFail()->id);
        $this->assertSame(0, $report->query(['totvs_os_number' => 'NAO-EXISTE'])->count());
        $this->assertSame(1, $report->query(['department_id' => 'UNSPECIFIED'])->count());
        $this->session(['Auth' => 1]); $this->get('/relatorios?department_id=2&totvs_os_number=REL-123'); $this->assertResponseOk();
        $this->assertResponseContains('SOL-2026-000001'); $this->assertResponseNotContains('SOL-2026-000002');
        foreach (['maintenance_type', 'department_id', 'totvs_os_number'] as $field) $this->assertResponseContains('name="' . $field . '"');
    }

    public function testRefreshPermissionsGuardsAndOnlyActiveData(): void
    {
        $this->get('/painel/atualizacao'); $this->assertRedirect('/entrar');
        foreach ([3, 4] as $id) {
            $this->session(['Auth' => $id]); $this->get('/painel/atualizacao');
            if ($id === 3) $this->assertResponseCode(403); else $this->assertRedirect('/entrar');
            $this->assertResponseNotContains('SOL-2026-000002');
        }
        $this->table('ServiceRequests')->updateAll(['status' => 'ATENDIDA'], ['id' => 1]);
        foreach ([1, 2] as $id) {
            $this->session(['Auth' => $id]); $this->get('/painel/atualizacao'); $this->assertResponseOk();
            $this->assertHeader('Cache-Control', 'no-store'); $this->assertResponseNotContains('<html');
            $this->assertResponseNotContains('SOL-2026-000001'); $this->assertResponseContains('SOL-2026-000002');
        }
        $this->table('Users')->updateAll(['must_change_password' => true], ['id' => 1]);
        $this->session(['Auth' => 1]); $this->get('/painel/atualizacao'); $this->assertRedirect('/alterar-senha');
    }

    public function testRefreshPaginationReturnsDashboardLinksAndHandlesDisappearingPage(): void
    {
        $table = $this->table('ServiceRequests');
        for ($id = 10; $id < 32; $id++) {
            $data = $table->get(1)->toArray(); unset($data['id']); $data['number'] = 'SOL-FILA-' . $id;
            $record = $table->newEmptyEntity(); $record->patch($data + ['id' => $id], ['guard' => false]); $table->saveOrFail($record);
        }
        $this->session(['Auth' => 1]); $this->get('/painel/atualizacao?page=2'); $this->assertResponseOk();
        $this->assertResponseContains('href="/painel"'); $this->assertResponseNotContains('/painel/atualizacao?page=1');
        $table->updateAll(['status' => 'ATENDIDA'], ['id >=' => 10]);
        $this->get('/painel/atualizacao?page=2'); $this->assertResponseOk(); $this->assertHeader('X-Queue-Page', '1');
        $this->assertResponseContains('SOL-2026-000001');
    }

    public function testSessionHasTwentyMinuteTimeoutAndRetainsSecurityConfiguration(): void
    {
        $config = Configure::read('Session');
        $this->assertSame(20, $config['timeout']); $this->assertSame('cake', $config['defaults']);
        $this->assertSame(1, $config['ini']['session.gc_probability']);
        $this->assertSame(1, $config['ini']['session.cookie_httponly']);
        $this->assertSame('Lax', $config['ini']['session.cookie_samesite']);
        $this->assertSame(0, $config['ini']['session.cookie_lifetime']);
        $session = new class(['timeout' => $config['timeout']]) extends \Cake\Http\Session {
            private mixed $lastTime;
            public function read(?string $name = null, mixed $default = null): mixed { return time() - 86400 * 365; }
            public function write(array|string $name, mixed $value = null): void { $this->lastTime = $value; }
            public function expired(): bool { return $this->_timedOut(); }
        };
        $this->assertTrue($session->expired());
    }

    public function testHttpsCsrfCookieKeepsSecureHttpOnlyAndSameSite(): void
    {
        $ini = Configure::read('Session.ini');
        try {
            $secure = $ini; $secure['session.cookie_secure'] = true; Configure::write('Session.ini', $secure);
            $this->get('/entrar'); $this->assertResponseOk();
            $cookie = $this->_response->getCookieCollection()->get('pcm_solicitacao_os_csrf');
            $this->assertTrue($cookie->isSecure()); $this->assertTrue($cookie->isHttpOnly());
            $this->assertSame('Lax', $cookie->getSameSite()?->value);
        } finally { Configure::write('Session.ini', $ini); }
    }
}
