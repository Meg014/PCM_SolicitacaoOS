<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Core\Configure;

final class SsoSettings
{
    public static function centralLoginUrl(): string
    {
        $url = rtrim((string)Configure::read('Sso.centralUrl', ''), '/');
        self::validateUrl($url, (bool)Configure::read('Sso.development', false));
        if (parse_url($url, PHP_URL_QUERY) !== null || preg_match('/[\\\\\x00-\x20]/', $url)) {
            throw new \RuntimeException('URL da Central inválida.');
        }
        return $url . '/login';
    }

    public static function read(): array
    {
        $s = Configure::read('Sso', []);
        if (empty($s['enabled']) || ($s['clientId'] ?? '') !== 'pcm-solicitacao-os' || strlen($s['integrationSecret'] ?? '') < 32) {
            throw new \RuntimeException('SSO indisponível.');
        }
        foreach (['issuer', 'callback', 'logoutReturn', 'centralUrl'] as $key) {
            self::validateUrl($s[$key] ?? '', !empty($s['development']));
            if (parse_url($s[$key], PHP_URL_QUERY)) { throw new \RuntimeException('URL SSO inválida.'); }
        }
        if (parse_url($s['issuer'], PHP_URL_QUERY) || parse_url($s['issuer'], PHP_URL_FRAGMENT)) {
            throw new \RuntimeException('Issuer inválido.');
        }
        if (empty($s['development']) && !(Configure::read('Session.ini')['session.cookie_secure'] ?? false)) {
            throw new \RuntimeException('SSO exige cookie Secure.');
        }
        return $s;
    }

    public static function validateUrl(string $url, bool $development): void
    {
        $p = parse_url($url);
        $local = in_array($p['host'] ?? '', ['localhost', '127.0.0.1', '[::1]'], true);
        if (!$p || empty($p['host']) || isset($p['user']) || isset($p['pass']) || isset($p['fragment']) ||
            (!$development && $local) || (($p['scheme'] ?? '') !== 'https' && !($development && $local && ($p['scheme'] ?? '') === 'http'))) {
            throw new \RuntimeException('URL SSO inválida.');
        }
    }
}
