<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Domain\Enum\MaintenanceType;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

class ServiceRequestsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->belongsTo('Users');
        $this->belongsTo('Sectors');
        $this->hasMany('RequestAttachments', ['foreignKey' => 'request_id', 'sort' => ['RequestAttachments.id' => 'ASC']]);
        $this->belongsTo('LinkedUsers', ['className' => 'Users', 'foreignKey' => 'os_linked_by', 'propertyName' => 'linked_user']);
        $this->belongsTo('CancelledUsers', ['className' => 'Users', 'foreignKey' => 'cancelled_by', 'propertyName' => 'cancelled_user']);
        $this->hasMany('RequestItems', ['foreignKey' => 'request_id', 'sort' => ['position' => 'ASC']]);
        $this->hasMany('RequestAudits', ['foreignKey' => 'request_id', 'sort' => ['RequestAudits.created_at' => 'ASC', 'RequestAudits.id' => 'ASC']]);
        $this->addBehavior('Timestamp', ['events' => [
            'Model.beforeSave' => ['created_at' => 'new', 'updated_at' => 'always'],
        ]]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->requirePresence('os_disposition', 'create', 'Escolha como a Ordem de Serviço deve seguir após a abertura.')->notEmptyString('os_disposition', 'Escolha como a Ordem de Serviço deve seguir após a abertura.')
            ->inList('os_disposition', array_column(\App\Domain\Enum\OsDisposition::cases(), 'value'), 'Selecione uma das três opções de abertura.')
            ->requirePresence('flow', 'create')->notEmptyString('flow', 'Escolha como deseja solicitar a Ordem de Serviço.')
            ->inList('flow', array_column(\App\Domain\Enum\RequestFlow::cases(), 'value'), 'Selecione um tipo de solicitação válido.')
            ->requirePresence('unit', 'create', 'Selecione a unidade.')->notEmptyString('unit', 'Selecione a unidade.')
            ->inList('unit', array_column(\App\Domain\Enum\RequestUnit::cases(), 'value'), 'Selecione Usina ou Fábrica.')
            ->requirePresence('sector_id', 'create', 'Selecione uma mão de obra da lista.')->integer('sector_id')
            ->notEmptyString('sector_id', 'Selecione uma mão de obra da lista.')
            ->requirePresence('asset_code', 'create', 'Selecione um equipamento da lista.')->scalar('asset_code')
            ->notEmptyString('asset_code', 'Selecione um equipamento da lista.')->maxLength('asset_code', 100)
            ->scalar('asset_branch')->allowEmptyString('asset_branch')->maxLength('asset_branch', 100)
            ->requirePresence('maintenance_type', 'create', 'Selecione o tipo de manutenção.')
            ->notEmptyString('maintenance_type', 'Selecione o tipo de manutenção.')->inList('maintenance_type', array_column(MaintenanceType::cases(), 'value'), 'Selecione o tipo de manutenção.')
            ->requirePresence('description', 'create', 'Descreva o serviço ou problema.')->scalar('description')
            ->notEmptyString('description', 'Descreva o serviço ou problema.')->maxLength('description', 5000)
            ->add('description', 'notBlank', ['rule' => fn($value) => trim($value) !== '', 'message' => 'Descreva o serviço ou problema.']);
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['user_id'], 'Users'));
        $rules->add($rules->existsIn(['sector_id'], 'Sectors'));
        $rules->add(function ($entity): bool {
            $number = trim((string)$entity->totvs_os_number);
            if ($number === '') { return true; }
            $query = $this->find()->where(['btrim(ServiceRequests.totvs_os_number)' => $number]);
            if ($entity->id) { $query->where(['ServiceRequests.id !=' => $entity->id]); }
            return $query->count() === 0;
        }, 'uniqueTotvsOs', ['errorField' => 'totvs_os_number', 'message' => 'Esta O.S. já está vinculada a outra solicitação.']);

        return $rules;
    }
}
