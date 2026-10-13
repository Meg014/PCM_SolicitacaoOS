<?php
declare(strict_types=1);
namespace App\Test\TestCase\Controller;

use App\Service\AssetsLookupInterface;
use App\Service\OrdersLookupInterface;
use App\Service\OrdersUnavailableException;
use App\Service\TotvsOrderSyncService;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class TotvsAutomationTest extends TestCase
{
    use IntegrationTestTrait;
    protected array $fixtures = ['app.Users', 'app.Sectors', 'app.RegistrationAudits', 'app.ServiceRequests',
        'app.RequestItems', 'app.RequestAudits', 'app.RequestAttachments'];
    private function table(string $alias): \Cake\ORM\Table { return TableRegistry::getTableLocator()->get($alias); }
    private function tracking(string $disposition = 'MANTER_ABERTA'): void
    {
        $this->table('ServiceRequests')->updateAll(['flow' => 'ACOMPANHAMENTO', 'os_disposition' => $disposition,
            'status' => 'OS_ABERTA', 'totvs_os_number' => '005874', 'last_activity_at' => '2026-10-01 10:00:00'], ['id' => 1]);
    }
    private function order(string $finished = 'N'): array
    {
        return ['number' => '005874', 'branch' => '01', 'asset_code' => 'BEM01', 'situation' => 'L', 'finished' => $finished];
    }
    private function sync(array $rows): array
    {
        $fake = $this->createMock(OrdersLookupInterface::class);
        $fake->expects($this->once())->method('batch')->with(['005874'])->willReturn($rows);
        return (new TotvsOrderSyncService($fake))->sync();
    }
    private function postForm(string $url, array $data = []): void
    {
        $this->enableCsrfToken('pcm_solicitacao_os_csrf'); $this->post($url, $data);
    }

    public function testOpenOrderKeepsSharedExecutionAndSyncIsIdempotent(): void
    {
        $this->tracking(); $result = $this->sync([$this->order()]);
        $this->assertSame(1, $result['changed']);
        $record = $this->table('ServiceRequests')->get(1);
        $this->assertSame('EM_EXECUCAO', $record->status); $this->assertSame('01', $record->totvs_os_branch);
        $this->assertTrue(\App\Service\RequestAccess::canAppend($record));
        $this->assertNotNull($record->totvs_checked_at); $this->assertNotNull($record->last_activity_at);
        $this->table('ServiceRequests')->updateAll(['last_activity_at' => '2026-10-01 10:00:00'], ['id' => 1]);
        $result = $this->sync([$this->order()]); $this->assertSame(0, $result['changed']);
        $this->assertSame('2026-10-01 10:00:00', $this->table('ServiceRequests')->get(1)->last_activity_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, $this->table('RequestAudits')->find()->count());
    }

    public function testClosedTotvsOrderAutomaticallyClosesAndAuditsSystem(): void
    {
        $this->tracking(); $this->assertSame(1, $this->sync([$this->order('S')])['changed']);
        $record = $this->table('ServiceRequests')->get(1);
        $this->assertSame('ENCERRADA', $record->status); $this->assertNull($record->closed_by);
        $this->assertNotNull($record->closed_at); $this->assertNotNull($record->totvs_closed_detected_at);
        $audit = $this->table('RequestAudits')->find()->firstOrFail();
        $this->assertSame('TOTVS_AUTO_CLOSED', $audit->action); $this->assertNull($audit->user_id);
        $this->assertSame('Sistema', $audit->actor_snapshot['name']);
        $this->assertSame('OS_ABERTA', $audit->old_status); $this->assertSame('ENCERRADA', $audit->new_status);
        $this->assertSame('005874', $audit->new_values['totvs_os_number']);
        $this->assertSame('SISTEMA / SINCRONIZAÇÃO TOTVS', $audit->new_values['source']);
        $this->session(['Auth' => 1]); $this->get('/painel'); $this->assertResponseNotContains('SOL-2026-000001');
        $this->get('/relatorios?status=ENCERRADA'); $this->assertResponseContains('SOL-2026-000001');
        $this->session(['Auth' => 3]); $this->get('/minhas-solicitacoes'); $this->assertResponseContains('SOL-2026-000001');
        $this->get('/solicitacoes/1'); $this->assertResponseContains('Sistema identificou que a O.S. 005874 foi encerrada no TOTVS');
        $fake = $this->createMock(OrdersLookupInterface::class); $fake->expects($this->never())->method('batch');
        $this->assertSame(0, (new TotvsOrderSyncService($fake))->sync()['checked']);
        $this->assertSame(1, $this->table('RequestAudits')->find()->count());
    }

    public function testAllThreeFlowsPreserveOpeningOnlyAndTrackOtherTwo(): void
    {
        foreach (['MANTER_ABERTA', 'PODE_ENCERRAR'] as $choice) {
            $this->tracking($choice); $this->sync([$this->order()]);
            $this->assertSame('EM_EXECUCAO', $this->table('ServiceRequests')->get(1)->status);
            $this->sync([$this->order('S')]); $this->assertSame('ENCERRADA', $this->table('ServiceRequests')->get(1)->status);
        }
        $this->table('ServiceRequests')->updateAll(['flow' => 'SOMENTE_ABERTURA', 'os_disposition' => 'SOMENTE_ABERTURA', 'status' => 'ATENDIDA'], ['id' => 1]);
        $fake = $this->createMock(OrdersLookupInterface::class); $fake->expects($this->never())->method('batch');
        (new TotvsOrderSyncService($fake))->sync();
        $this->assertSame('ATENDIDA', $this->table('ServiceRequests')->get(1)->status);
    }

    public function testUnavailableMissingAmbiguousAndUnconfirmedOrdersNeverClose(): void
    {
        $this->tracking(); $fake = $this->createMock(OrdersLookupInterface::class);
        $fake->method('batch')->willThrowException(new OrdersUnavailableException());
        $this->assertSame(1, (new TotvsOrderSyncService($fake))->sync()['errors']);
        foreach ([[], [array_replace($this->order('S'), ['branch' => '02'])],
            [array_replace($this->order('S'), ['asset_code' => 'OUTRO'])], [$this->order('S'), $this->order('S')],
            [array_replace($this->order('S'), ['situation' => 'C'])], [array_replace($this->order(), ['finished' => '?'])]] as $rows) {
            $this->assertSame(1, $this->sync($rows)['warnings']);
            $this->assertSame('OS_ABERTA', $this->table('ServiceRequests')->get(1)->status);
            $this->assertNull($this->table('ServiceRequests')->get(1)->totvs_closed_detected_at);
        }
        $this->assertSame(0, $this->table('RequestAudits')->find()->count());
    }

    public function testSharedBranchAmbiguityDoesNotGuessAndConfirmedBranchIsStored(): void
    {
        $this->tracking(); $this->table('ServiceRequests')->updateAll(['asset_branch' => ''], ['id' => 1]);
        $this->assertSame(1, $this->sync([$this->order('S'), array_replace($this->order('S'), ['branch' => '02'])])['warnings']);
        $this->assertSame(1, $this->sync([$this->order()])['changed']);
        $this->assertSame('01', $this->table('ServiceRequests')->get(1)->totvs_os_branch);
        $this->assertSame(1, $this->sync([array_replace($this->order('S'), ['branch' => '02'])])['warnings']);
        $this->assertSame('EM_EXECUCAO', $this->table('ServiceRequests')->get(1)->status);
    }

    public function testConcurrentCancellationAndAuditFailureArePreserved(): void
    {
        $this->tracking(); $fake = $this->createMock(OrdersLookupInterface::class);
        $fake->method('batch')->willReturnCallback(function () {
            $this->table('ServiceRequests')->updateAll(['status' => 'CANCELADA'], ['id' => 1]);
            return [$this->order('S')];
        });
        $this->assertSame(0, (new TotvsOrderSyncService($fake))->sync()['changed']);
        $this->assertSame('CANCELADA', $this->table('ServiceRequests')->get(1)->status);
        $this->tracking(); $this->table('RequestAudits')->getValidator()->add('action', 'reject', ['rule' => fn() => false]);
        $this->assertSame(1, $this->sync([$this->order('S')])['errors']);
        $this->assertSame('OS_ABERTA', $this->table('ServiceRequests')->get(1)->status);
        $this->assertNull($this->table('ServiceRequests')->get(1)->totvs_closed_detected_at);
    }

    public function testManualClosureRemovedAndDetailIsAuthorizedOnDemand(): void
    {
        $this->tracking(); $row = $this->order() + ['description' => '<script>malicioso</script>', 'asset_name' => 'Bomba', 'identity_count' => 1];
        $fake = $this->createMock(OrdersLookupInterface::class);
        $fake->expects($this->exactly(2))->method('find')->with('005874', '01', 'BEM01')->willReturn($row);
        $this->mockService(OrdersLookupInterface::class, fn() => $fake);
        foreach ([1, 2] as $id) {
            $this->session(['Auth' => $id]); $this->get('/relatorios'); $this->assertResponseOk();
            $this->assertResponseContains('href="/relatorios/solicitacoes/1/os-totvs"');
            $this->get('/relatorios/solicitacoes/1/os-totvs'); $this->assertResponseOk();
            $this->assertResponseContains('Detalhes da Ordem de Serviço no TOTVS');
            $this->assertResponseContains('&lt;script&gt;malicioso');
        }
        $this->session(['Auth' => 3]); $this->get('/relatorios/solicitacoes/1/os-totvs'); $this->assertResponseCode(403);
        $this->session(['Auth' => 1]); $this->postForm('/solicitacoes/1/confirmar-encerramento'); $this->assertResponseCode(404);
        $this->table('ServiceRequests')->updateAll(['status' => 'AGUARDANDO_ENCERRAMENTO'], ['id' => 1]);
        $this->get('/solicitacoes/1'); $this->assertResponseNotContains('Confirmar encerramento');
        $this->assertResponseContains('identificará a mudança automaticamente');
    }

    public function testIdleExpirationHumanActivityAndPollingClock(): void
    {
        $last = time() - 1100;
        $this->session(['Auth' => 1, 'AuthActivity' => ['last_at' => $last]]);
        $this->get('/painel/atualizacao'); $this->assertResponseOk(); $this->assertSession($last, 'AuthActivity.last_at');
        $this->get('/painel'); $this->assertResponseOk(); $this->assertGreaterThan($last, $_SESSION['AuthActivity']['last_at']);
        $this->session(['Auth' => 1, 'AuthActivity' => ['last_at' => time() - 1201]]);
        $this->get('/painel/atualizacao'); $this->assertRedirect('/entrar'); $this->assertSession(null, 'Auth');
        $this->assertSession(true, 'AuthActivity.expired');
        $this->session(['Auth' => null, 'AuthActivity' => ['expired' => true]]);
        $this->get('/entrar'); $this->assertResponseContains('Sua sessão expirou por inatividade');
        $this->session(['Auth' => 1, 'AuthActivity' => ['last_at' => $last]]);
        $this->postForm('/sessao/atividade'); $this->assertResponseCode(204);
        $this->assertGreaterThan($last, $_SESSION['AuthActivity']['last_at']);
    }

    public function testLaborSelectOnlyActiveAndCameraInputs(): void
    {
        $sector = $this->table('Sectors')->newEntity(['name' => 'Inativa', 'active' => false]);
        $this->table('Sectors')->getConnection()->execute("SELECT setval(pg_get_serial_sequence('sectors', 'id'), (SELECT MAX(id) FROM sectors))");
        $this->table('Sectors')->saveOrFail($sector);
        $this->session(['Auth' => 3]); $this->get('/solicitacoes/nova'); $this->assertResponseOk();
        $this->assertMatchesRegularExpression('/<select[^>]*name="sector_id"[^>]*required/', (string)$this->_response->getBody());
        $this->assertResponseContains('Selecione a Mão de obra'); $this->assertResponseContains('Manutenção');
        $this->assertResponseNotContains('Inativa'); $this->assertResponseNotContains('data-kind="sector"');
        $this->assertResponseContains('capture="environment"'); $this->assertResponseContains('Tirar foto');
        $this->assertResponseContains('Selecionar da galeria'); $this->assertResponseContains('15 MB');
    }

    public function testSyncUsesBatchesOfAtMostFiftyLinkedOrders(): void
    {
        $this->tracking(); $table = $this->table('ServiceRequests');
        for ($id = 10; $id < 70; $id++) {
            $data = $table->get(1)->toArray(); $data['id'] = $id; $data['number'] = 'SOL-LOTE-' . $id; $data['totvs_os_number'] = 'OS-' . $id;
            $entity = $table->newEmptyEntity(); $entity->patch($data, ['guard' => false]); $table->saveOrFail($entity);
        }
        $sizes = []; $fake = $this->createMock(OrdersLookupInterface::class);
        $fake->expects($this->exactly(2))->method('batch')->willReturnCallback(function ($numbers) use (&$sizes) {
            $sizes[] = count($numbers);
            return array_map(fn($number) => array_replace($this->order(), ['number' => $number]), $numbers);
        });
        $result = (new TotvsOrderSyncService($fake))->sync();
        $this->assertSame([50, 11], $sizes); $this->assertSame(61, $result['checked']); $this->assertSame(61, $result['changed']);
    }
}
