<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Database\Driver\ProtheusReadOnly;
use App\Service\TotvsEmployeeQueries;
use PHPUnit\Framework\TestCase;

class TotvsEmployeeQueriesTest extends TestCase
{
    public function testDiagnosticsOnlyAllowExactQueriesWithoutEmployeeRows(): void
    {
        $this->assertTrue(TotvsEmployeeQueries::allows(TotvsEmployeeQueries::COLUMNS));
        $this->assertTrue(TotvsEmployeeQueries::allows(TotvsEmployeeQueries::ACCESS));
        $this->assertStringContainsString('TOP (0)', TotvsEmployeeQueries::ACCESS);
        foreach ([str_replace('TOP (0)', 'TOP (1)', TotvsEmployeeQueries::ACCESS),
            TotvsEmployeeQueries::ACCESS . '; DELETE FROM DADOSP12.dbo.SRA010',
            'SELECT * FROM DADOSP12.dbo.SRA010', 'UPDATE DADOSP12.dbo.SRA010 SET RA_NOME = NULL'] as $sql) {
            $this->assertFalse(TotvsEmployeeQueries::allows($sql));
            try {
                (new ProtheusReadOnly())->prepare($sql);
                $this->fail('Consulta fora da lista deve ser bloqueada antes da conexão.');
            } catch (\LogicException $e) {
                $this->assertStringContainsString('fora da lista', $e->getMessage());
            }
        }
    }
}
