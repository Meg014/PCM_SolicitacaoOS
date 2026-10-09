<?php
declare(strict_types=1);

namespace App\Domain\Enum;

enum OsDisposition: string
{
    case SOMENTE_ABERTURA = 'SOMENTE_ABERTURA';
    case MANTER_ABERTA = 'MANTER_ABERTA';
    case PODE_ENCERRAR = 'PODE_ENCERRAR';

    public function label(): string
    {
        return match ($this) {
            self::SOMENTE_ABERTURA => 'Somente abrir a Ordem de Serviço',
            self::MANTER_ABERTA => 'Abrir e manter em andamento',
            self::PODE_ENCERRAR => 'Abrir e pode encerrar',
        };
    }

    public function heading(): string
    {
        return match ($this) {
            self::SOMENTE_ABERTURA => 'SOMENTE ABRIR A O.S.',
            self::MANTER_ABERTA => 'MANTER O.S. ABERTA', self::PODE_ENCERRAR => 'O.S. PODE SER ENCERRADA',
        };
    }

    public function explanation(): string
    {
        return match ($this) {
            self::SOMENTE_ABERTURA => 'Quero apenas solicitar a abertura. Não haverá acompanhamento de execução neste sistema.',
            self::MANTER_ABERTA => 'O serviço continuará e poderão ser feitos novos apontamentos por outras pessoas ou turnos.',
            self::PODE_ENCERRAR => 'O serviço já foi concluído e, após a abertura e tratamento pelo PCM, a O.S. pode ser encerrada.',
        };
    }
}
