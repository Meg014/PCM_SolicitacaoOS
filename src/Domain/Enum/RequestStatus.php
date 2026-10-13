<?php
declare(strict_types=1);

namespace App\Domain\Enum;

enum RequestStatus: string
{
    case AGUARDANDO_PCM = 'AGUARDANDO_PCM';
    case EM_ANALISE = 'EM_ANALISE';
    case OS_ABERTA = 'OS_ABERTA';
    case EM_EXECUCAO = 'EM_EXECUCAO';
    case AGUARDANDO_ENCERRAMENTO = 'AGUARDANDO_ENCERRAMENTO';
    case ATENDIDA = 'ATENDIDA';
    case ENCERRADA = 'ENCERRADA';
    case CANCELADA = 'CANCELADA';

    public function label(): string
    {
        return match ($this) {
            self::AGUARDANDO_PCM => 'Aguardando PCM',
            self::EM_ANALISE => 'Em análise pelo PCM',
            self::OS_ABERTA => 'O.S. aberta - aguardando execução',
            self::EM_EXECUCAO => 'Serviço em andamento',
            self::AGUARDANDO_ENCERRAMENTO => 'Aguardando encerramento no TOTVS',
            self::ATENDIDA => 'Atendida',
            self::ENCERRADA => 'Encerrada',
            self::CANCELADA => 'Solicitação cancelada',
        };
    }

    public static function activeValues(): array
    {
        return array_map(fn($s) => $s->value, array_filter(self::cases(), fn($s) => !in_array($s, [self::ATENDIDA, self::ENCERRADA, self::CANCELADA], true)));
    }
}
