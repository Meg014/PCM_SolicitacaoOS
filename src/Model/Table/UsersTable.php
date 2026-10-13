<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Domain\Enum\UserRole;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class UsersTable extends Table
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
        return $validator
            ->scalar('name')->requirePresence('name', 'create')
            ->notEmptyString('name', 'Informe o nome.')->maxLength('name', 150)
            ->add('name', 'notBlank', ['rule' => fn($value) => trim($value) !== '', 'message' => 'Informe o nome.'])
            ->scalar('username')->allowEmptyString('username')
            ->maxLength('username', 60)
            ->add('username', 'format', ['rule' => ['custom', '/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,59}$/'], 'message' => 'Use letras sem acento, números, ponto, hífen ou sublinhado, sem espaços ou @.'])
            ->email('email', false, 'Informe um e-mail válido.')
            ->allowEmptyString('email')
            ->maxLength('email', 254)
            ->scalar('password')->requirePresence('password', 'create')
            ->notEmptyString('password', 'Informe a senha.')
            ->minLength('password', 6, 'Use uma senha com pelo menos 6 caracteres.')
            ->requirePresence('role', 'create')->notEmptyString('role', 'Selecione o perfil.')
            ->inList('role', array_column(UserRole::cases(), 'value'), 'Selecione um perfil válido.')
            ->boolean('active', 'Informe se o usuário está ativo.');
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add(fn($entity) => trim((string)$entity->username) !== '' || trim((string)$entity->email) !== '',
            'loginIdentifier', ['errorField' => 'username', 'message' => 'Informe usuário/matrícula ou e-mail. Pelo menos um deles é necessário.']);
        $rules->add($rules->isUnique(['username'], ['allowMultipleNulls' => true]), ['errorField' => 'username', 'message' => 'Este usuário já está cadastrado.']);
        $rules->add($rules->isUnique(['email'], ['allowMultipleNulls' => true]), ['errorField' => 'email', 'message' => 'Este e-mail já está cadastrado.']);

        return $rules;
    }

    public function findActive(SelectQuery $query): SelectQuery
    {
        return $query->where(['Users.active' => true]);
    }
}
