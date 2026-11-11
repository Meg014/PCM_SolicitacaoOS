<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\TotvsEmployeeQueries;
use App\Service\TotvsEmployeesService;
use App\Service\EmployeesUnavailableException;
use PHPUnit\Framework\TestCase;

class TotvsEmployeesServiceTest extends TestCase
{
    private function service(array $rows): TotvsEmployeesService
    {
        $service = $this->getMockBuilder(TotvsEmployeesService::class)->onlyMethods(['read', 'functions'])->getMock();
        $service->method('functions')->willReturn(['00005' => 'Mecânico']);
        $service->method('read')->willReturn($rows);
        return $service;
    }

    private function row(string $situation = ''): array
    {
        return ['registration' => '006783', 'name' => 'ABADIO OLIVEIRA', 'function_code' => '00005', 'situation' => $situation];
    }

    public function testSearchByNameAndRegistrationUsesBoundParameters(): void
    {
        foreach (['ABADIO', '006783'] as $term) {
            $service = $this->getMockBuilder(TotvsEmployeesService::class)->onlyMethods(['read', 'functions'])->getMock();
            $service->method('functions')->willReturn(['00005' => 'Mecânico']);
            $service->expects($this->once())->method('read')->with(TotvsEmployeeQueries::lookup(),
                ['name_term' => '%' . $term . '%', 'registration_term' => '%' . $term . '%'])->willReturn([$this->row()]);
            $result = $service->search($term);
            $this->assertSame('006783 - ABADIO OLIVEIRA - Mecânico', $result[0]['label']);
            $this->assertSame('00005', $result[0]['function_code']);
        }
    }

    public function testSearchUsesEntressafraWildcardEscaping(): void
    {
        $service = $this->getMockBuilder(TotvsEmployeesService::class)->onlyMethods(['read', 'functions'])->getMock();
        $service->method('functions')->willReturn(['00005' => 'Mecânico']);
        $service->expects($this->once())->method('read')->with(TotvsEmployeeQueries::lookup(),
            ['name_term' => '%A[%][_][[]%', 'registration_term' => '%A[%][_][[]%'])
            ->willReturn([$this->row()]);

        $this->assertSame('006783 - ABADIO OLIVEIRA - Mecânico', $service->search('A%_[')[0]['label']);
    }

    public function testSituationsAndFunctionsDoNotRestrictEligibleEmployees(): void
    {
        foreach (['', ' ', 'F', 'A', 'D', 'X'] as $situation) {
            $service = $this->service([array_replace($this->row($situation), ['function_code' => '99999'])]);
            $result = $service->search('AB');
            $eligible = in_array(trim($situation), ['', 'F', 'A'], true);
            $this->assertCount($eligible ? 1 : 0, $result);
            $this->assertSame($eligible, $service->find('006783') !== null);
            if ($eligible) {
                $this->assertSame(trim($situation), $result[0]['situation']);
                $suffix = match (trim($situation)) { 'F' => ' - FÉRIAS', 'A' => ' - AFASTADO', default => '' };
                $this->assertSame('006783 - ABADIO OLIVEIRA' . $suffix, $result[0]['label']);
            }
        }
    }

    public function testEmptyOrUnavailableFunctionCatalogDoesNotBlockLookup(): void
    {
        foreach ([false, true] as $unavailable) {
            $service = $this->getMockBuilder(TotvsEmployeesService::class)->onlyMethods(['read', 'functions'])->getMock();
            if ($unavailable) {
                $service->method('functions')->willThrowException(new \RuntimeException('catalog unavailable'));
            } else {
                $service->method('functions')->willReturn([]);
            }
            $service->method('read')->willReturn([$this->row()]);
            $this->assertSame('006783', $service->search('AB')[0]['registration']);
        }
    }

    public function testInvalidTermDoesNotQueryTotvs(): void
    {
        $service = $this->getMockBuilder(TotvsEmployeesService::class)->onlyMethods(['read'])->getMock();
        $service->expects($this->never())->method('read');
        foreach (['', 'a', str_repeat('x', 61)] as $term) { $this->assertSame([], $service->search($term)); }
    }

    public function testFindRevalidatesAndRejectsAmbiguousRegistration(): void
    {
        $this->assertSame('006783', $this->service([$this->row()])->find('006783')['registration']);
        $this->assertNull($this->service([$this->row(), $this->row()])->find('006783'));
        $this->assertNull($this->service([$this->row('D')])->find('006783'));
    }

    public function testExactReadOnlyQueriesAndSituationPredicate(): void
    {
        foreach ([1] as $count) {
            foreach ([true, false] as $find) {
                $sql = TotvsEmployeeQueries::lookup($find);
                $this->assertTrue(TotvsEmployeeQueries::allows($sql));
                $this->assertStringContainsString('FROM DADOSP12.dbo.SRA010', $sql);
                $this->assertStringContainsString("IN ('', 'F', 'A')", $sql);
                if ($find) {
                    $this->assertStringContainsString('RTRIM(RA_MAT) = :registration', $sql);
                } else {
                    $this->assertStringContainsString('UPPER(RA_NOME) LIKE UPPER(:name_term)', $sql);
                    $this->assertStringContainsString('UPPER(RA_MAT) LIKE UPPER(:registration_term)', $sql);
                }
                $this->assertStringNotContainsString('RA_CODFUNC IN', $sql);
                $this->assertStringNotContainsString('RA_FILIAL', $sql);
                $this->assertFalse(TotvsEmployeeQueries::allows($sql . '; DELETE FROM DADOSP12.dbo.SRA010'));
                $this->assertFalse(TotvsEmployeeQueries::allows(str_replace("IN ('', 'F', 'A')", "IN ('D')", $sql)));
            }
        }
    }

    public function testUnavailableLookupDoesNotLeakDriverDetails(): void
    {
        $service = $this->getMockBuilder(TotvsEmployeesService::class)->onlyMethods(['read', 'functions'])->getMock();
        $service->method('functions')->willReturn(['00005' => 'Mecânico']);
        $service->method('read')->willThrowException(new \RuntimeException('driver secret'));
        $this->expectException(EmployeesUnavailableException::class);
        $this->expectExceptionMessage('Não foi possível consultar os funcionários');
        $service->search('AB');
    }
}
