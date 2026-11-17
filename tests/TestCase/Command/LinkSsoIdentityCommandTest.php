<?php
declare(strict_types=1);
namespace App\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

class LinkSsoIdentityCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;
    protected array $fixtures = ['app.Users', 'app.SsoIdentities', 'app.RegistrationAudits'];

    public function testExplicitLinkPreservesProfileAndCannotReassignIdentity(): void
    {
        Configure::write('Sso.issuer', 'https://sso.example.test/realms/pcm');
        TableRegistry::getTableLocator()->get('SsoIdentities')->getConnection()->execute(
            "SELECT setval(pg_get_serial_sequence('sso_identities', 'id'), (SELECT MAX(id) FROM sso_identities))",
        );
        $users = TableRegistry::getTableLocator()->get('Users');
        $before = $users->get(3)->toArray();
        $this->exec('link_sso_identity 3 verified-subject --actor-id 1 --reason "Conferencia administrativa"');
        $this->assertExitSuccess();
        self::assertEquals($before, $users->get(3)->toArray());
        $links = TableRegistry::getTableLocator()->get('SsoIdentities');
        self::assertTrue($links->exists(['user_id' => 3, 'subject' => 'verified-subject']));
        $audit = TableRegistry::getTableLocator()->get('RegistrationAudits')->find()->firstOrFail();
        self::assertSame('SSO_IDENTITY_LINKED', $audit->action);
        self::assertSame(1, $audit->user_id);
        $this->exec('link_sso_identity 2 verified-subject --actor-id 1 --reason "Tentativa de troca"');
        $this->assertExitError();
        self::assertTrue($links->exists(['user_id' => 3, 'subject' => 'verified-subject']));
        self::assertFalse($links->exists(['user_id' => 2, 'subject' => 'verified-subject']));
    }
}
