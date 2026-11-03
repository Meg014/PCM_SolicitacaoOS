<?php
declare(strict_types=1);

namespace App\Service;

class EmployeesUnavailableException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Não foi possível consultar os funcionários agora. Tente novamente em instantes.');
    }
}
