<?php
declare(strict_types=1);

namespace App\Service;

final class TotvsAssetQueries
{
    public const SEARCH = <<<'SQL'
SELECT TOP (20) RTRIM(b.T9_CODBEM) AS code, RTRIM(b.T9_NOME) AS description, RTRIM(b.T9_FILIAL) AS branch
FROM dbo.ST9010 b
WHERE b.D_E_L_E_T_ <> '*'
    AND b.T9_SITBEM = 'A'
    AND NULLIF(LTRIM(RTRIM(b.T9_CODBEM)), '') IS NOT NULL
    AND NULLIF(LTRIM(RTRIM(b.T9_NOME)), '') IS NOT NULL
    AND b.T9_NOME COLLATE Latin1_General_100_CI_AI LIKE CAST(:description_term AS NVARCHAR(200)) ESCAPE '~'
ORDER BY b.T9_NOME, b.T9_CODBEM, b.T9_FILIAL
SQL;

    public const FIND = <<<'SQL'
SELECT TOP (2) RTRIM(b.T9_CODBEM) AS code, RTRIM(b.T9_NOME) AS description, RTRIM(b.T9_FILIAL) AS branch
FROM dbo.ST9010 b
WHERE b.D_E_L_E_T_ <> '*'
    AND b.T9_SITBEM = 'A'
    AND b.T9_CODBEM = CAST(:code AS VARCHAR(100))
    AND b.T9_FILIAL = CAST(:branch AS VARCHAR(100))
    AND NULLIF(LTRIM(RTRIM(b.T9_NOME)), '') IS NOT NULL
SQL;

    public static function allows(string $sql): bool
    {
        return in_array($sql, [self::SEARCH, self::FIND], true);
    }
}
