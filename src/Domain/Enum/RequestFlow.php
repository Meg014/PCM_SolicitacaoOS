<?php
declare(strict_types=1);

namespace App\Domain\Enum;

enum RequestFlow: string
{
    case SOMENTE_ABERTURA = 'SOMENTE_ABERTURA';
    case ACOMPANHAMENTO = 'ACOMPANHAMENTO';

    public function label(): string
    {
        return $this === self::ACOMPANHAMENTO ? 'Abertura + acompanhamento da execução' : 'Somente abertura da Ordem de Serviço';
    }

    public static function options(): array
    {
        return array_column(array_map(fn($flow) => ['value' => $flow->value, 'label' => $flow->label()], self::cases()), 'label', 'value');
    }
}
