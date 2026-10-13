<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Event\EventInterface;
use Cake\Datasource\EntityInterface;
use Cake\ORM\TableRegistry;

class RequestAuditsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->belongsTo('Users');
    }

    public function beforeSave(EventInterface $event, EntityInterface $entity, \ArrayObject $options): void
    {
        if (!$entity->isNew()) { return; }
        $user = $entity->user_id ? $this->Users->get($entity->user_id) : null;
        $entity->set('actor_snapshot', $user ? array_intersect_key($user->toArray(),
            array_flip(['id', 'name', 'username', 'email', 'role'])) : ['id' => null, 'name' => 'Sistema', 'username' => null, 'email' => null, 'role' => null]);
        $current = TableRegistry::getTableLocator()->get('ServiceRequests')->get($entity->request_id)->status;
        $entity->set('old_status', $entity->action === 'REQUEST_CREATED' ? null : ($entity->old_values['status'] ?? $current));
        $entity->set('new_status', $entity->new_values['status'] ?? $current);
    }
}
