<?php
declare(strict_types=1);
namespace App\Test\TestCase\Service;

use App\Service\{PcmOidcClient, SsoSettings};
use Cake\Http\Session;
use PHPUnit\Framework\TestCase;

class SsoSecurityTest extends TestCase
{
    public function testFlagAcceptsStringAndBooleanEnvironmentValues(): void
    {
        $key = 'PCM_SSO_ENABLED';
        $oldEnv = $_ENV[$key] ?? null;
        $oldServer = $_SERVER[$key] ?? null;
        $oldProcess = getenv($key);
        try {
            foreach (['true', true, 'false', false] as $value) {
                $_ENV[$key] = $_SERVER[$key] = $value;
                putenv($key . '=' . (($value === true || $value === 'true') ? 'true' : 'false'));
                $settings = require dirname(__DIR__, 3) . '/config/sso.php';
                self::assertSame($value === true || $value === 'true', $settings['Sso']['enabled']);
            }
        } finally {
            if ($oldEnv === null) { unset($_ENV[$key]); } else { $_ENV[$key] = $oldEnv; }
            if ($oldServer === null) { unset($_SERVER[$key]); } else { $_SERVER[$key] = $oldServer; }
            putenv($oldProcess === false ? $key : $key . '=' . $oldProcess);
        }
    }

    private function client(): PcmOidcClient
    {
        $session = new Session(['defaults' => 'php']);
        $session->write('OidcTransaction.openid_connect_nonce', 'expected-nonce');
        return new class ($session, ['issuer' => 'https://sso.example.test/realms/pcm',
            'clientId' => 'pcm-solicitacao-os', 'clientSecret' => '', 'callback' => 'https://os.example.test/sso/callback',
            'development' => false]) extends PcmOidcClient {
            public function claims(object $claims): bool { return $this->verifyJWTClaims($claims); }
        };
    }

    public function testRequiredClaimsAndAudience(): void
    {
        $client = $this->client();
        $valid = ['iss' => 'https://sso.example.test/realms/pcm', 'sub' => 'keycloak-id', 'aud' => 'pcm-solicitacao-os',
            'exp' => time() + 600, 'iat' => time(), 'nonce' => 'expected-nonce'];
        self::assertTrue($client->claims((object)$valid));
        foreach (['nonce', 'exp', 'iat', 'sub', 'iss'] as $key) {
            $invalid = $valid; unset($invalid[$key]);
            self::assertFalse($client->claims((object)$invalid), $key);
        }
        foreach (['iss' => 'https://attacker.test', 'aud' => ['pcm-central'], 'nonce' => 'wrong', 'exp' => time() - 1,
            'iat' => time() + 100, 'nbf' => time() + 100, 'azp' => 'pcm-central'] as $key => $value) {
            self::assertFalse($client->claims((object)array_replace($valid, [$key => $value])), $key);
        }
        self::assertFalse($client->claims((object)array_replace($valid, ['aud' => ['pcm-solicitacao-os', 'other']])));
    }

    public function testSymmetricAndUnsignedTokensRejected(): void
    {
        $client = $this->client();
        foreach (['HS256', 'none', 'ES256'] as $alg) {
            try {
                $client->verifySignatures(rtrim(strtr(base64_encode(json_encode(['alg' => $alg])), '+/', '-_'), '=') . '.e30.signature');
                self::fail('Algoritmo aceito indevidamente.');
            } catch (\RuntimeException $e) { self::assertSame('Assinatura não permitida.', $e->getMessage()); }
        }
    }

    public function testProductionRejectsLocalAndInsecureUrls(): void
    {
        foreach (['http://os.example.test', 'https://localhost/sso', 'https://user:secret@os.example.test'] as $url) {
            try { SsoSettings::validateUrl($url, false); self::fail('URL aceita indevidamente.'); }
            catch (\RuntimeException $e) { self::assertSame('URL SSO inválida.', $e->getMessage()); }
        }
        SsoSettings::validateUrl('https://os.example.test/sso', false);
        SsoSettings::validateUrl('http://localhost:8080/sso', true);
        self::assertTrue(true);
    }

