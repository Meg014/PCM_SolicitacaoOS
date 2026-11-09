<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Service\ServiceSelection;
use App\Service\RequestCreationService;
use App\Service\RequestExecutionService;
use App\Service\RequestHandlingService;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class ServicePairsTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Sectors', 'app.Services', 'app.ServiceActions', 'app.ServiceComponents',
        'app.RegistrationAudits', 'app.ServiceRequests', 'app.RequestItems', 'app.RequestAudits', 'app.RequestAttachments'];

    public function testDescriptionsAreComposedFromBothActiveOptions(): void
    {
        foreach ([[2, 2, 'Soldar chapa'], [3, 3, 'Emendar cabo'], [1, 1, 'Limpeza geral'], [1, 4, 'Limpar motor']] as [$action, $component, $text]) {
            $value = ServiceSelection::resolve($action, $component);
            $this->assertSame($text, $value['description']);
            $this->assertSame($action, $value['service_action_id']);
            $this->assertSame($component, $value['service_component_id']);
        }
        foreach ([[null,1], [1,null], ['OTHER',1], [999,1], [4,1], [1,5], [[],1]] as [$action,$component]) {
            try { ServiceSelection::resolve($action,$component); $this->fail('Opção ausente/inativa deve ser rejeitada.'); }
            catch (\InvalidArgumentException $e) { $this->assertStringContainsString('Selecione', $e->getMessage()); }
        }
    }

    public function testBothCatalogsAreAdminOnlyAndHaveAuditedActivationWithoutDelete(): void
    {
        foreach (['acoes-servico' => 'ServiceActions', 'componentes-servico' => 'ServiceComponents'] as $path => $alias) {
            foreach ([2,3] as $user) {
                $this->session(['Auth' => $user]);
                $this->get('/' . $path); $this->assertResponseCode(403);
                foreach (['/novo', '/1/editar', '/1/situacao'] as $action) {
                    $this->enableCsrfToken('pcm_solicitacao_os_csrf');
                    $this->post('/' . $path . $action, ['name'=>'Forjado']); $this->assertResponseCode(403);
                }
            }
            $this->session(['Auth' => 1]);
            $table = TableRegistry::getTableLocator()->get($alias);
            $tableName = $table->getTable();
            $table->getConnection()->execute("SELECT setval(pg_get_serial_sequence('$tableName','id'),(SELECT MAX(id) FROM $tableName))");
            $this->get('/' . $path); $this->assertResponseOk();
            $this->get('/' . $path . '/novo'); $this->assertResponseOk();
            $this->enableCsrfToken('pcm_solicitacao_os_csrf');
            $this->post('/' . $path . '/novo', ['name'=>'Nova opção', 'active'=>'1']); $this->assertRedirect('/' . $path);
            $option = $table->find()->where(['name'=>'Nova opção'])->firstOrFail();
            $this->enableCsrfToken('pcm_solicitacao_os_csrf');
            $this->post('/' . $path . '/' . $option->id . '/editar', ['name'=>'Opção atualizada', 'active'=>'1']); $this->assertRedirect('/' . $path);
            foreach ([false,true] as $active) {
                $this->enableCsrfToken('pcm_solicitacao_os_csrf');
                $this->post('/' . $path . '/' . $option->id . '/situacao'); $this->assertRedirect('/' . $path);
                $this->assertSame($active, $table->get($option->id)->active);
            }
            $this->assertFalse($table->delete($table->get($option->id)));
            $this->assertTrue($table->exists(['id'=>$option->id]));
            $events=TableRegistry::getTableLocator()->get('RegistrationAudits')->find()->where(['resource'=>$alias])->orderBy(['id'=>'ASC'])->toArray();
            $this->assertSame(['CREATED','UPDATED','DEACTIVATED','ACTIVATED'],array_column($events,'action'));
        }
    }

    public function testRequestKeepsCatalogSnapshotAndItemsRemainFreeText(): void
    {
        $tables=TableRegistry::getTableLocator();
        $requests=$tables->get('ServiceRequests');
        $requests->getConnection()->execute("SELECT setval(pg_get_serial_sequence('service_requests','id'),(SELECT MAX(id) FROM service_requests))");
        $assets=$this->createMock(\App\Service\AssetsLookupInterface::class);
        $assets->method('find')->willReturn(['code'=>'NOVO','branch'=>'01','description'=>'Bem de teste']);
        $row=['service_action_id'=>2,'service_component_id'=>2,'employee_registration'=>'006783',
            'service_date'=>'2026-11-04','start_time'=>'08:00','end_time'=>'09:00','description'=>'Serviço livre realizado na chapa.', 'service_action_name'=>'Forjado'];
        $request=(new RequestCreationService($assets,new \App\Test\Support\FakeEmployees()))->create([
            'asset_code'=>'NOVO','asset_branch'=>'01','sector_id'=>1,'unit'=>'USINA','maintenance_type'=>'CORRETIVA',
            'os_disposition'=>'MANTER_ABERTA','service_action_id'=>2,'service_component_id'=>2,
            'description'=>'Forjado','service_component_name'=>'Forjado','items'=>[$row,$row]],3);
        $request=$requests->get($request->id);
        $this->assertSame('Soldar chapa',$request->description);
        $this->assertSame(2,$request->service_action_id);
        $this->assertSame(2,$request->service_component_id);
        $items=$tables->get('RequestItems')->find()->where(['request_id'=>$request->id])->toArray();
        $this->assertCount(2,$items);
        $this->assertNull($items[0]->service_action_id);
        $this->assertNull($items[0]->service_component_id);
        $this->assertSame('Serviço livre realizado na chapa.',$items[0]->description);
        $audit=$tables->get('RequestAudits')->find()->where(['request_id'=>$request->id,'action'=>'REQUEST_CREATED'])->firstOrFail();
        $this->assertSame('Soldar chapa',$audit->new_values['description']);
        $this->assertSame('Serviço livre realizado na chapa.',$audit->new_values['items'][0]['description']);
        $tables->get('ServiceActions')->updateAll(['name'=>'Alterada','active'=>false],['id'=>2]);
        $tables->get('ServiceComponents')->updateAll(['name'=>'Alterado','active'=>false],['id'=>2]);
        $this->assertSame('Soldar chapa',$requests->get($request->id)->description);
        $this->assertSame('Serviço livre realizado na chapa.',$tables->get('RequestItems')->get($items[0]->id)->description);
        (new RequestHandlingService())->handle($request->id,1,'link','PAIR-OS-01');
        $before=$requests->get($request->id)->last_activity_at;
        (new RequestExecutionService(new \App\Test\Support\FakeEmployees()))->handle($request->id,3,'items',[$row]);
        $this->assertNotNull($requests->get($request->id)->last_activity_at);
        $this->assertSame(3,$tables->get('RequestItems')->find()->where(['request_id'=>$request->id])->count());
        $event=$tables->get('RequestAudits')->find()->where(['request_id'=>$request->id,'action'=>'EXECUTION_ITEMS_ADDED'])->firstOrFail();
        $this->assertSame('Serviço livre realizado na chapa.',$event->new_values['items'][0]['description']);
    }
}
