<?php
declare(strict_types=1);
namespace App\Test\TestCase\Service;

use App\Service\{CentralAccessService, SsoSessionGuard};
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;

class SsoSessionGuardTest extends TestCase
{
    protected array $fixtures = ['app.Users', 'app.SsoIdentities'];

    public function testBindingExpiryRevocationAndUnavailableCentral(): void
    {
        $previous = Configure::read('Sso');
        Configure::write('Sso', ['enabled' => true, 'development' => true, 'clientId' => 'pcm-solicitacao-os',
            'issuer' => 'https://sso.example.test/realms/pcm', 'callback' => 'https://os.example.test/sso/callback',
            'logoutReturn' => 'https://os.example.test/entrar', 'centralUrl' => 'https://central.example.test', 'integrationSecret' => str_repeat('x', 32)]);
        try {
            $sso = ['issuer' => 'https://sso.example.test/realms/pcm', 'subject' => 'linked-id', 'userId' => 1, 'exp' => time() + 600];
            $central = $this->createMock(CentralAccessService::class);
            $central->method('allowed')->willReturnOnConsecutiveCalls(true, false);
            $guard = new SsoSessionGuard($central);
            self::assertTrue($guard->valid($sso, 1));
            self::assertFalse($guard->valid($sso, 1)); // Revogação na próxima requisição.
            self::assertFalse($guard->valid(array_replace($sso, ['subject' => 'unlinked']), 1));
            self::assertFalse($guard->valid($sso, 2));
            self::assertFalse($guard->valid(array_replace($sso, ['exp' => time() - 1]), 1));
            $offline = $this->createMock(CentralAccessService::class);
            $offline->method('allowed')->willThrowException(new \RuntimeException());
            self::assertFalse((new SsoSessionGuard($offline))->valid($sso, 1));
        } finally { Configure::write('Sso', $previous); }
    }
}
