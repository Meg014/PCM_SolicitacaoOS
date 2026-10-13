<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Domain\Enum\UserRole;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class AdministrationTest extends TestCase
{
    use IntegrationTestTrait;

    protected array $fixtures = ['app.UserDepartments', 'app.Users', 'app.Sectors', 'app.RegistrationAudits'];

    public function setUp(): void
    {
        $config = ConnectionManager::getConfig('test');
        if (empty($config['username']) || empty($config['password'])) {
            $this->markTestSkipped('Configure o banco PostgreSQL de testes e TEST_DB_PASSWORD.');
        }
        parent::setUp();
        foreach (['users', 'sectors'] as $table) {
            ConnectionManager::get('test')->execute(
                "SELECT setval(pg_get_serial_sequence('$table', 'id'), (SELECT MAX(id) FROM $table))",
            );
        }
    }

    private function login(int $id = 1): void
    {
        $this->session(['Auth' => $id]);
    }

    private function postForm(string $url, array $data): void
    {
        $this->enableCsrfToken('pcm_solicitacao_os_csrf');
        $this->post($url, $data);
    }

    public function testLoginAndLogout(): void
    {
        $this->postForm('/entrar', ['login' => 'user1@example.test', 'password' => 'SenhaTeste123!']);
        $this->assertRedirect('/');
        $this->assertSession(1, 'Auth');
        $this->postForm('/sair', []);
        $this->assertRedirect('/entrar');
        $this->assertSession(null, 'Auth');
    }

    public function testInvalidPassword(): void
    {
        $this->postForm('/entrar', ['login' => 'user1@example.test', 'password' => 'errada']);
        $this->assertResponseOk();
        $this->assertSession(null, 'Auth');
        $this->assertResponseContains('Usuário, e-mail ou senha incorretos');
    }

    public function testLoginByUsernameWithoutEmail(): void
    {
        TableRegistry::getTableLocator()->get('Users')->updateAll(['email' => null], ['id' => 3]);
        $this->postForm('/entrar', ['login' => ' USER3 ', 'password' => 'SenhaTeste123!']);
        $this->assertRedirect('/');
        $this->assertSession(3, 'Auth');
    }

    public function testInvalidAndInactiveUsernamesAreRejected(): void
    {
        foreach (['inexistente', 'user4'] as $login) {
            $this->postForm('/entrar', ['login' => $login, 'password' => 'SenhaTeste123!']);
            $this->assertResponseOk();
            $this->assertSession(null, 'Auth');
        }
    }

    public function testMultipleAccountsWithoutEmailAndUsernameUniqueness(): void
    {
        $this->login();
        foreach (['sem.email1', 'sem.email2'] as $username) {
            $this->postForm('/usuarios/novo', ['department_id' => 1, 'name' => 'Sem e-mail', 'username' => $username, 'email' => '', 'password' => 'SenhaTeste123!', 'role' => UserRole::SOLICITANTE->value, 'active' => '1']);
            $this->assertRedirect('/usuarios');
        }
        $users = TableRegistry::getTableLocator()->get('Users');
        $this->assertSame(2, $users->find()->where(['email IS' => null])->count());
        $this->postForm('/usuarios/novo', ['department_id' => 1, 'name' => 'Duplicado', 'username' => 'SEM.EMAIL1', 'password' => 'SenhaTeste123!', 'role' => UserRole::SOLICITANTE->value]);
        $this->assertResponseOk();
        $this->assertResponseContains('Este usuário já está cadastrado');
        $this->assertSame(6, $users->find()->count());
        $audit = TableRegistry::getTableLocator()->get('RegistrationAudits')->find()->firstOrFail();
        $this->assertSame('sem.email1', $audit->new_values['username']);
    }

    public function testUsernameIsRequiredAndCannotBeAnEmail(): void
    {
        $this->login();
        foreach (['', 'joao@example.test'] as $username) {
            $this->postForm('/usuarios/novo', ['department_id' => 1, 'name' => 'Inválido', 'username' => $username, 'password' => 'SenhaTeste123!', 'role' => UserRole::SOLICITANTE->value]);
            $this->assertResponseOk();
        }
        $this->assertSame(4, TableRegistry::getTableLocator()->get('Users')->find()->count());
    }

    public function testInactiveUserCannotLogin(): void
    {
        $this->postForm('/entrar', ['login' => 'user4@example.test', 'password' => 'SenhaTeste123!']);
        $this->assertSession(null, 'Auth');
    }

    public function testInactiveSessionIsRejected(): void
    {
        $this->login(4);
        $this->get('/');
        $this->assertRedirect('/entrar');
    }

    public function testLoginReplacesInactiveSession(): void
    {
        $this->login(4);
        $this->postForm('/entrar', ['login' => 'user1@example.test', 'password' => 'SenhaTeste123!']);
        $this->assertRedirect('/');
        $this->assertSession(1, 'Auth');
        // IntegrationTestTrait reaplica a sessão fornecida em cada request.
        $this->session(['Auth' => 1]);
        $this->get('/usuarios');
        $this->assertResponseOk();
    }

    public function testCurrentRoleIsReloadedFromDatabase(): void
    {
        $this->login();
        TableRegistry::getTableLocator()->get('Users')->updateAll(['role' => UserRole::GESTOR->value], ['id' => 1]);
        $this->get('/usuarios');
        $this->assertResponseCode(403);
    }

    public function testManagerCannotAdministerUsersOrSectors(): void
    {
        $this->login(2);
        $this->get('/usuarios');
        $this->assertResponseCode(403);
        $this->get('/setores/novo');
        $this->assertResponseCode(403);
    }

    public function testRequesterCannotWriteUsers(): void
    {
        $this->login(3);
        $this->postForm('/usuarios/1/editar', ['department_id' => 1, 'name' => 'Alterado']);
        $this->assertResponseCode(403);
        $this->assertSame('Usuário 1', TableRegistry::getTableLocator()->get('Users')->get(1)->name);
    }

    public function testAdminCanReadRegistrations(): void
    {
        $this->login();
        foreach (['/usuarios', '/usuarios/novo', '/usuarios/1/editar', '/setores', '/setores/novo', '/setores/1/editar'] as $url) {
            $this->get($url);
            $this->assertResponseOk();
        }
    }

    public function testCreatesUserWithHashAndAuditIgnoringInjectedId(): void
    {
        $this->login();
        $this->postForm('/usuarios/novo', [
            'department_id' => 1, 'id' => 1, 'name' => 'Novo usuário', 'username' => 'novo', 'email' => 'novo@example.test',
            'password' => 'NovaSenha12345!', 'role' => UserRole::SOLICITANTE->value, 'active' => '1',
        ]);
        $this->assertRedirect('/usuarios');
        $user = TableRegistry::getTableLocator()->get('Users')->find()->where(['email' => 'novo@example.test'])->firstOrFail();
        $this->assertNotEquals(1, $user->id);
        $this->assertTrue((new DefaultPasswordHasher())->check('NovaSenha12345!', $user->password));
        $audit = TableRegistry::getTableLocator()->get('RegistrationAudits')->find()->firstOrFail();
        $this->assertSame(1, $audit->user_id);
        $this->assertArrayNotHasKey('password', $audit->new_values);
    }

    public function testEmptyPasswordKeepsHashOnEdit(): void
    {
        $this->login();
        $users = TableRegistry::getTableLocator()->get('Users');
        $before = $users->get(2)->password;
        $this->postForm('/usuarios/2/editar', ['department_id' => 1, 'name' => 'Gestor atualizado', 'password' => '']);
        $this->assertRedirect('/usuarios');
        $this->assertSame($before, $users->get(2)->password);
    }

    public function testCannotDeactivateOwnAdminAccount(): void
    {
        $this->login();
        $this->postForm('/usuarios/1/editar', ['active' => '0']);
        $this->assertResponseOk();
        $this->assertTrue(TableRegistry::getTableLocator()->get('Users')->get(1)->active);
    }

    public function testInvalidRoleAndDuplicateEmailAreRejected(): void
    {
        $this->login();
        $data = ['department_id' => 1, 'name' => 'Duplicado', 'username' => 'duplicado', 'email' => 'user1@example.test', 'password' => 'NovaSenha12345!', 'role' => UserRole::GESTOR->value];
        $this->postForm('/usuarios/novo', $data);
        $this->assertResponseOk();
        $data['email'] = 'outro@example.test';
        $data['role'] = 'INVALID';
        $this->postForm('/usuarios/novo', $data);
        $this->assertResponseOk();
        $this->assertSame(4, TableRegistry::getTableLocator()->get('Users')->find()->count());
    }

    public function testCreateAndDeactivateSectorWithAudit(): void
    {
        $this->login();
        $this->postForm('/setores/novo', ['name' => 'Produção', 'active' => '1']);
        $this->assertRedirect('/setores');
        $sector = TableRegistry::getTableLocator()->get('Sectors')->find()->where(['name' => 'Produção'])->firstOrFail();
        $this->postForm('/setores/' . $sector->id . '/editar', ['active' => '0']);
        $this->assertRedirect('/setores');
        $this->assertFalse(TableRegistry::getTableLocator()->get('Sectors')->get($sector->id)->active);
        $this->assertSame(2, TableRegistry::getTableLocator()->get('RegistrationAudits')->find()->count());
    }

    public function testPostWithoutCsrfIsRejected(): void
    {
        $this->login();
        $this->post('/setores/novo', ['name' => 'Inválido']);
        $this->assertResponseCode(403);
    }

    public function testAuditFailureRollsBackRegistration(): void
    {
        $users = TableRegistry::getTableLocator()->get('Users');
        $audits = TableRegistry::getTableLocator()->get('RegistrationAudits');
        $audits->getValidator()->add('resource', 'reject', ['rule' => fn() => false]);
        $user = $users->newEntity([
            'department_id' => 1, 'name' => 'Rollback', 'username' => 'rollback', 'email' => 'rollback@example.test', 'password' => 'SenhaTeste123!',
            'role' => UserRole::SOLICITANTE->value, 'active' => true,
        ]);
        try {
            (new \App\Service\RegistrationService())->save($users, $user, null, 1);
            $this->fail('A falha de auditoria deveria impedir o cadastro.');
        } catch (\Cake\ORM\Exception\PersistenceFailedException $e) {
            $this->assertFalse($users->exists(['email' => 'rollback@example.test']));
            $this->assertSame(0, $audits->find()->count());
        }
    }

    public function testLoginByEmailWithoutUsernameAndInactiveEmailIsBlocked(): void
    {
        $users = TableRegistry::getTableLocator()->get('Users');
        $users->updateAll(['username' => null], ['id' => 3]);
        $this->postForm('/entrar', ['login' => ' USER3@EXAMPLE.TEST ', 'password' => 'SenhaTeste123!']);
        $this->assertRedirect('/'); $this->assertSession(3, 'Auth');
        $this->postForm('/sair', []);
        $users->updateAll(['active' => false], ['id' => 3]);
        $this->postForm('/entrar', ['login' => 'user3@example.test', 'password' => 'SenhaTeste123!']);
        $this->assertResponseOk(); $this->assertSession(null, 'Auth');
    }

    public function testEmailOnlyUsersCanBeCreatedAndIdentifiersCannotBothBeRemoved(): void
    {
        $this->login(); $users = TableRegistry::getTableLocator()->get('Users');
        foreach (['somente1@example.test', 'somente2@example.test'] as $email) {
            $this->postForm('/usuarios/novo', ['department_id' => 1, 'name' => 'Apenas e-mail', 'email' => $email,
                'password' => 'SenhaTeste123!', 'role' => UserRole::SOLICITANTE->value, 'active' => '1']);
            $this->assertRedirect('/usuarios');
            $this->assertNull($users->find()->where(['email' => $email])->firstOrFail()->username);
        }
        $user = $users->find()->where(['email' => 'somente1@example.test'])->firstOrFail();
        $this->postForm('/usuarios/' . $user->id . '/editar', ['department_id' => 1, 'name' => 'Apenas e-mail', 'username' => '', 'email' => '',
            'password' => '', 'role' => UserRole::SOLICITANTE->value, 'active' => '1']);
        $this->assertResponseOk(); $this->assertResponseContains('Pelo menos um deles é necessário');
        $this->assertSame('somente1@example.test', $users->get($user->id)->email);
        $this->postForm('/usuarios/novo', ['department_id' => 1, 'name' => 'Duplicado', 'email' => 'SOMENTE1@EXAMPLE.TEST',
            'password' => 'SenhaTeste123!', 'role' => UserRole::SOLICITANTE->value, 'active' => '1']);
        $this->assertResponseOk(); $this->assertSame(6, $users->find()->count());
    }
}
