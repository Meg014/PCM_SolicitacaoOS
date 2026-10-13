<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Service\AssetsLookupInterface;
use App\Service\RequestCreationService;
use App\Service\RequestExecutionService;
use Cake\ORM\Exception\PersistenceFailedException;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class HomologationTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.UserDepartments', 'app.Users', 'app.Sectors', 'app.RegistrationAudits',
        'app.ServiceRequests', 'app.RequestItems', 'app.RequestAudits', 'app.RequestAttachments'];

    private function table(string $name): \Cake\ORM\Table { return TableRegistry::getTableLocator()->get($name); }
    private function assets(): AssetsLookupInterface
    {
        $fake = $this->createMock(AssetsLookupInterface::class);
        $fake->method('find')->willReturnCallback(fn($code, $branch) => $code === 'BEM01' && $branch === '01'
            ? ['code' => $code, 'branch' => $branch, 'description' => 'Bomba'] : null);
        return $fake;
    }
    private function data(): array
    {
        return ['unit' => 'USINA', 'asset_code' => 'BEM01', 'asset_branch' => '01', 'sector_id' => 1,
            'maintenance_type' => 'CORRETIVA', 'description' => 'Reparar bomba', 'os_disposition' => 'MANTER_ABERTA',
            'confirm_existing' => '1', 'items' => []];
    }
    private function item(): array
    {
        return ['executor' => 'Executante externo', 'service_date' => '2026-10-13',
            'start_time' => '08:00', 'end_time' => '09:00', 'description' => 'Troca de vedação'];
    }
    private function track(int $id = 1): void
    {
        $this->table('ServiceRequests')->updateAll(['flow' => 'ACOMPANHAMENTO', 'os_disposition' => 'MANTER_ABERTA',
            'status' => 'EM_EXECUCAO', 'totvs_os_number' => 'OS-' . $id], ['id' => $id]);
    }
    private function postForm(string $url, array $data): void
    {
        $this->enableCsrfToken('pcm_solicitacao_os_csrf'); $this->post($url, $data);
    }

    public function testRequiredRequestFieldsMissingEmptyAndInvalidSelections(): void
    {
        $creation = new RequestCreationService($this->assets());
        foreach (['unit', 'asset_code', 'sector_id', 'maintenance_type', 'description', 'os_disposition'] as $field) {
            foreach (['missing', 'empty'] as $case) {
                $data = $this->data();
                if ($case === 'missing') unset($data[$field]); else $data[$field] = '';
                try {
                    $creation->create($data, 3); $this->fail('Campo obrigatório aceito: ' . $field);
                } catch (PersistenceFailedException $e) {
                    $this->assertArrayHasKey($field, $e->getEntity()->getErrors());
                    $this->assertStringNotContainsString('required', implode(' ', $e->getEntity()->getError($field)));
                }
            }
        }
        foreach (['unit' => 'OUTRA', 'asset_code' => 'INVALIDO', 'sector_id' => 999,
            'maintenance_type' => 'INVALIDO', 'description' => '   ', 'os_disposition' => 'INVALIDO'] as $field => $value) {
            $data = $this->data(); $data[$field] = $value;
            try { $creation->create($data, 3); $this->fail('Seleção inválida aceita: ' . $field); }
            catch (PersistenceFailedException $e) { $this->assertArrayHasKey($field, $e->getEntity()->getErrors()); }
        }
        $this->assertSame(2, $this->table('ServiceRequests')->find()->count());
    }

    public function testOptionalAndCompleteInitialItemsAndPartialRows(): void
    {
        $this->table('ServiceRequests')->getConnection()->execute("SELECT setval(pg_get_serial_sequence('service_requests', 'id'), (SELECT MAX(id) FROM service_requests))");
        $creation = new RequestCreationService($this->assets());
        foreach ([[], [['executor' => '', 'description' => '', 'service_date' => '', 'start_time' => '', 'end_time' => '']]] as $rows) {
            $data = $this->data(); $data['items'] = $rows; $request = $creation->create($data, 3);
            $this->assertSame(0, $this->table('RequestItems')->find()->where(['request_id' => $request->id])->count());
        }
        foreach (array_keys($this->item()) as $field) {
            $data = $this->data(); $item = $this->item(); unset($item[$field]); $data['items'] = [$item];
            try { $creation->create($data, 3); $this->fail('Apontamento parcial aceito'); }
            catch (PersistenceFailedException $e) {
                $this->assertArrayHasKey('items', $e->getEntity()->getErrors());
                $this->assertStringNotContainsString('required', json_encode($e->getEntity()->getErrors()));
            }
        }
        $data = $this->data(); $data['items'] = [['_added' => '1']];
        try { $creation->create($data, 3); $this->fail('Linha adicionada explicitamente deve ser preenchida'); }
        catch (PersistenceFailedException $e) { $this->assertArrayHasKey('items', $e->getEntity()->getErrors()); }
        $data = $this->data(); $data['items'] = [$this->item()]; $request = $creation->create($data, 3);
        $this->assertSame(1, $this->table('RequestItems')->find()->where(['request_id' => $request->id])->count());
        $this->assertSame(5, $this->table('ServiceRequests')->find()->count());
    }

    public function testDedicatedFormAppendsSharedItemsPreservesRequestAndAuditsActor(): void
    {
        $this->track(); $this->session(['Auth' => 2]);
        $before = $this->table('ServiceRequests')->get(1);
        $this->get('/solicitacoes/1/novo-apontamento'); $this->assertResponseOk();
        $this->assertResponseContains('Salvar apontamento');
        $this->assertResponseNotContains('name="unit"'); $this->assertResponseNotContains('name="sector_id"');
        $this->assertResponseNotContains('name="asset_code"');
        $this->postForm('/solicitacoes/1/novo-apontamento', ['items' => [$this->item()], 'user_id' => 2, 'number' => 'ALTERADO']);
        $this->assertRedirect('/solicitacoes/1');
        $firstActivity = $this->table('ServiceRequests')->get(1)->last_activity_at;
        $this->assertNotNull($firstActivity);
        $this->session(['Auth' => 3]);
        $this->postForm('/solicitacoes/1/novo-apontamento', ['items' => [$this->item() + ['extra' => 'ignorado']]]);
        $this->assertRedirect('/solicitacoes/1');
        $after = $this->table('ServiceRequests')->get(1);
        foreach (['number', 'user_id', 'totvs_os_number', 'description', 'sector_id', 'asset_code'] as $field) {
            $this->assertSame($before->get($field), $after->get($field));
        }
        $this->assertSame(2, $this->table('ServiceRequests')->find()->count());
        $items = $this->table('RequestItems')->find()->where(['request_id' => 1])->orderBy(['position' => 'ASC'])->all()->toList();
        $this->assertCount(2, $items); $this->assertSame([2, 3], array_column($items, 'recorded_by'));
        $audits = $this->table('RequestAudits')->find()->where(['action' => 'EXECUTION_ITEMS_ADDED'])->orderBy(['id' => 'ASC'])->all()->toList();
        $this->assertCount(2, $audits); $this->assertSame([2, 3], array_column($audits, 'user_id'));
        $this->assertArrayHasKey('last_activity_at', $audits[0]->new_values);
        $this->get('/solicitacoes/1'); $this->assertResponseContains('NOVO APONTAMENTO');
    }

    public function testAppendFailuresDoNotUpdateActivityOrCreateRecords(): void
    {
        $this->track(); $this->session(['Auth' => 2]);
        $this->postForm('/solicitacoes/1/novo-apontamento', ['items' => [['executor' => 'Pessoa']]]);
        $this->assertResponseOk(); $this->assertResponseContains('Informe a data do serviço');
        $this->assertNull($this->table('ServiceRequests')->get(1)->last_activity_at);
        $this->assertSame(0, $this->table('RequestItems')->find()->count());
        $this->table('RequestAudits')->getValidator()->add('action', 'reject', ['rule' => fn() => false]);
        try { (new RequestExecutionService())->handle(1, 2, 'items', [$this->item()]); $this->fail('Auditoria deveria impedir gravação'); }
        catch (PersistenceFailedException $e) {
            $this->assertNull($this->table('ServiceRequests')->get(1)->last_activity_at);
            $this->assertSame(0, $this->table('RequestItems')->find()->count());
        }
    }

    public function testAppendPermissionsAndLookupActionDependOnCurrentRequest(): void
    {
        $this->session(['Auth' => 3]);
        $this->get('/solicitacoes/2/novo-apontamento'); $this->assertResponseCode(404);
        $this->get('/solicitacoes/1/novo-apontamento'); $this->assertResponseCode(409);
        $this->track(2);
        $this->get('/api/solicitacoes-ativas?code=BEM01&branch=01'); $this->assertResponseOk();
        $rows = json_decode((string)$this->_response->getBody(), true)['results'];
        $this->assertNull($rows[0]['add_item_url']);
        $this->assertSame('/solicitacoes/2/novo-apontamento', $rows[1]['add_item_url']);
        $this->get('/solicitacoes/2/novo-apontamento'); $this->assertResponseOk();
        $this->table('ServiceRequests')->updateAll(['status' => 'AGUARDANDO_ENCERRAMENTO'], ['id' => 2]);
        $this->postForm('/solicitacoes/2/novo-apontamento', ['items' => [$this->item()]]); $this->assertResponseCode(409);
        $this->session(['Auth' => 4]); $this->get('/solicitacoes/2/novo-apontamento'); $this->assertRedirect('/entrar');
    }

    public function testNewActivityElevatesTrackingQueueWithoutStatusPriority(): void
    {
        $this->track(1); $this->track(2);
        $table = $this->table('ServiceRequests');
        $table->updateAll(['created_at' => '2026-10-01 10:00:00'], ['id' => 1]);
        $table->updateAll(['created_at' => '2026-10-12 10:00:00', 'status' => 'OS_ABERTA'], ['id' => 2]);
        $this->session(['Auth' => 1]); $this->get('/painel');
        $before = (string)$this->_response->getBody();
        $this->assertLessThan(strpos($before, '>SOL-2026-000001</a>'), strpos($before, '>SOL-2026-000002</a>'));
        (new RequestExecutionService())->handle(1, 2, 'items', [$this->item()]);
        $this->get('/painel'); $body = (string)$this->_response->getBody();
        $this->assertLessThan(strpos($body, '>SOL-2026-000002</a>'), strpos($body, '>SOL-2026-000001</a>'));
        $this->assertResponseContains('NOVO APONTAMENTO');
        $table->updateAll(['status' => 'AGUARDANDO_ENCERRAMENTO'], ['id' => 2]);
        $this->get('/painel'); $body = (string)$this->_response->getBody();
        $this->assertLessThan(strpos($body, '>SOL-2026-000002</a>'), strpos($body, '>SOL-2026-000001</a>'));
        $table->updateAll(['status' => 'ENCERRADA'], ['id' => 2]);
        $this->get('/painel'); $body = (string)$this->_response->getBody();
        $this->assertResponseContains('SOL-2026-000001'); $this->assertResponseNotContains('SOL-2026-000002');
    }

    public function testCreationAndItemsUseLatestActivityInPageAndPollingWithoutBadgePriority(): void
    {
        $table = $this->table('ServiceRequests');
        $table->getConnection()->execute("SELECT setval(pg_get_serial_sequence('service_requests', 'id'), (SELECT MAX(id) FROM service_requests))");
        $creation = new RequestCreationService($this->assets());
        $this->session(['Auth' => 1]);
        $assertOrder = function (array $numbers, bool $badge): void {
            foreach (['/painel', '/painel/atualizacao', '/painel/atualizacao?sort=created_at&direction=asc'] as $url) {
                $this->get($url); $this->assertResponseOk();
                preg_match_all('/>(SOL-\d{4}-\d{6})<\/a>/', (string)$this->_response->getBody(), $matches);
                $this->assertSame($numbers, $matches[1]);
                if ($badge) $this->assertResponseContains('NOVO APONTAMENTO');
            }
        };
        try {
            \Cake\I18n\DateTime::setTestNow('2026-10-13 14:00:00');
            $first = $creation->create($this->data(), 3);
            $this->assertEquals($first->created_at, $first->last_activity_at);
            \Cake\I18n\DateTime::setTestNow('2026-10-13 14:10:00');
            $second = $creation->create($this->data(), 3);
            $legacy = ['SOL-2026-000002', 'SOL-2026-000001'];
            $assertOrder([$second->number, $first->number, ...$legacy], false);
            $this->track($first->id);
            \Cake\I18n\DateTime::setTestNow('2026-10-13 14:20:00');
            (new RequestExecutionService())->handle($first->id, 2, 'items', [$this->item()]);
            $this->assertSame('14:20', $table->get($first->id)->last_activity_at->format('H:i'));
            $assertOrder([$first->number, $second->number, ...$legacy], true);
            \Cake\I18n\DateTime::setTestNow('2026-10-13 14:25:00');
            $third = $creation->create($this->data(), 3);
            $assertOrder([$third->number, $first->number, $second->number, ...$legacy], true);
        } finally {
            \Cake\I18n\DateTime::setTestNow();
        }
    }

    public function testUserDepartmentRequiredAndLegacyStillWorksWithoutInventedMapping(): void
    {
        $this->session(['Auth' => 1]);
        $data = ['name' => 'Novo usuário', 'email' => 'novo.dept@example.test', 'password' => 'SenhaTeste123!', 'role' => 'SOLICITANTE', 'active' => '1'];
        $this->postForm('/usuarios/novo', $data); $this->assertResponseOk();
        $this->assertResponseContains('Selecione o setor do funcionário');
        foreach ([999, 3] as $invalid) {
            $this->postForm('/usuarios/novo', $data + ['department_id' => $invalid]); $this->assertResponseOk();
            $this->assertSame(4, $this->table('Users')->find()->count());
        }
        $this->table('Users')->getConnection()->execute("SELECT setval(pg_get_serial_sequence('users', 'id'), (SELECT MAX(id) FROM users))");
        $this->postForm('/usuarios/novo', $data + ['department_id' => 2]); $this->assertRedirect('/usuarios');
        $user = $this->table('Users')->find()->where(['email' => $data['email']])->firstOrFail();
        $this->assertSame(2, $user->department_id);
        $this->get('/usuarios'); $this->assertResponseContains('Moenda teste'); $this->assertResponseContains('Não informado (registro anterior)');
        $this->postForm('/usuarios/2/editar', ['name' => 'Gestor atualizado']); $this->assertResponseOk();
        $this->assertNull($this->table('Users')->get(2)->department_id);
        $this->session(['Auth' => 3]); $this->get('/meu-perfil'); $this->assertResponseOk();
        $this->assertResponseContains('Não informado (registro anterior)');
        (new \App\Service\PasswordChangeService())->change(3, 'SenhaTeste123!', 'SenhaNova123!', 'SenhaNova123!');
        $this->assertNull($this->table('Users')->get(3)->department_id);
    }

    public function testDepartmentCatalogRestrictedToPcmAndAudited(): void
    {
        $this->session(['Auth' => 2]); $this->get('/setores-usuarios'); $this->assertResponseCode(403);
        $this->session(['Auth' => 1]);
        $this->table('UserDepartments')->getConnection()->execute("SELECT setval(pg_get_serial_sequence('user_departments', 'id'), (SELECT MAX(id) FROM user_departments))");
        $this->postForm('/setores-usuarios/novo', ['name' => 'Caldeira', 'active' => '1']); $this->assertRedirect('/setores-usuarios');
        $department = $this->table('UserDepartments')->find()->where(['name' => 'Caldeira'])->firstOrFail();
        $audit = $this->table('RegistrationAudits')->find()->where(['resource' => 'UserDepartments'])->firstOrFail();
        $this->assertSame($department->id, $audit->record_id); $this->assertSame(1, $audit->user_id);
        $this->assertFalse($this->table('Sectors')->exists(['name' => 'Caldeira']));
    }

    public function testOnlyOneCreationShortcutOnQueuesAndNoDuplicateHeader(): void
    {
        $this->session(['Auth' => 1]);
        foreach (['/painel', '/minhas-solicitacoes', '/acompanhamento'] as $url) {
            $this->get($url); $this->assertResponseOk();
            $body = (string)$this->_response->getBody();
            $this->assertSame(1, substr_count($body, 'href="/solicitacoes/nova"'));
            $this->assertResponseNotContains('class="button header-request-shortcut"');
        }
        $this->get('/solicitacoes/nova'); $this->assertResponseOk();
        $this->assertResponseNotContains('class="button header-request-shortcut"');
        foreach (['unit', 'maintenance_type', 'description', 'os_disposition', 'asset_label', 'sector_id'] as $field) {
            $this->assertMatchesRegularExpression('/<(?:input|select|textarea)[^>]*name="' . $field . '"[^>]*required/s', (string)$this->_response->getBody());
        }
    }
}
