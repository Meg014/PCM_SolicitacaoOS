<?php
declare(strict_types=1);

namespace App\Service;

/** Consultas parametrizadas e diagnóstico, sempre somente leitura. */
final class TotvsEmployeeQueries
{
    public const ALLOWED_SITUATIONS = ['', 'F', 'A'];

    public static function lookup(bool $find = false): string
    {
        $situations = implode(', ', array_map(fn($s) => "'" . $s . "'", self::ALLOWED_SITUATIONS));
        $filter = $find ? 'RTRIM(RA_MAT) = :registration'
            : '(UPPER(RA_NOME) LIKE UPPER(:name_term) OR UPPER(RA_MAT) LIKE UPPER(:registration_term))';
        $limit = $find ? 2 : 20;

        return "SELECT TOP ($limit) RTRIM(RA_MAT) AS registration, RTRIM(RA_NOME) AS name, RTRIM(RA_CODFUNC) AS function_code, LTRIM(RTRIM(RA_SITFOLH)) AS situation\n"
            . "FROM DADOSP12.dbo.SRA010\nWHERE LTRIM(RTRIM(RA_SITFOLH)) IN ($situations)\n"
            . "AND NULLIF(LTRIM(RTRIM(RA_MAT)), '') IS NOT NULL AND NULLIF(LTRIM(RTRIM(RA_NOME)), '') IS NOT NULL\n"
            . "AND $filter\nORDER BY name, registration, function_code, situation";
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
