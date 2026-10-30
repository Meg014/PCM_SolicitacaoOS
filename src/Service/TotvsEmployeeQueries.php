<?php
declare(strict_types=1);

namespace App\Service;

/** Consultas fixas de diagnóstico; não leem dados pessoais. */
final class TotvsEmployeeQueries
{
    public const COLUMNS = <<<'SQL'
SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = 'SRA010'
    AND COLUMN_NAME IN ('RA_MAT', 'RA_NOME', 'RA_CODFUNC', 'RA_SITFOLH', 'RA_FILIAL', 'D_E_L_E_T_')
ORDER BY COLUMN_NAME
SQL;

    public const ACCESS = <<<'SQL'
SELECT TOP (0) RA_MAT, RA_NOME, RA_CODFUNC, RA_SITFOLH, RA_FILIAL, D_E_L_E_T_
FROM dbo.SRA010
SQL;

    public static function allows(string $sql): bool
    {
        return in_array($sql, [self::COLUMNS, self::ACCESS], true);
    }
}
