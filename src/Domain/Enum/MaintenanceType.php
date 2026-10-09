<?php
declare(strict_types=1);

namespace App\Domain\Enum;

enum MaintenanceType: string
{
    case PROGRAMADA = 'PROGRAMADA';
    case CORRETIVA = 'CORRETIVA';
    case PREVENTIVA = 'PREVENTIVA';
    case APOIO_PROCESSO = 'APOIO_PROCESSO';
    case NAO_PROGRAMADA = 'NAO_PROGRAMADA';

    public function label(): string
    {
        return match ($this) {
            self::PROGRAMADA => 'Programada', self::CORRETIVA => 'Corretiva',
            self::PREVENTIVA => 'Preventiva', self::APOIO_PROCESSO => 'Apoio ao processo',
            self::NAO_PROGRAMADA => 'Não programada',
        };
    }

    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $type) {
            $options[$type->value] = $type->label();
        }

        return $options;
    }
}
