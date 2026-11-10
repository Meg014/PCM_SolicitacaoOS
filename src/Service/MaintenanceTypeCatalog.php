<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\TableRegistry;

class MaintenanceTypeCatalog
{
    public static function options(bool $activeOnly = false): array
    {
        $query = TableRegistry::getTableLocator()->get('MaintenanceTypes')->find()->orderBy(['name' => 'ASC']);
        if ($activeOnly) {
            $query->where(['active' => true]);
        }
        return $query->find('list', keyField: 'code', valueField: 'name')->toArray();
    }

    public static function label(string $code): string
    {
        return self::options()[$code] ?? $code;
    }
}
