<?php
declare(strict_types=1);

namespace App\Controller;

class MaintenanceTypesController extends ServicesController
{
    protected string $catalogTable = 'MaintenanceTypes';
    protected string $catalogTitle = 'Tipos de manutenção';
    protected string $catalogLabel = 'Tipo de manutenção';
    protected string $catalogPath = '/tipos-manutencao';
}
