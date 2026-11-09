<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\TableRegistry;

final class ServiceSelection
{
    public static function resolve(mixed $actionId, mixed $componentId, bool $lock = false): array
    {
        $values = [];
        foreach ([['ServiceActions', 'service_actions', 'service_action', $actionId, 'ação'],
            ['ServiceComponents', 'service_components', 'service_component', $componentId, 'item/componente']] as [$alias, $table, $field, $id, $label]) {
            if ((!is_string($id) && !is_int($id)) || !ctype_digit((string)$id) || (int)$id < 1) {
                throw new \InvalidArgumentException('Selecione uma opção ativa de ' . $label . '.', $field === 'service_component' ? 2 : 1);
            }
            $connection = TableRegistry::getTableLocator()->get($alias)->getConnection();
            $row = $connection->execute("SELECT id, name FROM $table WHERE id = :id AND active = TRUE" . ($lock ? ' FOR SHARE' : ''), ['id' => (int)$id])->fetch('assoc');
            if (!$row) { throw new \InvalidArgumentException('Selecione uma opção ativa de ' . $label . '.', $field === 'service_component' ? 2 : 1); }
            $values[$field . '_id'] = (int)$row['id'];
            $values[$field . '_name'] = $row['name'];
        }
        $action = $values['service_action_name'];
        $component = mb_strtolower($values['service_component_name'], 'UTF-8');
        $values['description'] = mb_strtolower($action, 'UTF-8') === 'limpar' && $component === 'geral'
            ? 'Limpeza geral' : $action . ' ' . $component;
        // Legacy single-service snapshots remain untouched on historical records.
        return $values + ['service_id' => null, 'service_name' => null];
    }

    public static function options(): array
    {
        $locator = TableRegistry::getTableLocator();
        return [
            'serviceActionOptions' => $locator->get('ServiceActions')->find('list')->where(['active' => true])->orderBy(['name' => 'ASC'])->toArray(),
            'serviceComponentOptions' => $locator->get('ServiceComponents')->find('list')->where(['active' => true])->orderBy(['name' => 'ASC'])->toArray(),
        ];
    }
}
