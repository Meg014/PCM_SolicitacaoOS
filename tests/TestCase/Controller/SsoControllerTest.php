<?php
declare(strict_types=1);
namespace App\Test\TestCase\Controller;

use Cake\Core\Configure;
use Cake\TestSuite\{IntegrationTestTrait, TestCase};

class SsoControllerTest extends TestCase
{
    use IntegrationTestTrait;
    protected array $fixtures = ['app.Users', 'app.RegistrationAudits'];

    public function testDisabledByDefault(): void
    {
        Configure::write('Sso.enabled', false);
        $this->get('/sso');
        $this->assertResponseCode(404);
    }

    public function testInvalidCallbackCannotAuthenticate(): void
    {
        Configure::write('Sso', ['enabled' => true, 'development' => true, 'clientId' => 'pcm-solicitacao-os',
            'issuer' => 'https://sso.example.test/realms/pcm', 'callback' => 'https://os.example.test/sso/callback',
            'logoutReturn' => 'https://os.example.test/entrar', 'centralUrl' => 'https://central.example.test', 'integrationSecret' => str_repeat('x', 32)]);
        $this->session(['OidcTransaction' => ['created' => time(), 'openid_connect_state' => 'expected']]);
        $this->get('/sso/callback?state=attacker&code=code');
        $this->assertResponseCode(403);
        $this->assertSession(null, 'Auth');
        $this->assertSession(null, 'OidcTransaction');
    }

    public function testLocalLogoutClearsSsoAndRequiresCsrf(): void
    {
        Configure::write('Sso.centralUrl', 'https://central.example.test');
        $this->session(['Auth' => 1, 'Sso' => ['userId' => 1]]);
        $this->enableCsrfToken('pcm_solicitacao_os_csrf');
        $this->post('/sair', ['redirect' => 'https://attacker.test', 'globalLogout' => true]);
        $this->assertSession(null, 'Auth');
        $this->assertSession(null, 'Sso');
        $this->assertRedirect('https://central.example.test/login');
    }

    public function testLocalLoginLogoutStillReturnsToCakeLogin(): void
    {
        $this->session(['Auth' => 1]);
        $this->enableCsrfToken('pcm_solicitacao_os_csrf');
        $this->post('/sair');
        $this->assertRedirect('/entrar');
        $this->assertSession(null, 'Auth');
    }

    public function testGlobalLogoutUsesSeparatePostRouteAndCentralReturn(): void
    {
        Configure::write('Sso', ['enabled' => true, 'development' => true, 'clientId' => 'pcm-solicitacao-os',
            'issuer' => 'https://sso.example.test/realms/pcm', 'callback' => 'https://os.example.test/sso/callback',
            'logoutReturn' => 'https://central.example.test/login', 'centralUrl' => 'https://central.example.test',
            'integrationSecret' => str_repeat('x', 32)]);
        $this->session(['Auth' => 1, 'Sso' => ['userId' => 1]]);
        $this->enableCsrfToken('pcm_solicitacao_os_csrf');
        $this->post('/sair-de-todos');
        $this->assertRedirect('https://sso.example.test/realms/pcm/protocol/openid-connect/logout?client_id=pcm-solicitacao-os&post_logout_redirect_uri=https%3A%2F%2Fcentral.example.test%2Flogin');
        $this->assertSession(null, 'Auth');
        $this->assertSession(null, 'Sso');
    }

    public function testInvalidCentralUrlCannotRedirectAndStillClearsSession(): void
    {
        Configure::write('Sso.centralUrl', 'https://central.example.test/?next=https://attacker.test');
        $this->session(['Auth' => 1, 'Sso' => ['userId' => 1]]);
        $this->enableCsrfToken('pcm_solicitacao_os_csrf');
        $this->post('/sair', ['redirect' => 'https://attacker.test', 'globalLogout' => true]);
        $this->assertResponseCode(503);
        $this->assertSession(null, 'Auth');
        self::assertFalse($this->_response->hasHeader('Location'));
    }

    public function testLogoutWithoutCsrfIsRejected(): void
    {
        $this->session(['Auth' => 1, 'Sso' => ['userId' => 1]]);
        $this->post('/sair');
        $this->assertResponseCode(403);
        $this->assertSession(1, 'Auth');
    }
}
