<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Domain\Enum\MaintenanceType;
use App\Domain\Enum\RequestStatus;
use App\Service\AssetsLookupInterface;
use App\Service\AttachmentService;
use App\Service\RequestCreationService;
use App\Service\RequestReportService;
use App\Service\UploadException;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Laminas\Diactoros\UploadedFile;

class PhaseFourTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Sectors', 'app.RegistrationAudits', 'app.ServiceRequests', 'app.RequestItems', 'app.RequestAudits', 'app.RequestAttachments'];
    private string $storage;
    private mixed $originalPath;

    public function setUp(): void
    {
        parent::setUp();
        $this->originalPath = Configure::read('Attachments.path');
        $this->storage = TMP . 'photos-test-' . bin2hex(random_bytes(8));
        Configure::write('Attachments.path', $this->storage);
        $this->mockService(AssetsLookupInterface::class, fn() => $this->assets());
        \Cake\Datasource\ConnectionManager::get('test')->execute("SELECT setval(pg_get_serial_sequence('service_requests', 'id'), (SELECT MAX(id) FROM service_requests))");
    }

    public function tearDown(): void
    {
        if ($this->_response) { $this->_response->getBody()->close(); }
        foreach (glob($this->storage . DS . '*') ?: [] as $file) { if (is_file($file)) { unlink($file); } }
        if (is_dir($this->storage)) { rmdir($this->storage); }
        if ($this->originalPath === null) { Configure::delete('Attachments.path'); }
        else { Configure::write('Attachments.path', $this->originalPath); }
        parent::tearDown();
    }

    private function assets(): AssetsLookupInterface
    {
        $fake = $this->createMock(AssetsLookupInterface::class);
        $fake->method('find')->willReturn(['code' => 'BEM01', 'branch' => '01', 'description' => 'Bomba de água']);

        return $fake;
    }

    private function photo(string $name = 'foto.png', ?int $size = null, ?string $content = null): UploadedFile
    {
        $content ??= base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j3i8AAAAASUVORK5CYII=');
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        return new UploadedFile($stream, $size ?? strlen($content), UPLOAD_ERR_OK, $name, 'image/png');
    }

    private function data(array $photos): array
    {
        return ['flow' => 'ACOMPANHAMENTO', 'os_disposition' => 'MANTER_ABERTA', 'confirm_existing' => '1', 'unit' => 'USINA', 'asset_code' => 'BEM01', 'asset_branch' => '01', 'sector_id' => 1,
            'maintenance_type' => MaintenanceType::CORRETIVA->value, 'description' => 'Pedido com fotos',
            'items' => [['description' => 'Serviço', 'executor' => 'Equipe externa', 'service_date' => '2026-10-07', 'start_time' => '08:00', 'end_time' => '09:00']], 'photos' => $photos];
    }

    public function testUploadDuringCreationStoresPhotosAndAudit(): void
    {
        $this->session(['Auth' => 3]);
        $this->enableCsrfToken('pcm_solicitacao_os_csrf');
        $this->post('/solicitacoes/nova', $this->data([$this->photo(), $this->photo('outra.png')]));
        $this->assertResponseCode(302);
        $photos = TableRegistry::getTableLocator()->get('RequestAttachments')->find()->toArray();
        $this->assertCount(2, $photos);
        foreach ($photos as $photo) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{48}\.png$/', $photo->stored_name);
            $this->assertFileExists($this->storage . DS . $photo->stored_name);
            $this->assertSame('image/png', $photo->mime_type);
            $this->assertSame(3, $photo->user_id);
        }
        $this->assertSame(2, TableRegistry::getTableLocator()->get('RequestAudits')->find()->where(['action' => 'ATTACHMENT_ADDED'])->count());
        foreach (TableRegistry::getTableLocator()->get('RequestAudits')->find()->where(['action' => 'ATTACHMENT_ADDED']) as $audit) {
            $this->assertSame(3, $audit->actor_snapshot['id']);
            $this->assertSame('user3', $audit->actor_snapshot['username']);
            $this->assertSame('AGUARDANDO_PCM', $audit->old_status);
            $this->assertSame('AGUARDANDO_PCM', $audit->new_status);
        }
    }

    public function testInvalidExtensionMimeSizeAndFileCountAreRejected(): void
    {
        foreach ([[$this->photo('arquivo.exe')], [$this->photo('foto.jpg')], [$this->photo('foto.png', AttachmentService::MAX_SIZE + 1)], [$this->photo('foto.png', null, '<?php echo 1;')], array_fill(0, 6, $this->photo())] as $photos) {
            try { (new AttachmentService())->validate($photos); $this->fail('Upload inválido deve ser rejeitado.'); }
            catch (UploadException $e) { $this->assertNotEmpty($e->getMessage()); }
        }
    }

    public function testInvalidUploadCannotCreatePartialRequest(): void
    {
        $this->session(['Auth' => 3]);
        $this->enableCsrfToken('pcm_solicitacao_os_csrf');
        $this->post('/solicitacoes/nova', $this->data([$this->photo('executavel.php')]));
        $this->assertResponseOk();
        $this->assertResponseContains('Outros tipos de arquivo não são permitidos');
        $this->assertSame(2, TableRegistry::getTableLocator()->get('ServiceRequests')->find()->count());
        $this->assertSame(0, TableRegistry::getTableLocator()->get('RequestAttachments')->find()->count());
    }

    public function testAttachmentAuthorization(): void
    {
        $request = (new RequestCreationService($this->assets()))->create(array_replace($this->data([$this->photo()]), ['flow' => 'SOMENTE_ABERTURA', 'os_disposition' => 'SOMENTE_ABERTURA', 'items' => []]), 2);
        $photo = TableRegistry::getTableLocator()->get('RequestAttachments')->find()->where(['request_id' => $request->id])->firstOrFail();
        $this->get('/fotos/' . $photo->id);
        $this->assertRedirect('/entrar');
        $this->session(['Auth' => 3]);
        $this->get('/fotos/' . $photo->id);
        $this->assertResponseCode(404);
        foreach ([1, 2] as $userId) {
            $this->session(['Auth' => $userId]);
            $this->get('/fotos/' . $photo->id);
            $this->assertResponseOk();
            $this->assertContentType('image/png');
            $this->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->_response->getBody()->close();
        }
    }

    public function testOwnerCanSeePhotoAndHumanTimeline(): void
    {
        $request = (new RequestCreationService($this->assets()))->create($this->data([$this->photo()]), 3);
        $photo = TableRegistry::getTableLocator()->get('RequestAttachments')->find()->firstOrFail();
        $this->session(['Auth' => 3]);
        $this->get('/fotos/' . $photo->id);
        $this->assertResponseOk();
        $this->_response->getBody()->close();
        $this->get('/solicitacoes/' . $request->id);
        $this->assertResponseOk();
        $this->assertResponseContains('class="timeline"');
        $this->assertResponseContains('criou a solicitação.');
        $this->assertResponseContains('adicionou uma foto: foto.png');
        $this->assertResponseNotContains('ATTACHMENT_ADDED');
    }

    public function testAttachmentFailureRollsBackDatabaseAndRemovesFiles(): void
    {
        $photos = TableRegistry::getTableLocator()->get('RequestAttachments');
        $photos->getValidator()->add('original_name', 'reject', ['rule' => fn() => false]);
        try {
            (new RequestCreationService($this->assets()))->create($this->data([$this->photo()]), 3);
            $this->fail('A falha de metadados deve desfazer a criação.');
        } catch (\Cake\ORM\Exception\PersistenceFailedException $e) {
            $this->assertSame(2, TableRegistry::getTableLocator()->get('ServiceRequests')->find()->count());
            $this->assertSame(0, $photos->find()->count());
            $this->assertSame([], glob($this->storage . DS . '*'));
        }
    }

    public function testIndicatorsCalculateGroupsAndAverage(): void
    {
        TableRegistry::getTableLocator()->get('ServiceRequests')->updateAll(['status' => RequestStatus::OS_ABERTA->value, 'os_linked_at' => '2026-10-07 14:00:00'], ['id' => 2]);
        $stats = (new RequestReportService())->indicators([]);
        $this->assertSame(2, $stats['total']);
        $this->assertCount(2, $stats['status']);
        $this->assertEquals(2, $stats['sector'][0]['total']);
        $this->assertCount(2, $stats['user']);
        $this->assertEquals(2, $stats['equipment'][0]['total']);
        $this->assertEquals(2, $stats['type'][0]['total']);
        $this->assertEquals(7200, $stats['average_seconds']);
        $empty = (new RequestReportService())->indicators(['start' => '2027-01-01']);
        $this->assertSame(0, $empty['total']);
        $this->assertNull($empty['average_seconds']);
    }

    public function testReportFiltersAndLiteralText(): void
    {
        $service = new RequestReportService();
        $this->assertSame(1, $service->query(['user_id' => '3', 'sector_id' => '1', 'asset' => 'BEM01', 'q' => 'teste 1', 'status' => RequestStatus::AGUARDANDO_PCM->value, 'start' => '2026-10-07', 'end' => '2026-10-07'])->count());
        $this->assertSame(0, $service->query(['q' => '%'])->count());
        $this->session(['Auth' => 2]);
        $this->get('/relatorios?user_id=3');
        $this->assertResponseOk();
        $this->assertResponseContains('SOL-2026-000001');
        $this->assertResponseNotContains('SOL-2026-000002');
        $this->get('/relatorios?start=2026-02-30');
        $this->assertResponseContains('Informe datas válidas');
    }

    public function testReportFiltersWorkIndependently(): void
    {
        TableRegistry::getTableLocator()->get('ServiceRequests')->updateAll([
            'status' => RequestStatus::OS_ABERTA->value, 'asset_code' => 'BEM02',
            'asset_description' => 'Motor', 'created_at' => '2026-10-06 03:00:00',
        ], ['id' => 2]);
        $service = new RequestReportService();
        foreach ([['status' => RequestStatus::OS_ABERTA->value], ['user_id' => '2'], ['asset' => 'motor'], ['q' => 'teste 2'], ['start' => '2026-10-06', 'end' => '2026-10-06']] as $filters) {
            $this->assertSame(2, $service->query($filters)->firstOrFail()->id);
            $this->assertSame(1, $service->query($filters)->count());
        }
        $this->assertSame(0, $service->query(['sector_id' => '999'])->count());
        $this->assertSame(2, $service->query(['sector_id' => '1'])->count());
    }

    public function testReportsAndIndicatorsRespectProfilesAndEmptyStates(): void
    {
        foreach ([1, 2] as $id) {
            $this->session(['Auth' => $id]);
            foreach (['/indicadores', '/relatorios'] as $url) { $this->get($url); $this->assertResponseOk(); }
        }
        $this->get('/indicadores?start=2027-01-01');
        $this->assertResponseContains('Nenhuma solicitação no período');
        $this->session(['Auth' => 3]);
        foreach (['/indicadores', '/relatorios'] as $url) { $this->get($url); $this->assertResponseCode(403); }
    }
}
