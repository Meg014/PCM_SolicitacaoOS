<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class ServicesControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Services', 'app.ServiceActions', 'app.ServiceComponents', 'app.RegistrationAudits'];

    public function testAdminCanCreateEditAndToggleServiceWithRegistrationAudit(): void
    {
        $this->session(['Auth' => 1]);
        \Cake\Datasource\ConnectionManager::get('test')->execute(
            "SELECT setval(pg_get_serial_sequence('services', 'id'), (SELECT MAX(id) FROM services))",
        );
        $this->get('/servicos');
        $this->assertResponseOk();
        $this->assertResponseContains('Serviços padronizados');

        $this->enableCsrfToken('pcm_solicitacao_os_csrf');
        $this->post('/servicos/novo', ['name' => '  Inspeção geral  ', 'active' => '1']);
        $this->assertRedirect('/servicos');
        $service = TableRegistry::getTableLocator()->get('Services')->find()->where(['name' => 'Inspeção geral'])->firstOrFail();
        $this->assertTrue($service->active);

        $this->enableCsrfToken('pcm_solicitacao_os_csrf');
        $this->post('/servicos/' . $service->id . '/editar', ['name' => 'Inspeção elétrica', 'active' => '1']);
        $this->assertRedirect('/servicos');
        $this->assertSame('Inspeção elétrica', TableRegistry::getTableLocator()->get('Services')->get($service->id)->name);

        $this->enableCsrfToken('pcm_solicitacao_os_csrf');
        $this->post('/servicos/' . $service->id . '/situacao');
        $this->assertRedirect('/servicos');
        $this->assertFalse(TableRegistry::getTableLocator()->get('Services')->get($service->id)->active);

        $audits = TableRegistry::getTableLocator()->get('RegistrationAudits')->find()
            ->where(['resource' => 'Services'])->orderBy(['id' => 'ASC'])->all()->toArray();
        $this->assertSame(['CREATED', 'UPDATED', 'DEACTIVATED'], array_column($audits, 'action'));
    }

    public function testOtherProfilesCannotManageServices(): void
    {
        $this->session(['Auth' => 2]);
        $this->get('/servicos');
        $this->assertResponseCode(403);
    }
}
