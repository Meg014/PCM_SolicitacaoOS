<?php
declare(strict_types=1);
namespace App\Service;

final class TotvsOrderQueries
{
    public static function batch(int $size): string
    {
        if ($size < 1 || $size > 50) { throw new \InvalidArgumentException('Lote de O.S. inválido.'); }
        $params = array_map(fn($i) => '(CAST(:number_' . $i . ' AS VARCHAR(100)))', range(0, $size - 1));
        return "SELECT TOP (101) RTRIM(j.TJ_ORDEM) AS number, RTRIM(j.TJ_FILIAL) AS branch,
            RTRIM(j.TJ_CODBEM) AS asset_code, RTRIM(j.TJ_SITUACA) AS situation, RTRIM(j.TJ_TERMINO) AS finished
            FROM dbo.STJ010 j INNER JOIN (VALUES " . implode(', ', $params) . ") wanted(number) ON j.TJ_ORDEM = wanted.number
            WHERE j.D_E_L_E_T_ <> '*'";
    }

    public const DETAIL = <<<'SQL'
SELECT TOP (3) RTRIM(j.TJ_ORDEM) AS number, RTRIM(j.TJ_FILIAL) AS branch, RTRIM(j.TJ_CODBEM) AS asset_code,
    (SELECT COUNT(*) FROM dbo.STJ010 identity_row WHERE identity_row.TJ_ORDEM = j.TJ_ORDEM AND identity_row.TJ_FILIAL = j.TJ_FILIAL AND identity_row.D_E_L_E_T_ <> '*') AS identity_count,
    RTRIM(j.TJ_SITUACA) AS situation, RTRIM(j.TJ_TERMINO) AS finished, RTRIM(j.TJ_SERVICO) AS service_code,
    RTRIM(j.TJ_CCUSTO) AS cost_center, RTRIM(j.TJ_CODAREA) AS area, RTRIM(j.TJ_TIPO) AS maintenance_code,
    CONVERT(VARCHAR(MAX), j.TJ_OBSERVA) AS description, j.TJ_DTORIGI AS origin_date,
    j.TJ_DTMPINI AS planned_start, j.TJ_HOMPINI AS planned_start_time,
    j.TJ_DTMPFIM AS planned_end, j.TJ_HOMPFIM AS planned_end_time,
    j.TJ_DTMRINI AS actual_start, j.TJ_HOMRINI AS actual_start_time,
    j.TJ_DTMRFIM AS actual_end, j.TJ_HOMRFIM AS actual_end_time,
    CASE WHEN bl.matches > 0 THEN bl.name ELSE bs.name END AS asset_name,
    CASE WHEN bl.matches > 0 THEN bl.matches ELSE bs.matches END AS asset_matches,
    CASE WHEN sl.matches > 0 THEN sl.name ELSE ss.name END AS service_name,
    CASE WHEN sl.matches > 0 THEN sl.matches ELSE ss.matches END AS service_matches
FROM dbo.STJ010 j
OUTER APPLY (SELECT COUNT(*) AS matches, MAX(b.T9_NOME) AS name FROM dbo.ST9010 b WHERE b.T9_CODBEM = j.TJ_CODBEM AND b.T9_FILIAL = j.TJ_FILIAL AND b.D_E_L_E_T_ <> '*') bl
OUTER APPLY (SELECT COUNT(*) AS matches, MAX(b.T9_NOME) AS name FROM dbo.ST9010 b WHERE b.T9_CODBEM = j.TJ_CODBEM AND b.T9_FILIAL = '' AND b.D_E_L_E_T_ <> '*') bs
OUTER APPLY (SELECT COUNT(*) AS matches, MAX(s.T4_NOME) AS name FROM dbo.ST4010 s WHERE s.T4_SERVICO = j.TJ_SERVICO AND s.T4_FILIAL = j.TJ_FILIAL AND s.D_E_L_E_T_ <> '*') sl
OUTER APPLY (SELECT COUNT(*) AS matches, MAX(s.T4_NOME) AS name FROM dbo.ST4010 s WHERE s.T4_SERVICO = j.TJ_SERVICO AND s.T4_FILIAL = '' AND s.D_E_L_E_T_ <> '*') ss
WHERE j.D_E_L_E_T_ <> '*' AND j.TJ_ORDEM = CAST(:number AS VARCHAR(100))
    AND (CAST(:branch AS VARCHAR(100)) = '' OR j.TJ_FILIAL = CAST(:exact_branch AS VARCHAR(100)))
    AND j.TJ_CODBEM = CAST(:asset AS VARCHAR(100))
SQL;

    public static function allows(string $sql): bool
    {
        if ($sql === self::DETAIL) { return true; }
        for ($size = 1; $size <= 50; $size++) { if ($sql === self::batch($size)) { return true; } }
        return false;
    }
}
