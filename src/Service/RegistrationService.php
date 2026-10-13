<?php
declare(strict_types=1);

namespace App\Service;

use Cake\Datasource\EntityInterface;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;

class RegistrationService
{
    public function save(Table $table, EntityInterface $entity, ?array $before, ?int $actorId, ?string $action = null): void
    {
        $table->getConnection()->transactional(function () use ($table, $entity, $before, $actorId, $action): void {
            $passwordChanged = $entity->isDirty('password');
            $table->saveOrFail($entity);
            $fields = $table->getAlias() === 'Users' ? ['name', 'username', 'email', 'role', 'active', 'must_change_password', 'department_id'] : ['code', 'name', 'active'];
            $after = array_intersect_key($entity->toArray(), array_flip($fields));
            if ($table->getAlias() === 'Users') {
                $after['password_changed'] = $passwordChanged;
            }
            $audits = TableRegistry::getTableLocator()->get('RegistrationAudits');
            $audit = $audits->newEntity([
                'user_id' => $actorId, 'resource' => $table->getAlias(), 'record_id' => $entity->get('id'),
                'action' => $action ?? ($before === null ? 'CREATED' : 'UPDATED'),
                'old_values' => $before === null ? null : array_intersect_key($before, array_flip($fields)),
                'new_values' => $after, 'created_at' => new \Cake\I18n\DateTime('now', 'UTC'),
            ]);
            $audits->saveOrFail($audit);
        });
    }
}
