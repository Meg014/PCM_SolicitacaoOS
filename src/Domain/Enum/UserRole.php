<?php
declare(strict_types=1);

namespace App\Domain\Enum;

enum UserRole: string
{
    case ADMIN_PCM = 'ADMIN_PCM';
    case PCM = 'PCM';
    case MANTENEDOR = 'MANTENEDOR';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN_PCM => 'Administrador PCM',
            self::PCM => 'PCM',
            self::MANTENEDOR => 'Mantenedor',
        };
    }

    public function canHandleRequests(): bool
    {
        return in_array($this, [self::ADMIN_PCM, self::PCM], true);
    }

    public function hasDashboard(): bool
    {
        return $this->canHandleRequests();
    }

    public function hasReports(): bool
    {
        return $this->canHandleRequests();
    }

    public static function options(): array
    {
        return array_column(array_map(fn($role) => ['value' => $role->value, 'label' => $role->label()], self::cases()), 'label', 'value');
    }
}
