<?php
declare(strict_types=1);

namespace App\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    protected array $fixtures = ['app.UserDepartments', 'app.Users', 'app.RegistrationAudits'];
    private array $previous = [];

    public function setUp(): void
    {
        parent::setUp();
        foreach (['APP_ENV' => 'production', 'BOOTSTRAP_ADMIN_NAME' => 'Administrador de teste',
            'BOOTSTRAP_ADMIN_USERNAME' => 'bootstrap.teste', 'BOOTSTRAP_ADMIN_EMAIL' => '',
            'BOOTSTRAP_ADMIN_PASSWORD' => 'BootstrapTeste123!', 'BOOTSTRAP_ADMIN_DEPARTMENT' => 'PCM teste'] as $key => $value) {
            $this->previous[$key] = $_SERVER[$key] ?? null;
            $_SERVER[$key] = $value;
        }
        $this->users()->getConnection()->execute("SELECT setval(pg_get_serial_sequence('users', 'id'), (SELECT MAX(id) FROM users))");
    }

    public function tearDown(): void
    {
        foreach ($this->previous as $key => $value) {
            if ($value === null) { unset($_SERVER[$key]); } else { $_SERVER[$key] = $value; }
        }
        parent::tearDown();
    }

    private function users(): \Cake\ORM\Table { return TableRegistry::getTableLocator()->get('Users'); }
    private function withoutAdmin(): void { $this->users()->updateAll(['role' => 'GESTOR'], ['id' => 1]); }

    public function testProductionCreatesHashedAdminWithMandatoryPasswordChangeAndSafeAudit(): void
    {
        $this->withoutAdmin(); $this->exec('create_admin'); $this->assertExitSuccess();
        $user = $this->users()->find()->where(['role' => 'ADMIN_PCM'])->firstOrFail();
        $this->assertSame('bootstrap.teste', $user->username); $this->assertNull($user->email);
        $this->assertTrue($user->active); $this->assertTrue($user->must_change_password);
        $this->assertTrue(password_verify('BootstrapTeste123!', $user->password));
        $this->assertSame('argon2id', password_get_info($user->password)['algoName']);
        $audit = TableRegistry::getTableLocator()->get('RegistrationAudits')->find()->firstOrFail();
        $this->assertSame('PRODUCTION_ADMIN_BOOTSTRAPPED', $audit->action); $this->assertNull($audit->user_id);
        $this->assertStringNotContainsString($user->password, json_encode($audit->new_values));
        $this->assertStringNotContainsString('BootstrapTeste123!', json_encode($audit->new_values));
        $this->assertOutputNotContains('BootstrapTeste123!'); $this->assertErrorEmpty();
        $this->exec('create_admin'); $this->assertExitSuccess();
        $this->assertSame(1, $this->users()->find()->where(['role' => 'ADMIN_PCM'])->count());
        $this->assertSame(1, TableRegistry::getTableLocator()->get('RegistrationAudits')->find()->count());
    }

    public function testExistingInactiveAdminIsNotDuplicatedOrReactivated(): void
    {
        $this->users()->updateAll(['active' => false], ['id' => 1]);
        $_SERVER['BOOTSTRAP_ADMIN_PASSWORD'] = '';
        $this->exec('create_admin'); $this->assertExitSuccess();
        $this->assertOutputContains('Já existe um ADMIN_PCM');
        $this->assertFalse($this->users()->get(1)->active); $this->assertSame(4, $this->users()->find()->count());
    }

    public function testNonProductionRequiresExplicitConfirmation(): void
    {
        $this->withoutAdmin(); $_SERVER['APP_ENV'] = 'development';
        $this->exec('create_admin'); $this->assertExitError();
        $this->assertSame(4, $this->users()->find()->count());
        $this->exec('create_admin --confirm-non-production'); $this->assertExitSuccess();
        $this->assertSame(1, $this->users()->find()->where(['role' => 'ADMIN_PCM'])->count());
    }

    public function testInvalidCredentialsAndUsernameConflictCreateNoUser(): void
    {
        $this->withoutAdmin();
        foreach (['BOOTSTRAP_ADMIN_PASSWORD' => 'x', 'BOOTSTRAP_ADMIN_NAME' => '',
            'BOOTSTRAP_ADMIN_USERNAME' => 'user2', 'BOOTSTRAP_ADMIN_EMAIL' => 'invalid'] as $key => $value) {
            $before = $_SERVER[$key]; $_SERVER[$key] = $value;
            $this->exec('create_admin'); $this->assertExitError();
            $this->assertSame(4, $this->users()->find()->count());
            $this->assertSame(0, TableRegistry::getTableLocator()->get('RegistrationAudits')->find()->count());
            $this->assertOutputNotContains('BootstrapTeste123!');
            $this->assertErrorRegExp('/\A(?![\s\S]*BootstrapTeste123!)/');
            $_SERVER[$key] = $before;
        }
    }

    public function testAuditFailureRollsBackBootstrap(): void
    {
        $this->withoutAdmin();
        $listener = function (): void {
            TableRegistry::getTableLocator()->get('RegistrationAudits')->getValidator()->add('action', 'reject', ['rule' => fn() => false]);
        };
        \Cake\Event\EventManager::instance()->on('Command.beforeExecute', $listener);
        try { $this->exec('create_admin'); } finally { \Cake\Event\EventManager::instance()->off('Command.beforeExecute', $listener); }
        $this->assertExitError();
        $this->assertSame(4, $this->users()->find()->count());
        $this->assertSame(0, TableRegistry::getTableLocator()->get('RegistrationAudits')->find()->count());
        $this->assertOutputNotContains('BootstrapTeste123!');
    }

    public function testEmailOnlyAndBothIdentifiersAreAccepted(): void
    {
        $this->withoutAdmin();
        $_SERVER['BOOTSTRAP_ADMIN_USERNAME'] = '';
        $_SERVER['BOOTSTRAP_ADMIN_EMAIL'] = 'bootstrap.email@example.test';
        $this->exec('create_admin'); $this->assertExitSuccess();
        $user = $this->users()->find()->where(['role' => 'ADMIN_PCM'])->firstOrFail();
        $this->assertNull($user->username); $this->assertSame('bootstrap.email@example.test', $user->email);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(password_verify('BootstrapTeste123!', $user->password));
        $this->users()->updateAll(['role' => 'GESTOR'], ['id' => $user->id]);
        $_SERVER['BOOTSTRAP_ADMIN_USERNAME'] = 'outro.bootstrap';
        $_SERVER['BOOTSTRAP_ADMIN_EMAIL'] = 'outro.bootstrap@example.test';
        $this->exec('create_admin'); $this->assertExitSuccess();
        $user = $this->users()->find()->where(['role' => 'ADMIN_PCM'])->firstOrFail();
        $this->assertSame('outro.bootstrap', $user->username);
        $this->assertSame('outro.bootstrap@example.test', $user->email);
    }

    public function testNeitherIdentifierIsRejected(): void
    {
        $this->withoutAdmin(); $_SERVER['BOOTSTRAP_ADMIN_USERNAME'] = ''; $_SERVER['BOOTSTRAP_ADMIN_EMAIL'] = '';
        $this->exec('create_admin'); $this->assertExitError();
        $this->assertSame(4, $this->users()->find()->count());
        $this->assertSame(0, TableRegistry::getTableLocator()->get('RegistrationAudits')->find()->count());
    }

    public function testDepartmentRequiredAndNewDepartmentCreationIsAudited(): void
    {
        $this->withoutAdmin(); $_SERVER['BOOTSTRAP_ADMIN_DEPARTMENT'] = '';
        $this->exec('create_admin'); $this->assertExitError();
        $this->assertSame(4, $this->users()->find()->count());
        $this->users()->getConnection()->execute("SELECT setval(pg_get_serial_sequence('user_departments', 'id'), (SELECT MAX(id) FROM user_departments))");
        $_SERVER['BOOTSTRAP_ADMIN_DEPARTMENT'] = 'Setor informado pelo operador';
        $this->exec('create_admin'); $this->assertExitSuccess();
        $department = TableRegistry::getTableLocator()->get('UserDepartments')->find()->where(['name' => $_SERVER['BOOTSTRAP_ADMIN_DEPARTMENT']])->firstOrFail();
        $user = $this->users()->find()->where(['role' => 'ADMIN_PCM'])->firstOrFail();
        $this->assertSame($department->id, $user->department_id);
        $this->assertSame(2, TableRegistry::getTableLocator()->get('RegistrationAudits')->find()->count());
    }
}
