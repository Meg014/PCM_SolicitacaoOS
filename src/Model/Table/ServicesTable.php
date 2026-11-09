<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Event\EventInterface;
use Cake\Datasource\EntityInterface;
use Cake\Validation\Validator;

class ServicesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setDisplayField('name');
        $this->addBehavior('Timestamp', ['events' => [
            'Model.beforeSave' => ['created_at' => 'new', 'updated_at' => 'always'],
        ]]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        return $validator->requirePresence('name', 'create')->scalar('name')
            ->notEmptyString('name', 'Informe o nome do serviço.')
            ->maxLength('name', 150)
            ->add('name', 'notBlank', [
                'rule' => static fn($value): bool => is_string($value) && trim($value) !== '',
                'message' => 'Informe o nome do serviço.',
            ])
            ->boolean('active');
    }

    public function beforeSave(EventInterface $event, EntityInterface $entity, \ArrayObject $options): void
    {
        if (is_string($entity->name)) {
            $entity->set('name', trim($entity->name));
        }
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add(function ($entity): bool {
            $query = $this->find()->where(['name' => trim((string)$entity->name)]);
            if ($entity->id) {
                $query->where(['id !=' => $entity->id]);
            }

            return $query->count() === 0;
        }, 'uniqueServiceName', [
            'errorField' => 'name',
            'message' => 'Este serviço já está cadastrado.',
        ]);

        return $rules;
    }
}
