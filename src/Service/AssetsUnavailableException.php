<?php
declare(strict_types=1);

namespace App\Service;

class AssetsUnavailableException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Não foi possível consultar os equipamentos agora. Tente novamente em instantes.');
    }
}
