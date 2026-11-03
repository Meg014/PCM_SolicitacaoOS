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

    public function testSpreadsheetCodeIsPaddedForSearchAndRevalidation(): void
    {
        $service = $this->getMockBuilder(TotvsEmployeesService::class)->onlyMethods(['read', 'functions'])->getMock();
        $service->method('functions')->willReturn([32 => 'ELETRICISTA SR', '00032' => 'ELETRICISTA SR']);
        $service->expects($this->exactly(3))->method('read')->willReturnCallback(function ($sql, $params) {
            $this->assertSame('00032', $params['function_0']);
            $this->assertArrayNotHasKey('function_1', $params);
            return [array_replace($this->row(), ['function_code' => '00032'])];
        });
        foreach (['ABADIO', '006783'] as $term) {
            $this->assertSame('006783 - ABADIO OLIVEIRA - ELETRICISTA SR', $service->search($term)[0]['label']);
        }
        $this->assertSame('00032', $service->find('006783')['function_code']);
    }

    public function testConflictingEquivalentLocalCodesFailClosed(): void
    {
        $service = $this->getMockBuilder(TotvsEmployeesService::class)->onlyMethods(['read', 'functions'])->getMock();
        $service->method('functions')->willReturn([32 => 'Mecânico', '00032' => 'Eletricista']);
        $service->expects($this->never())->method('read');
        $this->expectException(EmployeesUnavailableException::class);
        $service->search('AB');
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

    public function testSearchUsesEntressafraWildcardEscaping(): void
    {
        $service = $this->getMockBuilder(TotvsEmployeesService::class)->onlyMethods(['read', 'functions'])->getMock();
        $service->method('functions')->willReturn(['00005' => 'Mecânico']);
        $service->expects($this->once())->method('read')->with(TotvsEmployeeQueries::lookup(1),
            ['name_term' => '%A[%][_][[]%', 'registration_term' => '%A[%][_][[]%', 'function_0' => '00005'])
            ->willReturn([$this->row()]);

        $this->assertSame('006783 - ABADIO OLIVEIRA - Mecânico', $service->search('A%_[')[0]['label']);
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
                $this->assertStringContainsString('FROM DADOSP12.dbo.SRA010', $sql);
                $this->assertStringContainsString("IN ('', 'F')", $sql);
                if ($find) {
                    $this->assertStringContainsString('RTRIM(RA_MAT) = :registration', $sql);
                } else {
                    $this->assertStringContainsString('UPPER(RA_NOME) LIKE UPPER(:name_term)', $sql);
                    $this->assertStringContainsString('UPPER(RA_MAT) LIKE UPPER(:registration_term)', $sql);
                }
                $this->assertStringNotContainsString('RA_FILIAL', $sql);
                $this->assertFalse(TotvsEmployeeQueries::allows($sql . '; DELETE FROM DADOSP12.dbo.SRA010'));
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
