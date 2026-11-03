<?php
declare(strict_types=1);

namespace App\Service;

final class AuthorizedFunctionCode
{
    public static function forTotvs(string $code): string
    {
        $code = trim($code);
        // RA_CODFUNC has five characters. Never convert to integer or truncate.
        return preg_match('/^[0-9]{1,5}$/D', $code) ? str_pad($code, 5, '0', STR_PAD_LEFT) : $code;
    }
}
