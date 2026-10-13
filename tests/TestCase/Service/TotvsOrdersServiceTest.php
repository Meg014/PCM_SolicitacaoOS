<?php
declare(strict_types=1);
namespace App\Test\TestCase\Service;

use App\Service\TotvsOrderQueries;
use App\Service\TotvsOrdersService;
use PHPUnit\Framework\TestCase;

class TotvsOrdersServiceTest extends TestCase
{
    public function testReadOnlyAllowlistUsesBoundBatchParametersAndRejectsInjectedSql(): void
    {
        foreach ([1, 25, 50] as $size) {
            $sql = TotvsOrderQueries::batch($size);
            $this->assertTrue(TotvsOrderQueries::allows($sql));
            $this->assertSame($size, substr_count($sql, ':number_'));
            $this->assertFalse(TotvsOrderQueries::allows($sql . '; DELETE FROM dbo.STJ010'));
        }
        $this->assertTrue(TotvsOrderQueries::allows(TotvsOrderQueries::DETAIL));
        foreach (['SELECT * FROM dbo.STJ010', 'UPDATE dbo.STJ010 SET TJ_TERMINO = 1', 'EXEC encerrar'] as $sql) {
            $driver = new \App\Database\Driver\ProtheusReadOnly();
            try { $driver->prepare($sql); $this->fail('Consulta arbitrária aceita'); }
            catch (\LogicException $e) { $this->assertFalse(TotvsOrderQueries::allows($sql)); }
        }
        $service = $this->getMockBuilder(TotvsOrdersService::class)->onlyMethods(['read'])->getMock();
        $service->expects($this->once())->method('read')->with(TotvsOrderQueries::batch(2), ['number_0' => '005874', 'number_1' => 'OS002'])->willReturn([]);
        $this->assertSame([], $service->batch(['005874', '005874', 'OS002']));
    }
    public function testDetailRejectsAmbiguousIdentityMasterRecordsAndWrongEquipment(): void
    {
        $valid = ['number' => '005874', 'branch' => '01', 'asset_code' => 'BEM01', 'identity_count' => 1,
            'asset_matches' => 1, 'service_matches' => 1];
        foreach ([[], [$valid, $valid], [array_replace($valid, ['identity_count' => 2])],
            [array_replace($valid, ['asset_matches' => 2])], [array_replace($valid, ['branch' => '02'])],
            [array_replace($valid, ['asset_code' => 'OUTRO'])]] as $rows) {
            $service = $this->getMockBuilder(TotvsOrdersService::class)->onlyMethods(['read'])->getMock();
            $service->method('read')->willReturn($rows); $this->assertNull($service->find('005874', '01', 'BEM01'));
        }
        $service = $this->getMockBuilder(TotvsOrdersService::class)->onlyMethods(['read'])->getMock();
        $service->method('read')->willReturn([$valid]); $this->assertSame($valid, $service->find('005874', '01', 'BEM01'));
    }
    public function testTruncatedBatchIsInconclusive(): void
    {
        $service = $this->getMockBuilder(TotvsOrdersService::class)->onlyMethods(['read'])->getMock();
        $service->method('read')->willReturn(array_fill(0, 101, ['number' => '005874']));
        $this->expectException(\App\Service\OrdersUnavailableException::class); $service->batch(['005874']);
    }
}
