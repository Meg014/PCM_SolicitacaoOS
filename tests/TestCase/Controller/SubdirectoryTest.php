<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class SubdirectoryTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.Users', 'app.Sectors', 'app.RegistrationAudits', 'app.ServiceRequests', 'app.RequestItems', 'app.RequestAudits', 'app.RequestAttachments'];
    private array $originalApp;
    private string $originalOrigin;
    private const BASE = '/pcm-solicitacao-os';

    public function setUp(): void
    {
        parent::setUp();
        $this->originalApp = Configure::read('App');
        $this->originalOrigin = Router::fullBaseUrl();
        Configure::write('App.baseUrl', self::BASE);
        Configure::write('App.base', self::BASE);
        Configure::write('App.fullBaseUrl', 'https://pcm.biolasa.com.br' . self::BASE);
        Router::fullBaseUrl('https://pcm.biolasa.com.br');
    }

    public function tearDown(): void
    {
        Configure::write('App', $this->originalApp);
        Router::fullBaseUrl($this->originalOrigin);
        parent::tearDown();
    }

    private function url(string $path): string { return 'https://pcm.biolasa.com.br' . self::BASE . $path; }

    public function testLoginFormAndAllPublicAssetsUseSubdirectory(): void
    {
        $this->get($this->url('/entrar')); $this->assertResponseOk();
        foreach (['action="' . self::BASE . '/entrar"', self::BASE . '/css/app.css', self::BASE . '/js/navigation.js',
            self::BASE . '/img/icon.png', self::BASE . '/img/biolasa-logo.png'] as $expected) {
            $this->assertResponseContains($expected);
        }
        $this->assertResponseNotContains('action="/entrar"');
        $this->assertSame($this->url('/entrar'), Router::url(['controller' => 'Home', 'action' => 'login'], true));
    }

    public function testAnonymousAndLoginLogoutRedirectsStayWithinSubdirectory(): void
    {
        $this->get($this->url('/painel')); $this->assertHeader('Location', self::BASE . '/entrar');
        $this->enableCsrfToken('pcm_solicitacao_os_csrf');
        $this->post($this->url('/entrar'), ['login' => 'user1', 'password' => 'SenhaTeste123!']);
        $this->assertResponseCode(302);
        $this->assertRedirect($this->url('')); $this->assertSession(1, 'Auth');
        $this->session(['Auth' => 1]); $this->get($this->url('/')); $this->assertRedirect($this->url('/painel'));
        $this->post($this->url('/sair')); $this->assertRedirect($this->url('/entrar'));
    }

    public function testPasswordGuardAndCsrfRecoveryRespectSubdirectory(): void
    {
        TableRegistry::getTableLocator()->get('Users')->updateAll(['must_change_password' => true], ['id' => 3]);
        $this->session(['Auth' => 3]); $this->get($this->url('/solicitacoes/nova'));
        $this->assertRedirect($this->url('/alterar-senha'));
        $this->session(['Auth' => null]);
        $this->post($this->url('/entrar'), ['login' => 'user1', 'password' => 'SenhaTeste123!', '_csrfToken' => 'invalid']);
        $this->assertResponseCode(303); $this->assertHeader('Location', self::BASE . '/entrar');
        $this->assertSession(null, 'Auth');
    }

    public function testPrivateLinksFormsAutocompleteAndAttachmentsUseSubdirectory(): void
    {
        $this->session(['Auth' => 1]); $this->get($this->url('/solicitacoes/nova')); $this->assertResponseOk();
        foreach (['/api/bens', '/api/setores', '/api/solicitacoes-ativas', '/solicitacoes/nova', '/sair', '/meu-perfil', '/usuarios'] as $path) {
            $this->assertResponseContains(self::BASE . $path);
        }
        $this->assertResponseContains(self::BASE . '/js/request-form.js');
        $this->get($this->url('/solicitacoes/1')); $this->assertResponseOk();
        $this->assertResponseContains('action="' . self::BASE . '/solicitacoes/1/vincular-os"');
        $this->assertSame(self::BASE . '/fotos/1', Router::url(['controller' => 'Attachments', 'action' => 'view', 1]));
        $this->get($this->url('/painel')); $this->assertResponseOk();
        $this->assertResponseContains('data-refresh-url="' . self::BASE . '/painel/atualizacao"');
        $this->assertResponseContains(self::BASE . '/js/pcm-queue.js');
        $this->get($this->url('/painel/atualizacao')); $this->assertResponseOk();
        $this->assertResponseContains('href="' . self::BASE . '/solicitacoes/1"');
    }

    public function testBootstrapFromEnvironmentGeneratesSinglePrefixAndSupportsLocalFalse(): void
    {
        $script = <<<'PHP'
$_SERVER['APP_BASE_URL'] = $argv[1];
$_SERVER['APP_FULL_BASE_URL'] = $argv[2];
require 'vendor/autoload.php';
require 'config/bootstrap.php';
$app = new \App\Application(CONFIG);
$app->routes(\Cake\Routing\Router::createRouteBuilder('/'));
$base = \Cake\Core\Configure::read('App.base') ?: '';
$request = \Cake\Http\ServerRequestFactory::fromGlobals([
    'REQUEST_URI' => $base . '/entrar', 'REQUEST_METHOD' => 'GET', 'PHP_SELF' => '/index.php',
    'HTTP_HOST' => 'pcm.biolasa.com.br', 'HTTPS' => 'on', 'SERVER_PORT' => '443',
]);
\Cake\Routing\Router::setRequest($request);
echo json_encode(['baseUrl' => \Cake\Core\Configure::read('App.baseUrl'), 'base' => $request->getAttribute('base'),
    'webroot' => $request->getAttribute('webroot'), 'path' => $request->getUri()->getPath(),
    'url' => \Cake\Routing\Router::url(['controller' => 'Home', 'action' => 'login'], true),
    'sessionSecure' => \Cake\Core\Configure::read('Session.ini')['session.cookie_secure']], JSON_THROW_ON_ERROR);
PHP;
        foreach ([self::BASE, '', 'false'] as $base) {
            $full = 'https://pcm.biolasa.com.br' . ($base === self::BASE ? $base : '');
            $pipes = [];
            $process = proc_open([PHP_BINARY, '-r', $script, $base, $full], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT,
                ['SystemRoot' => (string)getenv('SystemRoot'), 'PATH' => (string)getenv('PATH')]);
            $this->assertIsResource($process);
            $out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $this->assertSame(0, proc_close($process), $error);
            // O PHP local emite um aviso preexistente de OpenSSL antes do JSON.
            $values = json_decode(substr($out, strpos($out, '{')), true, 512, JSON_THROW_ON_ERROR);
            $expected = $base === self::BASE ? self::BASE : '';
            $this->assertSame($expected ?: false, $values['baseUrl']);
            $this->assertSame($expected, $values['base']); $this->assertSame($expected . '/', $values['webroot']);
            $this->assertSame('/entrar', $values['path']); $this->assertSame($full . '/entrar', $values['url']);
            $this->assertTrue($values['sessionSecure']);
        }
    }
}
