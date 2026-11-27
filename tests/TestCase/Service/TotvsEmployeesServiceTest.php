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
                ['name_term' => '%' . $term . '%', 'registration_term' => '%' . $term . '%',
                    'exact_registration' => $term, 'exact_name' => $term])->willReturn([$this->row()]);
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
            ['name_term' => '%A[%][_][[]%', 'registration_term' => '%A[%][_][[]%', 'exact_registration' => 'A%_[', 'exact_name' => 'A%_['])
            ->willReturn([$this->row()]);

        $this->assertSame('006783 - ABADIO OLIVEIRA - Mecânico', $service->search('A%_[')[0]['label']);
    }

    public function testNonDismissedSituationsAndUnlistedFunctionsRemainEligible(): void
    {
        foreach (['', ' ', 'F', 'A', 'X'] as $situation) {
            $service = $this->getMockBuilder(TotvsEmployeesService::class)->onlyMethods(['read', 'functions'])->getMock();
            $service->method('functions')->willReturn([]);
            $service->method('read')->willReturn([array_replace($this->row($situation), ['function_code' => '00020'])]);
            $result = $service->search('AB');
            $this->assertCount(1, $result);
            $this->assertSame('006783 - ABADIO OLIVEIRA', $result[0]['label']);
            $this->assertSame('00020', $result[0]['function_code']);
            $this->assertSame($result[0], $service->find('006783'));
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
        $this->assertSame([], $service->search('   '));
    }

    public function testFindRevalidatesAndRejectsAmbiguousRegistration(): void
    {
        $this->assertSame('006783', $this->service([$this->row()])->find('006783')['registration']);
        $this->assertNull($this->service([$this->row(), $this->row()])->find('006783'));
        $service = $this->service([$this->row(), array_replace($this->row('D'), ['name' => 'OUTRO FUNCIONÁRIO'])]);
        $this->assertNull($service->find('006783'));
        $selected = $service->search('OUTRO')[1];
        $this->assertSame('OUTRO FUNCIONÁRIO', $service->find('006783', $selected['selection_key'])['name']);
    }

    public function testLookupFiltersOnlyDismissedEmployeesAndPrioritizesExactMatches(): void
    {
        foreach ([true, false] as $find) {
            $sql = TotvsEmployeeQueries::lookup($find);
            $this->assertTrue(TotvsEmployeeQueries::allows($sql));
            $this->assertStringContainsString('FROM DADOSP12.dbo.SRA010', $sql);
            $this->assertStringContainsString("ISNULL(RTRIM(RA_SITFOLH), '') <> 'D'", $sql);
            $this->assertStringNotContainsString('authorized_functions', $sql);
            $this->assertStringNotContainsString('RA_CODFUNC IN', $sql);
            $this->assertFalse(TotvsEmployeeQueries::allows($sql . ' AND RA_CODFUNC = 1'));
        }
        $this->assertStringContainsString("AND RTRIM(RA_MAT) = :registration", TotvsEmployeeQueries::lookup(true));
        $this->assertStringContainsString('ORDER BY CASE WHEN RTRIM(RA_MAT) = :exact_registration', TotvsEmployeeQueries::lookup());
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
