<?php
declare(strict_types=1);

namespace App\Domain\Enum;

enum RequestUnit: string
{
    case USINA = 'USINA';
    case FABRICA = 'FABRICA';

    public static function options(): array
    {
        return [self::USINA->value => 'Usina', self::FABRICA->value => 'Fábrica'];
    }

    public static function display(?string $value): string
    {
        return self::options()[$value ?? ''] ?? 'Não informada';
    }
}
