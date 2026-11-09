<?php
declare(strict_types=1);

namespace App\Model\Table;

class ServiceActionsTable extends ServicesTable
{
    public function beforeDelete(\Cake\Event\EventInterface $event): void
    {
        $event->stopPropagation();
    }
}
