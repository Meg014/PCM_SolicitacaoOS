<?php
declare(strict_types=1);

namespace App\Service;

/** Consultas parametrizadas e diagnóstico, sempre somente leitura. */
final class TotvsEmployeeQueries
{
    public const ALLOWED_SITUATIONS = ['', 'F'];

    public static function lookup(int $functions, bool $find = false): string
    {
        if ($functions < 1 || $functions > 500) {
            throw new \InvalidArgumentException('Quantidade de funções fora do limite de consulta.');
        }
        $codes = implode(', ', array_map(fn($i) => ':function_' . $i, range(0, $functions - 1)));
        $situations = implode(', ', array_map(fn($s) => "'" . $s . "'", self::ALLOWED_SITUATIONS));
        $filter = $find ? 'RA_MAT = CAST(:registration AS VARCHAR(100))'
            : "(RA_NOME COLLATE Latin1_General_100_CI_AI LIKE CAST(:name_term AS NVARCHAR(200)) ESCAPE '~' OR RA_MAT LIKE CAST(:registration_term AS VARCHAR(100)) ESCAPE '~')";
        $limit = $find ? 2 : 20;

        return "SELECT DISTINCT TOP ($limit) RTRIM(RA_MAT) AS registration, RTRIM(RA_NOME) AS name, RTRIM(RA_CODFUNC) AS function_code, LTRIM(RTRIM(RA_SITFOLH)) AS situation\n"
            . "FROM dbo.SRA010\nWHERE RA_CODFUNC IN ($codes) AND LTRIM(RTRIM(RA_SITFOLH)) IN ($situations)\n"
            . "AND NULLIF(LTRIM(RTRIM(RA_MAT)), '') IS NOT NULL AND NULLIF(LTRIM(RTRIM(RA_NOME)), '') IS NOT NULL\n"
            . "AND $filter\nORDER BY name, registration, function_code, situation";
    }
    public const COLUMNS = <<<'SQL'
SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'dbo' AND TABLE_NAME = 'SRA010'
    AND COLUMN_NAME IN ('RA_MAT', 'RA_NOME', 'RA_CODFUNC', 'RA_SITFOLH')
ORDER BY COLUMN_NAME
SQL;

    public const ACCESS = <<<'SQL'
SELECT TOP (0) RA_MAT, RA_NOME, RA_CODFUNC, RA_SITFOLH
FROM dbo.SRA010
SQL;

    public static function allows(string $sql): bool
    {
        if (in_array($sql, [self::COLUMNS, self::ACCESS], true)) {
            return true;
        }
        $count = preg_match_all('/:function_\d+\b/', $sql);
        return $count >= 1 && $count <= 500 &&
            ($sql === self::lookup($count) || $sql === self::lookup($count, true));
    }
}
