<?php
declare(strict_types=1);
namespace App\Service;

use Cake\Http\Session;
use Jumbojett\OpenIDConnectClient;

// Adapta sessão e resposta à infraestrutura CakePHP, sem header()/exit() da biblioteca.
class PcmOidcClient extends OpenIDConnectClient
{
    public function __construct(private Session $session, private array $settings)
    {
        parent::__construct($settings['issuer'], $settings['clientId'], $settings['clientSecret'], $settings['issuer']);
        $this->setRedirectURL($settings['callback']);
        $this->setIssuerValidator(fn($issuer) => $issuer === $settings['issuer']);
        $this->setCodeChallengeMethod('S256');
        $this->setResponseTypes(['code']);
        $this->setAllowImplicitFlow(false);
        $this->setTimeout(5);
        $this->addScope(['openid']);
    }

    protected function getSessionKey(string $key) { return $this->session->read('OidcTransaction.' . $key); }
    protected function setSessionKey(string $key, $value) { $this->session->write('OidcTransaction.' . $key, $value); }
    protected function unsetSessionKey(string $key) { $this->session->delete('OidcTransaction.' . $key); }
    protected function commitSession() { /* CakePHP gerencia o fechamento da sessão. */ }
    public function redirect(string $url) {
        SsoSettings::validateUrl($url, $this->settings['development']);
        parse_str((string)parse_url($url, PHP_URL_QUERY), $params);
        if (($params['response_type'] ?? '') !== 'code' || ($params['code_challenge_method'] ?? '') !== 'S256' ||
            !is_string($this->getCodeVerifier()) || $this->getCodeVerifier() === '') {
            throw new \RuntimeException('PKCE S256 obrigatório.');
        }
        throw new OidcRedirect($url);
    }
    protected function fetchURL(string $url, string $post_body = null, array $headers = []) {
        SsoSettings::validateUrl($url, $this->settings['development']);
        $http = new \Cake\Http\Client();
        $parsedHeaders = [];
        foreach ($headers as $header) {
            [$name, $value] = explode(':', $header, 2);
            $parsedHeaders[$name] = trim($value);
        }
        if ($post_body !== null) { $parsedHeaders['Content-Type'] = 'application/x-www-form-urlencoded'; }
        $options = ['timeout' => 5, 'redirect' => false, 'headers' => $parsedHeaders];
        $response = $post_body === null ? $http->get($url, [], $options) : $http->post($url, $post_body, $options);
        $this->responseCode = $response->getStatusCode();
        $this->responseContentType = $response->getHeaderLine('Content-Type');
        if ($this->responseCode !== 200) { throw new \RuntimeException('Provedor OIDC indisponível.'); }
        return $response->getStringBody();
    }
    public function verifySignatures(string $jwt) {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3 || $this->decodeJWT($jwt)->alg !== 'RS256') {
            throw new \RuntimeException('Assinatura não permitida.');
        }
        return parent::verifySignatures($jwt);
    }
    protected function verifyJWTClaims($claims, string $accessToken = null): bool
    {
        $aud = (array)($claims->aud ?? []);
        return isset($claims->nonce, $claims->exp, $claims->iat, $claims->sub, $claims->iss) &&
            is_string($claims->sub) && $claims->sub !== '' && strlen($claims->sub) <= 255 &&
            is_int($claims->exp) && $claims->exp > time() && is_int($claims->iat) && $claims->iat <= time() + 30 &&
            (!isset($claims->nbf) || (is_int($claims->nbf) && $claims->nbf <= time() + 30)) &&
            is_string($claims->nonce) && is_string($this->getNonce()) && hash_equals($this->getNonce(), $claims->nonce) &&
            in_array($this->settings['clientId'], $aud, true) &&
            (count($aud) === 1 || ($claims->azp ?? '') === $this->settings['clientId']) &&
            (!isset($claims->azp) || $claims->azp === $this->settings['clientId']) &&
            parent::verifyJWTClaims($claims, $accessToken);
    }
}
