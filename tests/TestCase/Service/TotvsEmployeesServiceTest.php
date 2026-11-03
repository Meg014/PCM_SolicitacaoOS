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
            $service->expects($this->once())->method('read')->with(TotvsEmployeeQueries::lookup(1),
                ['name_term' => '%' . $term . '%', 'registration_term' => '%' . $term . '%', 'function_0' => '00005'])->willReturn([$this->row()]);
            $result = $service->search($term);
            $this->assertSame('006783 - ABADIO OLIVEIRA - Mecânico', $result[0]['label']);
            $this->assertSame('00005', $result[0]['function_code']);
        }
    }

    public function testNormalAndVacationOnlyWithExactActiveFunctionCode(): void
    {
        foreach (['', ' ', 'F', 'D', 'A', 'X'] as $situation) {
            $this->assertCount(in_array(trim($situation), ['', 'F'], true) ? 1 : 0,
                $this->service([$this->row($situation)])->search('AB'));
        }
        foreach (['5', '99999'] as $code) {
            $this->assertSame([], $this->service([array_replace($this->row(), ['function_code' => $code])])->search('AB'));
        }
    }

    public function testNoFunctionsOrInvalidTermDoesNotQueryTotvs(): void
    {
        $service = $this->getMockBuilder(TotvsEmployeesService::class)->onlyMethods(['read', 'functions'])->getMock();
        $service->method('functions')->willReturn([]);
        $service->expects($this->never())->method('read');
        foreach (['', 'a', str_repeat('x', 61), 'AB'] as $term) { $this->assertSame([], $service->search($term)); }
    }

    public function testFindRevalidatesAndRejectsAmbiguousRegistration(): void
    {
        $this->assertSame('006783', $this->service([$this->row()])->find('006783')['registration']);
        $this->assertNull($this->service([$this->row(), $this->row()])->find('006783'));
        $this->assertNull($this->service([$this->row('D')])->find('006783'));
    }

    public function testExactReadOnlyQueriesAndSituationPredicate(): void
    {
        foreach ([1, 45, 500] as $count) {
            foreach ([true, false] as $find) {
                $sql = TotvsEmployeeQueries::lookup($count, $find);
                $this->assertTrue(TotvsEmployeeQueries::allows($sql));
                $this->assertStringContainsString("IN ('', 'F')", $sql);
                $this->assertStringNotContainsString('RA_FILIAL', $sql);
                $this->assertFalse(TotvsEmployeeQueries::allows($sql . '; DELETE FROM dbo.SRA010'));
                $this->assertFalse(TotvsEmployeeQueries::allows(str_replace("IN ('', 'F')", "IN ('D')", $sql)));
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
