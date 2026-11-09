<?php
declare(strict_types=1);
namespace App\Controller;
class ServiceComponentsController extends ServicesController
{
    protected string $catalogTable = 'ServiceComponents';
    protected string $catalogTitle = 'Itens/Componentes de serviço';
    protected string $catalogLabel = 'Item/Componente';
    protected string $catalogPath = '/componentes-servico';
}
