<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class UserDepartmentsTable extends Table
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
        return $validator->scalar('name')->requirePresence('name', 'create', 'Informe o nome do setor.')
            ->notEmptyString('name', 'Informe o nome do setor.')->maxLength('name', 150)
            ->add('name', 'notBlank', ['rule' => fn($value) => trim($value) !== '', 'message' => 'Informe o nome do setor.'])
            ->boolean('active');
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['name']), ['errorField' => 'name', 'message' => 'Este setor já está cadastrado.']);
        return $rules;
    }
}
