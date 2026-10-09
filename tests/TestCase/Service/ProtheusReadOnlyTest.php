<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Database\Driver\ProtheusReadOnly;
use App\Service\TotvsAssetQueries;
use App\Service\TotvsAssetsService;
use PHPUnit\Framework\TestCase;

class ProtheusReadOnlyTest extends TestCase
{
    public function testWritesAndArbitrarySelectsAreRejectedWithoutConnecting(): void
    {
        $driver = new ProtheusReadOnly();
        foreach (['INSERT INTO dbo.ST9010 DEFAULT VALUES', 'UPDATE dbo.ST9010 SET T9_NOME = 1', 'DELETE FROM dbo.ST9010', 'MERGE dbo.ST9010', 'ALTER TABLE dbo.ST9010', 'DROP TABLE dbo.ST9010', 'EXEC procedure', 'SELECT 1', TotvsAssetQueries::FIND . '; DELETE FROM dbo.ST9010'] as $sql) {
            try {
                $driver->prepare($sql);
                $this->fail('A consulta deveria ser bloqueada.');
            } catch (\LogicException $e) {
                $this->assertFalse(TotvsAssetQueries::allows($sql));
            }
        }
        $this->assertTrue(TotvsAssetQueries::allows(TotvsAssetQueries::SEARCH));
        $this->assertTrue(TotvsAssetQueries::allows(TotvsAssetQueries::FIND));
    }

    public function testDirectExecIsBlocked(): void
    {
        $this->expectException(\LogicException::class);
        (new ProtheusReadOnly())->exec('SELECT 1');
    }

    public function testEmptyAndOversizedSearchDoNotConnect(): void
    {
        $service = new TotvsAssetsService();
        $this->assertSame([], $service->search('a'));
        $this->assertSame([], $service->search(str_repeat('a', 61)));
        $this->assertNull($service->find('', ''));
        $this->assertNull($service->find(str_repeat('a', 101), ''));
    }

    public function testAccentInsensitiveQueryReturnsOriginalAssetDescription(): void
    {
        // Fake da consulta, sem conectar ao TOTVS: verifica SQL, parâmetros e preservação do retorno.
        $service = $this->getMockBuilder(TotvsAssetsService::class)->onlyMethods(['read'])->getMock();
        $service->expects($this->exactly(2))->method('read')->with(
            $this->callback(fn($sql) => $sql === TotvsAssetQueries::SEARCH && substr_count($sql, 'COLLATE Latin1_General_100_CI_AI') === 2),
            $this->callback(fn($params) => in_array($params['description_term'], ['%centrifuga%', '%CENTRÍFUGA%'], true) && $params['code_term'] === $params['description_term']),
        )->willReturn([['code' => 'CENT01', 'description' => 'Centrífuga', 'branch' => '01']]);
        foreach (['centrifuga', 'CENTRÍFUGA'] as $term) {
            $rows = $service->search($term);
            $this->assertSame('Centrífuga', $rows[0]['description']);
            $this->assertSame('CENT01', $rows[0]['code']);
            $this->assertSame('01', $rows[0]['branch']);
        }
    }
}
