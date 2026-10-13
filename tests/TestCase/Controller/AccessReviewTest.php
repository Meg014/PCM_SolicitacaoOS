<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class AccessReviewTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.UserDepartments', 'app.Users', 'app.Sectors', 'app.RegistrationAudits', 'app.ServiceRequests', 'app.RequestItems', 'app.RequestAudits', 'app.RequestAttachments'];

    private function users(): \Cake\ORM\Table
    {
        return TableRegistry::getTableLocator()->get('Users');
    }

    private function postPassword(string $current, string $new, ?string $confirmation = null): void
    {
        $this->enableCsrfToken('pcm_solicitacao_os_csrf');
        $this->post('/alterar-senha', ['current_password' => $current, 'new_password' => $new, 'confirmation' => $confirmation ?? $new, 'id' => 2, 'role' => 'INVALID']);
    }

    public function testFirstLoginRequiresPersonalPassword(): void
    {
        $this->users()->updateAll(['must_change_password' => true], ['id' => 1]);
        $this->enableCsrfToken('pcm_solicitacao_os_csrf');
        $this->post('/entrar', ['login' => 'user1', 'password' => 'SenhaTeste123!']);
        $this->assertRedirect('/alterar-senha');
        $this->assertSession(1, 'Auth');
    }

    public function testAllPrivatePagesAndActionsAreBlockedUntilChange(): void
    {
        $this->users()->updateAll(['must_change_password' => true], ['id' => 3]);
        $this->session(['Auth' => 3]);
        foreach (['/', '/painel', '/indicadores', '/relatorios', '/usuarios', '/setores', '/meu-perfil', '/solicitacoes/1', '/solicitacoes/nova', '/minhas-solicitacoes', '/fotos/1', '/api/bens?q=bo', '/api/setores?q=ma'] as $url) {
            $this->get($url);
            $this->assertRedirect('/alterar-senha');
        }
        $this->enableCsrfToken('pcm_solicitacao_os_csrf');
        $this->post('/solicitacoes/1/vincular-os');
        $this->assertRedirect('/alterar-senha');
        $this->get('/alterar-senha');
        $this->assertResponseOk();
        $this->assertResponseNotContains('Nova Ordem de Serviço');
        $this->assertResponseContains('Sair');
    }

    public function testLongPasswordChangeUnlocksAccessAndAuditsWithoutSecrets(): void
    {
        $this->users()->updateAll(['must_change_password' => true], ['id' => 3]);
        $this->session(['Auth' => 3]);
        $new = str_repeat('senha pessoal longa ', 30);
        $otherHash = $this->users()->get(2)->password;
        $this->postPassword('SenhaTeste123!', $new);
        $this->assertRedirect('/');
        $user = $this->users()->get(3);
        $this->assertFalse($user->must_change_password);
        $this->assertTrue((new DefaultPasswordHasher())->check($new, $user->password));
        $this->assertSame('argon2id', password_get_info($user->password)['algoName']);
        $this->assertSame($otherHash, $this->users()->get(2)->password);
        $audit = TableRegistry::getTableLocator()->get('RegistrationAudits')->find()->firstOrFail();
        $this->assertSame('PASSWORD_CHANGED', $audit->action);
        $this->assertArrayNotHasKey('password', $audit->new_values);
        $this->assertArrayNotHasKey('password', $audit->old_values);
        $this->get('/minhas-solicitacoes');
        $this->assertResponseOk();
    }

    public function testIncorrectCurrentConfirmationAndSamePasswordAreRejected(): void
    {
        $this->users()->updateAll(['must_change_password' => true], ['id' => 3]);
        $this->session(['Auth' => 3]);
        foreach ([['errada', 'pessoal123', 'pessoal123'], ['SenhaTeste123!', 'pessoal123', 'outra'], ['SenhaTeste123!', 'SenhaTeste123!', 'SenhaTeste123!'], ['SenhaTeste123!', '123', '123']] as [$old, $new, $confirmation]) {
            $this->postPassword($old, $new, $confirmation);
            $this->assertResponseOk();
            $this->assertTrue($this->users()->get(3)->must_change_password);
        }
    }

    public function testSimplePasswordAndLaterProfileChange(): void
    {
        $this->session(['Auth' => 3]);
        $this->get('/meu-perfil');
        $this->assertResponseOk();
        $this->assertResponseContains('Alterar minha senha');
        $this->postPassword('SenhaTeste123!', 'aaaaaa');
        $this->assertRedirect('/');
        $this->assertTrue((new DefaultPasswordHasher())->check('aaaaaa', $this->users()->get(3)->password));
    }

    public function testProfileBasedHomeAndMenus(): void
    {
        foreach ([1 => '/painel', 2 => '/painel', 3 => '/minhas-solicitacoes'] as $id => $destination) {
            $this->session(['Auth' => $id]);
            $this->get('/');
            $this->assertRedirect($destination);
            $this->get('/minhas-solicitacoes');
            $this->assertResponseOk();
            $this->assertResponseContains('Meu perfil');
            $this->assertResponseNotContains('biolasa-logo.png');
            if ($id === 1) { $this->assertResponseContains('Administração'); }
            else { $this->assertResponseNotContains('Administração'); }
            if ($id === 3) { $this->assertResponseNotContains('Fila de O.S.'); $this->assertResponseNotContains('Indicadores'); }
            else { $this->assertResponseContains('Início'); $this->assertResponseContains('Indicadores'); }
        }
    }

    public function testPcmPasswordResetRequiresChangeAgain(): void
    {
        $this->session(['Auth' => 1]);
        $this->enableCsrfToken('pcm_solicitacao_os_csrf');
        $this->post('/usuarios/2/editar', ['department_id' => 1, 'password' => str_repeat('a', 150), 'must_change_password' => false]);
        $this->assertRedirect('/usuarios');
        $this->assertTrue($this->users()->get(2)->must_change_password);
    }

    public function testPasswordChangeRequiresAuthenticationAndCsrf(): void
    {
        $this->get('/alterar-senha');
        $this->assertRedirect('/entrar');
        $this->session(['Auth' => 3]);
        $this->post('/alterar-senha', ['current_password' => 'SenhaTeste123!', 'new_password' => 'aaaaaa', 'confirmation' => 'aaaaaa']);
        $this->assertResponseCode(403);
    }

    public function testDebugOutputMasksPasswordFields(): void
    {
        $output = \Cake\Error\Debugger::exportVar(['current_password' => 'segredo-atual', 'new_password' => 'segredo-novo', 'confirmation' => 'segredo-confirmacao']);
        $this->assertStringNotContainsString('segredo-', $output);
        $this->assertStringContainsString('[oculto]', $output);
    }
}
