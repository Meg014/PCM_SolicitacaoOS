<?php
declare(strict_types=1);

namespace App\Domain\Enum;

enum UserRole: string
{
    case ADMIN_PCM = 'ADMIN_PCM';
    case GESTOR = 'GESTOR';
    case SOLICITANTE = 'SOLICITANTE';
    case MANTENEDOR = 'MANTENEDOR';
    case ENCARREGADO = 'ENCARREGADO';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN_PCM => 'PCM / Administrador', self::GESTOR => 'Gestor',
            self::SOLICITANTE => 'Solicitante', self::MANTENEDOR => 'Mantenedor', self::ENCARREGADO => 'Encarregado',
        };
    }

    public function hasDashboard(): bool
    {
        return in_array($this, [self::ADMIN_PCM, self::GESTOR, self::ENCARREGADO], true);
    }

    public function hasReports(): bool
    {
        return in_array($this, [self::ADMIN_PCM, self::GESTOR], true);
    }

    public static function options(): array
    {
        return array_column(array_map(fn($role) => ['value' => $role->value, 'label' => $role->label()], self::cases()), 'label', 'value');
    }
}
