<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class HomeControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users'];

    public function testPrivateHomeRequiresAuthentication(): void
    {
        $this->get('/');
        $this->assertRedirect('/entrar');
    }

    public function testPublicAccessPage(): void
    {
        $this->get('/entrar');
        $this->assertResponseOk();
        $this->assertResponseContains('lang="pt-BR"');
        $this->assertResponseContains('Entrar no sistema');
        $this->assertResponseContains('/img/icon.png');
        $this->assertResponseContains('apple-touch-icon');
        $this->assertHeader('Cache-Control', 'no-store');
        $this->assertResponseContains('name="_csrfToken"');
    }

    public function testUnknownRoutesAreNotExposed(): void
    {
        $this->get('/pages/home');
        $this->assertResponseCode(404);
    }

    public function testStaleLoginIsRejectedAndRedirectedWithoutAuthenticating(): void
    {
        $this->enableCsrfToken('pcm_solicitacao_os_csrf');
        $this->post('/entrar', ['login' => 'user1', 'password' => 'SenhaTeste123!', '_csrfToken' => 'token-desatualizado']);
        $this->assertResponseCode(303); $this->assertRedirect('/entrar');
        $this->assertSession(null, 'Auth');
        $this->assertFlashMessage('A página de acesso está desatualizada. Digite usuário e senha novamente para entrar.');
        $this->assertResponseNotContains('InvalidCsrfTokenException');
        $this->session(['Flash' => $_SESSION['Flash']]);
        $this->get('/entrar'); $this->assertResponseOk();
        $this->assertResponseContains('A página de acesso está desatualizada');
        $this->assertResponseNotContains('SenhaTeste123!');
    }

    public function testLoginWithoutCsrfCannotAuthenticate(): void
    {
        $this->post('/entrar', ['login' => 'user1', 'password' => 'SenhaTeste123!']);
        $this->assertResponseCode(303); $this->assertRedirect('/entrar');
        $this->assertSession(null, 'Auth');
    }

    public function testValidProjectTokenWorksEvenWithAnotherProjectsCookie(): void
    {
        $this->cookie('csrfToken', 'cookie-de-outro-sistema');
        $this->enableCsrfToken('pcm_solicitacao_os_csrf');
        $this->post('/entrar', ['login' => 'user1', 'password' => 'SenhaTeste123!']);
        $this->assertRedirect('/'); $this->assertSession(1, 'Auth');
    }
}