    public function testAuthorizationCodePkceAndSignedToken(): void
    {
        $session = new Session(['defaults' => 'php']);
        $client = new class ($session, ['issuer' => 'https://sso.example.test/realms/pcm',
            'clientId' => 'pcm-solicitacao-os', 'clientSecret' => 'test-only', 'callback' => 'https://os.example.test/sso/callback',
            'development' => false]) extends PcmOidcClient {
            public string $token = '';
            public string $jwks = '';
            public string $verifier = '';
            protected function fetchURL(string $url, string $post_body = null, array $headers = []) {
                if ($url === 'https://sso.example.test/token') {
                    parse_str($post_body, $data);
                    \PHPUnit\Framework\Assert::assertSame($this->verifier, $data['code_verifier']);
                    \PHPUnit\Framework\Assert::assertSame('authorization_code', $data['grant_type']);
                    return json_encode(['id_token' => $this->token, 'access_token' => 'opaque-token']);
                }
                return $this->jwks;
            }
        };
        $client->providerConfigParam(['authorization_endpoint' => 'https://sso.example.test/auth',
            'token_endpoint' => 'https://sso.example.test/token', 'jwks_uri' => 'https://sso.example.test/jwks',
            'token_endpoint_auth_methods_supported' => ['client_secret_basic'], 'code_challenge_methods_supported' => ['S256']]);
        $original = $_REQUEST;
        try {
            $_REQUEST = [];
            try { $client->authenticate(); self::fail('Redirect ausente.'); }
            catch (\App\Service\OidcRedirect $redirect) { parse_str(parse_url($redirect->url, PHP_URL_QUERY), $params); }
            self::assertSame('S256', $params['code_challenge_method']);
            $client->verifier = $session->read('OidcTransaction.openid_connect_code_verifier');
            $b64 = fn(string $value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
            self::assertSame($b64(hash('sha256', $client->verifier, true)), $params['code_challenge']);
            $key = \phpseclib3\Crypt\RSA::createKey(2048)->withHash('sha256')->withPadding(\phpseclib3\Crypt\RSA::SIGNATURE_PKCS1);
            $client->jwks = $key->getPublicKey()->toString('JWK');
            $payload = $b64(json_encode(['alg' => 'RS256'])) . '.' . $b64(json_encode([
                'iss' => 'https://sso.example.test/realms/pcm', 'sub' => 'immutable-id', 'aud' => 'pcm-solicitacao-os',
                'exp' => time() + 600, 'iat' => time(), 'nonce' => $params['nonce'],
            ]));
            $client->token = $payload . '.' . $b64($key->sign($payload));
            $_REQUEST = ['code' => 'one-use-code', 'state' => $params['state']];
            self::assertTrue($client->authenticate());
            self::assertSame('immutable-id', $client->getVerifiedClaims('sub'));
            self::assertNull($session->read('OidcTransaction.openid_connect_nonce'));
            self::assertNull($session->read('OidcTransaction.openid_connect_state'));
            self::assertFalse($client->verifyJWTSignature($payload . '.' . $b64(str_repeat('a', 256))));
        } finally { $_REQUEST = $original; $session->delete('OidcTransaction'); }
    }

    public function testPkceCannotSilentlyDowngrade(): void
    {
        $client = $this->client();
        $client->providerConfigParam(['authorization_endpoint' => 'https://sso.example.test/auth',
            'code_challenge_methods_supported' => []]);
        $original = $_REQUEST;
        $_REQUEST = [];
        try {
            $this->expectExceptionMessage('PKCE S256 obrigatório.');
            $client->authenticate();
        } finally { $_REQUEST = $original; }
    }
}
