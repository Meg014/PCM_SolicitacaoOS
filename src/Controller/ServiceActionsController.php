<?php
declare(strict_types=1);
namespace App\Controller;
class ServiceActionsController extends ServicesController
{
    protected string $catalogTable = 'ServiceActions';
    protected string $catalogTitle = 'Ações de serviço';
    protected string $catalogLabel = 'Ação';
    protected string $catalogPath = '/acoes-servico';
}
