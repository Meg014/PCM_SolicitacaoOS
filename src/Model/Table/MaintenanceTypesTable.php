<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;

class MaintenanceTypesTable extends ServicesTable
{
    public function beforeSave(EventInterface $event, EntityInterface $entity, \ArrayObject $options): void
    {
        parent::beforeSave($event, $entity, $options);
        if ($entity->isNew() && !$entity->code) {
            $entity->set('code', 'MT_' . bin2hex(random_bytes(12)));
        }
    }

    public function beforeDelete(EventInterface $event): void
    {
        $event->stopPropagation();
    }
}
