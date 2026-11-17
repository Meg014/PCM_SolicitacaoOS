<?php
declare(strict_types=1);
namespace App\Service;

use Cake\ORM\TableRegistry;

final class SsoSessionGuard
{
    public function __construct(private ?CentralAccessService $central = null) { }
    public function valid(array $sso, int $userId): bool
    {
        try {
            $settings = SsoSettings::read();
            return ($sso['userId'] ?? 0) === $userId && ($sso['issuer'] ?? '') === $settings['issuer'] &&
                ($sso['exp'] ?? 0) > time() &&
                TableRegistry::getTableLocator()->get('SsoIdentities')->exists([
                    'user_id' => $userId, 'issuer' => $sso['issuer'], 'subject' => $sso['subject'],
                ]) && ($this->central ?? new CentralAccessService())->allowed($sso['issuer'], $sso['subject']);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
