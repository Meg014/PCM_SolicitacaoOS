<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class RequestItemsTable extends Table
{
    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->requirePresence('executor', 'create', 'Informe o executante.')->scalar('executor')->notEmptyString('executor', 'Informe o executante.')
            ->maxLength('executor', 150)
            ->add('executor', 'notBlank', ['rule' => fn($value) => trim($value) !== '', 'message' => 'Informe o executante.'])
            ->requirePresence('service_date', 'create', 'Informe a data do serviço.')->notEmptyString('service_date', 'Informe a data do serviço.')
            ->add('service_date', 'calendar', ['rule' => static function ($value): bool {
                if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) { return false; }
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                return $date && $date->format('Y-m-d') === $value;
            }, 'message' => 'Informe uma data válida.'])
            ->requirePresence('start_time', 'create', 'Informe a hora inicial.')->notEmptyString('start_time', 'Informe a hora inicial.')
            ->add('start_time', 'clock', ['rule' => ['custom', '/^(?:[01]\d|2[0-3]):[0-5]\d$/D'], 'message' => 'Informe a hora inicial no formato HH:MM.'])
            ->requirePresence('end_time', 'create', 'Informe a hora final.')->notEmptyString('end_time', 'Informe a hora final.')
            ->add('end_time', 'clock', ['rule' => ['custom', '/^(?:[01]\d|2[0-3]):[0-5]\d$/D'], 'message' => 'Informe a hora final no formato HH:MM.'])
            ->add('end_time', 'afterStart', ['rule' => static function ($value, array $context): bool {
                $start = $context['data']['start_time'] ?? null;
                return !is_string($start) || !is_string($value) || $value >= $start;
            }, 'message' => 'A hora final não pode ser anterior à hora inicial.'])
            ->requirePresence('description', 'create', 'Descreva este apontamento.')->scalar('description')
            ->notEmptyString('description', 'Descreva este apontamento.')->maxLength('description', 2000)
            ->add('description', 'notBlank', ['rule' => fn($value) => trim($value) !== '', 'message' => 'Descreva este apontamento.']);
    }
}
