<?php
declare(strict_types=1);

namespace App\Service;

/** Consultas parametrizadas e diagnóstico, sempre somente leitura. */
final class TotvsEmployeeQueries
{
    public static function lookup(bool $find = false): string
    {
        $columns = 'RTRIM(RA_MAT) AS registration, RTRIM(RA_NOME) AS name, RTRIM(RA_CODFUNC) AS function_code';
        $source = 'FROM DADOSP12.dbo.SRA010';
        if ($find) {
            return "SELECT DISTINCT $columns $source WHERE ISNULL(RTRIM(RA_SITFOLH), '') <> 'D' AND RTRIM(RA_MAT) = :registration";
        }

        return "SELECT TOP (20) $columns $source\n"
            . "WHERE ISNULL(RTRIM(RA_SITFOLH), '') <> 'D' AND (UPPER(RA_NOME) LIKE UPPER(:name_term) OR UPPER(RA_MAT) LIKE UPPER(:registration_term))\n"
            . "GROUP BY RA_MAT, RA_NOME, RA_CODFUNC\n"
            . "ORDER BY CASE WHEN RTRIM(RA_MAT) = :exact_registration OR UPPER(RTRIM(RA_NOME)) = UPPER(:exact_name) THEN 0 ELSE 1 END, name, registration, function_code";
    }
    public const COLUMNS = <<<'SQL'
SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
FROM DADOSP12.INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_CATALOG = 'DADOSP12' AND TABLE_SCHEMA = 'dbo' AND TABLE_NAME = 'SRA010'
    AND COLUMN_NAME IN ('RA_MAT', 'RA_NOME', 'RA_CODFUNC', 'RA_SITFOLH')
ORDER BY COLUMN_NAME
SQL;

    public const ACCESS = <<<'SQL'
SELECT TOP (0) RA_MAT, RA_NOME, RA_CODFUNC, RA_SITFOLH
FROM DADOSP12.dbo.SRA010
SQL;

    public static function allows(string $sql): bool
    {
        if (in_array($sql, [self::COLUMNS, self::ACCESS], true)) {
            return true;
        }
        return $sql === self::lookup() || $sql === self::lookup(true);
    }
}
