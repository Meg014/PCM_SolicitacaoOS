<?php
declare(strict_types=1);

namespace App\Service;

final class SearchText
{
    private const ACCENTED = 'áàâãäåéèêëíìîïóòôõöúùûüçñ';
    private const PLAIN = 'aaaaaaeeeeiiiiooooouuuucn';

    public static function normalize(string $term): string
    {
        $term = \Normalizer::normalize($term, \Normalizer::FORM_C) ?: $term;
        return strtr(mb_strtolower($term), array_combine(mb_str_split(self::ACCENTED), str_split(self::PLAIN)));
    }

    public static function sql(string $column): string
    {
        if (!in_array($column, ['name', 'code'], true)) { throw new \InvalidArgumentException('Coluna de busca inválida.'); }
        return "translate(lower($column), '" . self::ACCENTED . "', '" . self::PLAIN . "')";
    }
}
