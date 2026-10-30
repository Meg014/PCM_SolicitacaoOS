<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Domain\Enum\UserRole;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class FunctionalRolesTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = [
        'app.UserDepartments', 'app.Users', 'app.Sectors', 'app.RegistrationAudits',
        'app.ServiceRequests', 'app.RequestItems', 'app.RequestAudits', 'app.RequestAttachments',
    ];

    public function testOnlyThreeFriendlyProfilesAreAvailable(): void
    {
        $this->assertSame([
            'ADMIN_PCM' => 'Administrador PCM', 'PCM' => 'PCM', 'MANTENEDOR' => 'Mantenedor',
        ], UserRole::options());
        foreach (['SOLICITANTE', 'GESTOR', 'ENCARREGADO', 'INVALID'] as $legacy) {
            $this->assertNull(UserRole::tryFrom($legacy));
        }
    }

    public function testAccessMatrix(): void
    {
        foreach (UserRole::cases() as $role) {
            TableRegistry::getTableLocator()->get('Users')->updateAll(['role' => $role->value], ['id' => 2]);
            $this->session(['Auth' => 2]);
            foreach (['/usuarios', '/setores', '/setores-usuarios'] as $url) {
                $this->get($url);
                $this->assertResponseCode($role === UserRole::ADMIN_PCM ? 200 : 403);
            }
            foreach (['/painel', '/relatorios', '/indicadores'] as $url) {
                $this->get($url);
                $this->assertResponseCode($role->canHandleRequests() ? 200 : 403);
            }
            $this->get('/minhas-solicitacoes');
            $this->assertResponseOk();
            $this->get('/solicitacoes/nova');
            $this->assertResponseOk();
        }
    }

    public function testPcmCanLinkAndCancelWithFunctionalAudit(): void
    {
        $this->session(['Auth' => 2]);
        $this->enableCsrfToken('pcm_solicitacao_os_csrf');
        $this->post('/solicitacoes/1/vincular-os', ['totvs_os_number' => 'PCM-0001']);
        $this->assertResponseCode(302);
        $requests = TableRegistry::getTableLocator()->get('ServiceRequests');
        $this->assertSame('PCM-0001', $requests->get(1)->totvs_os_number);
        $this->assertSame(2, $requests->get(1)->os_linked_by);
        $this->enableCsrfToken('pcm_solicitacao_os_csrf');
        $this->post('/solicitacoes/2/cancelar', ['reason' => 'Solicitação duplicada']);
        $this->assertResponseCode(302);
        $this->assertSame('CANCELADA', $requests->get(2)->status);
        $this->assertSame(2, $requests->get(2)->cancelled_by);
        $this->assertSame(4, TableRegistry::getTableLocator()->get('RequestAudits')->find()->where(['user_id' => 2])->count());
    }
}
