<?php
declare(strict_types=1);
namespace App\Service;

final class TotvsOrderState
{
    public static function resolve(array $row): ?string
    {
        // ProtheusOperationalEligibility da referência: não interpretar P/C/valores desconhecidos como encerramento.
        if (($row['situation'] ?? null) !== 'L') { return null; }
        return match ($row['finished'] ?? null) { 'N' => 'OPEN', 'S' => 'CLOSED', default => null };
    }
}
