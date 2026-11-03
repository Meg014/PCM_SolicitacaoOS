<?php
declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Service\AuthorizedFunctionsImport;
use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

class AuthorizedFunctionsImportTest extends TestCase
{
    use ConsoleIntegrationTestTrait;
    protected array $fixtures = ['app.Users', 'app.RegistrationAudits'];
    private array $paths = [];

    protected function tearDown(): void
    {
        TableRegistry::getTableLocator()->get('AuthorizedFunctions')->deleteAll([]);
        foreach ($this->paths as $path) { unlink($path); }
        parent::tearDown();
    }

    private function workbook(array $rows): string
    {
        $path = tempnam(TMP, 'functions-');
        unlink($path);
        $path .= '.xlsx';
        $this->paths[] = $path;
        $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach (array_merge([['Funcao', 'Descricao']], $rows) as $index => $row) {
            $number = $index + 1;
            $xml .= '<row r="' . $number . '">';
            foreach (['A', 'B'] as $col => $letter) {
                $xml .= '<c r="' . $letter . $number . '" t="inlineStr"><is><t>'
                    . htmlspecialchars($row[$col], ENT_XML1, 'UTF-8') . '</t></is></c>';
            }
            $xml .= '</row>';
        }
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml . '</sheetData></worksheet>');
        $zip->close();
        return $path;
    }

    public function testPreservesLeadingZerosAndDeduplicates(): void
    {
        $path = $this->workbook([['00005', 'Mecânico'], ['0005', 'Mecânico'], ['5', 'Mecânico'], ['32', 'Eletricista']]);
        $rows = (new AuthorizedFunctionsImport())->read($path);
        $this->assertSame([['code' => '00005', 'name' => 'Mecânico'], ['code' => '00032', 'name' => 'Eletricista']], $rows);
    }

    public function testConflictingDescriptionsRejectEntireFile(): void
    {
        $path = $this->workbook([['5', 'Mecânico'], ['00005', 'Eletricista']]);
        $this->expectException(\InvalidArgumentException::class);
        (new AuthorizedFunctionsImport())->import($path, 1);
    }

    public function testEmptyDescriptionIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new AuthorizedFunctionsImport())->read($this->workbook([['05', '']]));
    }

    public function testEmptyListIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new AuthorizedFunctionsImport())->read($this->workbook([]));
    }

    public function testImportUpdateReplaceAndAudit(): void
    {
        $service = new AuthorizedFunctionsImport();
        $first = $this->workbook([['0005', 'Mecânico'], ['0019', 'Auxiliar']]);
        $this->assertSame(2, $service->import($first, 1)['created']);
        $this->assertSame(2, $service->import($first, 1)['unchanged']);
        $next = $this->workbook([['0005', 'Mecânico atualizado']]);
        $counts = $service->import($next, 1, true);
        $this->assertSame(1, $counts['updated']);
        $this->assertSame(1, $counts['deactivated']);
        $tables = TableRegistry::getTableLocator();
        $table = $tables->get('AuthorizedFunctions');
        $this->assertSame(2, $table->find()->count());
        $this->assertSame('Mecânico atualizado', $table->find()->where(['code' => '00005'])->firstOrFail()->name);
        $this->assertFalse($table->find()->where(['code' => '00019'])->firstOrFail()->active);
        $audits = $tables->get('RegistrationAudits')->find()->where(['resource' => 'AuthorizedFunctions']);
        $this->assertSame(4, $audits->count());
        foreach ($audits as $audit) {
            $this->assertSame(1, $audit->user_id);
            $this->assertNotNull($audit->created_at);
        }
        $this->assertSame(2, $service->import($first, 1)['updated']);
        $this->assertSame(2, $table->find()->where(['active' => true])->count());
    }

    public function testNonAdminCannotImport(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new AuthorizedFunctionsImport())->import($this->workbook([['05', 'Mecânico']]), 2);
    }

    public function testInactiveAdminCannotImport(): void
    {
        TableRegistry::getTableLocator()->get('Users')->updateAll(['active' => false], ['id' => 1]);
        $this->expectException(\InvalidArgumentException::class);
        (new AuthorizedFunctionsImport())->import($this->workbook([['05', 'Mecânico']]), 1);
    }

    public function testCommandPreviewDoesNotWrite(): void
    {
        $path = $this->workbook([['0005', 'Mecânico']]);
        $this->exec('import_authorized_functions "' . $path . '" --dry-run');
        $this->assertExitSuccess();
        $this->assertOutputContains('Funções válidas: 1');
        $this->assertSame(0, TableRegistry::getTableLocator()->get('AuthorizedFunctions')->find()->count());
    }

    public function testCommandRequiresResponsibleAdmin(): void
    {
        $path = $this->workbook([['0005', 'Mecânico']]);
        $this->exec('import_authorized_functions "' . $path . '"');
        $this->assertExitError();
        $this->assertErrorContains('Informe --user-id');
    }
}
