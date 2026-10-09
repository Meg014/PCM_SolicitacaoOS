<?php
declare(strict_types=1);

namespace App\Service;

use App\Domain\Enum\RequestFlow;
use App\Domain\Enum\UserRole;

final class RequestAccess
{
    public static function conditions(string $role, int $userId): array
    {
        $profile = UserRole::tryFrom($role);
        if ($profile && in_array($profile, [UserRole::ADMIN_PCM, UserRole::GESTOR, UserRole::ENCARREGADO], true)) {
            return [];
        }
        if ($profile) {
            return ['OR' => ['ServiceRequests.user_id' => $userId, 'ServiceRequests.flow' => RequestFlow::ACOMPANHAMENTO->value]];
        }

        return ['ServiceRequests.id' => -1];
    }
}
