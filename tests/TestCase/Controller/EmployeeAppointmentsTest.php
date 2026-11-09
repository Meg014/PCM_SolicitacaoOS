<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Service\EmployeesLookupInterface;
use App\Service\EmployeesUnavailableException;
use App\Service\RequestHandlingService;
use App\Service\TotvsEmployeesService;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class EmployeeAppointmentsTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Sectors', 'app.RegistrationAudits', 'app.ServiceRequests',
        'app.Services', 'app.ServiceActions', 'app.ServiceComponents', 'app.RequestItems', 'app.RequestAudits', 'app.RequestAttachments', 'app.AuthorizedFunctions'];
    private EmployeesLookupInterface $employees;

    public function setUp(): void
    {
        parent::setUp();
        $this->employees = new class implements EmployeesLookupInterface {
            public bool $unavailable = false;
            public function search(string $term): array
            {
                if ($this->unavailable) { throw new EmployeesUnavailableException(); }
                return in_array($term, ['ABADIO', '006783'], true) ? [$this->find('006783')] : [];
            }
            public function find(string $registration): ?array
            {
                if ($this->unavailable) { throw new EmployeesUnavailableException(); }
                return $registration === '006783' ? ['registration' => '006783', 'name' => 'ABADIO',
                    'function_code' => '00005', 'function_name' => 'Mecânico', 'label' => '006783 - ABADIO - Mecânico'] : null;
            }
        };
        $this->mockService(EmployeesLookupInterface::class, fn() => $this->employees);
        $requests = TableRegistry::getTableLocator()->get('ServiceRequests');
        $requests->updateAll(['flow' => 'ACOMPANHAMENTO'], ['id' => 1]);
        (new RequestHandlingService())->handle(1, 1, 'link', '006001');
        $this->session(['Auth' => 3]);
    }

    private function row(): array
    {
        return ['employee_registration' => '006783', 'employee_label' => '006783 - ABADIO - Mecânico',
            'service_date' => '2026-11-03', 'start_time' => '08:00', 'end_time' => '09:30',
            'description' => 'Serviço realizado no equipamento'];
    }

    private function postRows(array $rows): void
    {
        $this->enableCsrfToken('pcm_solicitacao_os_csrf');
        $this->post('/solicitacoes/1/novo-apontamento', ['items' => $rows]);
    }

    public function testSearchEndpointIsPrivateValidatesInputAndHandlesUnavailableSource(): void
    {
        foreach (['ABADIO', '006783'] as $term) {
            $this->get('/api/funcionarios?q=' . $term); $this->assertResponseOk();
            $this->assertResponseContains('006783 - ABADIO - Mecânico');
            $this->assertHeader('Cache-Control', 'no-store');
        }
        $this->get('/api/funcionarios?q[]=invalid'); $this->assertResponseCode(400);
        $this->employees->unavailable = true;
        $this->get('/api/funcionarios?q=ABADIO'); $this->assertResponseCode(503);
        $this->session(['Auth' => null]);
        $this->get('/api/funcionarios?q=ABADIO'); $this->assertRedirect('/entrar');
    }

    public function testMultipleRowsUseServerSnapshotAndHistorySurvivesTotvsOutage(): void
    {
        $row = $this->row() + ['executor' => 'Forjado', 'employee_name' => 'Forjado', 'employee_function_name' => 'Forjado'];
        $this->postRows([$row, array_replace($row, ['start_time' => '10:00', 'end_time' => '11:00'])]);
        $this->assertRedirect('/solicitacoes/1');
        $items = TableRegistry::getTableLocator()->get('RequestItems')->find()->orderBy(['position' => 'ASC'])->toArray();
        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $this->assertSame('006783', $item->employee_registration);
            $this->assertSame('ABADIO', $item->employee_name);
            $this->assertSame('00005', $item->employee_function_code);
            $this->assertSame('Mecânico', $item->employee_function_name);
            $this->assertSame('ABADIO', $item->executor);
        }
        $audit = TableRegistry::getTableLocator()->get('RequestAudits')->find()->where(['action' => 'EXECUTION_ITEMS_ADDED'])->firstOrFail();
        $this->assertSame('006783', $audit->new_values['items'][0]['employee_registration']);
        $this->assertSame('00005', $audit->new_values['items'][0]['employee_function_code']);
        $this->assertSame('Mecânico', $audit->new_values['items'][0]['employee_function_name']);
        $request = TableRegistry::getTableLocator()->get('ServiceRequests')->get(1);
        $this->assertNotNull($request->last_activity_at);
        $this->assertNotNull($request->last_item_at);
        $this->employees->unavailable = true;
        $this->get('/solicitacoes/1'); $this->assertResponseOk();
        $this->assertResponseContains('006783 - ABADIO - Mecânico');
    }

    public function testEachAddedRowRequiresAllFieldsAndSelection(): void
    {
        $before = TableRegistry::getTableLocator()->get('ServiceRequests')->get(1)->last_activity_at;
        foreach (['employee_registration', 'service_date', 'start_time', 'end_time', 'description'] as $field) {
            $row = $this->row(); unset($row[$field]);
            $this->postRows([$this->row(), $row]); $this->assertResponseOk();
        }
        $this->postRows([array_replace($this->row(), ['employee_registration' => 'Livre'])]); $this->assertResponseOk();
        $this->postRows([['_added' => '1']]); $this->assertResponseOk();
        $this->employees->unavailable = true;
        $this->postRows([$this->row()]); $this->assertResponseOk();
        $this->assertResponseContains('Não foi possível consultar os funcionários');
        $this->assertSame(0, TableRegistry::getTableLocator()->get('RequestItems')->find()->count());
        $this->assertEquals($before, TableRegistry::getTableLocator()->get('ServiceRequests')->get(1)->last_activity_at);
    }

    public function testOnlyActiveLocalFunctionsAreSentToTotvs(): void
    {
        $service = $this->getMockBuilder(TotvsEmployeesService::class)->onlyMethods(['read'])->getMock();
        $service->expects($this->once())->method('read')->with($this->anything(),
            ['name_term' => '%AB%', 'registration_term' => '%AB%', 'function_0' => '00005'])->willReturn([]);
        $this->assertSame([], $service->search('AB'));
    }

    public function testSpreadsheetFunctionMatchesTotvsAndPersistsCanonicalSnapshot(): void
    {
        TableRegistry::getTableLocator()->get('AuthorizedFunctions')->updateAll(['code' => '32', 'name' => 'ELETRICISTA SR'], ['id' => 1]);
        $service = $this->getMockBuilder(TotvsEmployeesService::class)->onlyMethods(['read'])->getMock();
        $service->expects($this->once())->method('read')->with($this->anything(),
            ['registration' => '006783', 'function_0' => '00032'])->willReturn([
                ['registration' => '006783', 'name' => 'JOÃO DA SILVA', 'function_code' => '00032', 'situation' => 'F'],
            ]);
        $this->employees = $service;
        $this->postRows([$this->row()]);
        $this->assertRedirect('/solicitacoes/1');
        $item = TableRegistry::getTableLocator()->get('RequestItems')->find()->firstOrFail();
        $this->assertSame('006783', $item->employee_registration);
        $this->assertSame('JOÃO DA SILVA', $item->employee_name);
        $this->assertSame('00032', $item->employee_function_code);
        $this->assertSame('ELETRICISTA SR', $item->employee_function_name);
        $this->get('/solicitacoes/1');
        $this->assertResponseContains('006783 - JOÃO DA SILVA - ELETRICISTA SR');
    }
}
