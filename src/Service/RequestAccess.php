<?php
declare(strict_types=1);

namespace App\Service;

use App\Domain\Enum\RequestFlow;
use App\Domain\Enum\UserRole;

final class RequestAccess
{
    public static function canEdit(\App\Model\Entity\ServiceRequest $request, string $role, int $userId, ?\DateTimeInterface $now = null): bool
    {
        if ($role === UserRole::ADMIN_PCM->value) { return true; }
        if ($role !== UserRole::MANTENEDOR->value || (int)$request->user_id !== $userId ||
            trim((string)$request->totvs_os_number) !== '' || !$request->created_at instanceof \DateTimeInterface) {
            return false;
        }
        $age = ($now ?? \Cake\I18n\DateTime::now('UTC'))->getTimestamp() - $request->created_at->getTimestamp();
        return $age >= 0 && $age < 600;
    }

    public static function canAppend(\App\Model\Entity\ServiceRequest $request): bool
    {
        return $request->flow === RequestFlow::ACOMPANHAMENTO->value && $request->totvs_os_number &&
            in_array($request->status, [\App\Domain\Enum\RequestStatus::OS_ABERTA->value, \App\Domain\Enum\RequestStatus::EM_EXECUCAO->value], true);
    }

    public static function conditions(string $role, int $userId): array
    {
        $profile = UserRole::tryFrom($role);
        if ($profile && $profile->canHandleRequests()) {
            return [];
        }
        if ($profile) {
            return ['OR' => ['ServiceRequests.user_id' => $userId, 'ServiceRequests.flow' => RequestFlow::ACOMPANHAMENTO->value]];
        }

        return ['ServiceRequests.id' => -1];
    }
}
