<?php
declare(strict_types=1);
namespace App\Service;

use Cake\Http\Client;

class CentralAccessService
{
    public function allowed(string $issuer, string $subject): bool
    {
        $s = SsoSettings::read();
        $body = json_encode(['applicationId' => $s['clientId'], 'issuer' => $issuer, 'subject' => $subject], JSON_THROW_ON_ERROR);
        $timestamp = (string)time();
        $nonce = bin2hex(random_bytes(32));
        $response = (new Client())->post(rtrim($s['centralUrl'], '/') . '/api/internal/system-access', $body, [
            'timeout' => 5, 'redirect' => false, 'headers' => ['Content-Type' => 'application/json',
                'X-PCM-Timestamp' => $timestamp, 'X-PCM-Nonce' => $nonce,
                'X-PCM-Signature' => hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $body, $s['integrationSecret'])],
        ]);
        $data = $response->getJson();
        return $response->getStatusCode() === 200 && ($data['allowed'] ?? false) === true &&
            ($data['applicationId'] ?? '') === $s['clientId'] && ($data['issuer'] ?? '') === $issuer && ($data['subject'] ?? '') === $subject;
    }
}
