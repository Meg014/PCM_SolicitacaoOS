<?php
declare(strict_types=1);
namespace App\Test\TestCase\Command;

use App\Service\OrdersLookupInterface;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

class SyncTotvsOrdersCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;
    protected array $fixtures = ['app.Users', 'app.Sectors', 'app.ServiceRequests', 'app.RequestItems', 'app.RequestAudits'];
    public function testCommandUsesFakeAndDoesNotConsultOpeningOnlyOrUnlinkedOrders(): void
    {
        $fake = $this->createMock(OrdersLookupInterface::class); $fake->expects($this->never())->method('batch');
        $this->mockService(OrdersLookupInterface::class, fn() => $fake);
        $this->exec('sync_totvs_orders'); $this->assertExitSuccess(); $this->assertOutputContains('Verificadas: 0');
    }
    public function testCommandSynchronizesOnlyNeededNumbersInBatch(): void
    {
        TableRegistry::getTableLocator()->get('ServiceRequests')->updateAll(['flow' => 'ACOMPANHAMENTO', 'status' => 'EM_EXECUCAO', 'totvs_os_number' => 'OS001'], ['id' => 1]);
        $fake = $this->createMock(OrdersLookupInterface::class);
        $fake->expects($this->once())->method('batch')->with(['OS001'])->willReturn([
            ['number' => 'OS001', 'branch' => '01', 'asset_code' => 'BEM01', 'situation' => 'L', 'finished' => 'S']]);
        $this->mockService(OrdersLookupInterface::class, fn() => $fake);
        $this->exec('sync_totvs_orders'); $this->assertExitSuccess(); $this->assertOutputContains('Alteradas: 1');
        $this->assertSame('ENCERRADA', TableRegistry::getTableLocator()->get('ServiceRequests')->get(1)->status);
    }
}
