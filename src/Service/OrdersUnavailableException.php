<?php
declare(strict_types=1);
namespace App\Service;

class OrdersUnavailableException extends \RuntimeException
{
    public function __construct() { parent::__construct('Não foi possível consultar a Ordem de Serviço no TOTVS agora. Tente novamente mais tarde.'); }
}
