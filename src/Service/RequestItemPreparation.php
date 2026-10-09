<?php
declare(strict_types=1);

namespace App\Service;

use Cake\ORM\TableRegistry;

final class RequestItemPreparation
{
    public static function prepare(mixed $rawItems, int $minimum = 0): array
    {
        if (!is_array($rawItems) || count($rawItems) < $minimum || count($rawItems) > 50) {
            throw new \InvalidArgumentException($minimum ? 'Inclua de 1 a 50 apontamentos.' : 'Inclua no máximo 50 apontamentos.');
        }
        $table = TableRegistry::getTableLocator()->get('RequestItems');
        $items = [];
        foreach (array_values($rawItems) as $index => $raw) {
            $item = $table->newEntity(is_array($raw) ? array_intersect_key($raw, array_flip(['description', 'executor', 'service_date', 'start_time', 'end_time'])) : []);
            if ($item->hasErrors()) {
                $messages = [];
                $errors = $item->getErrors();
                array_walk_recursive($errors, function ($message) use (&$messages): void { $messages[] = $message; });
                throw new \InvalidArgumentException('Apontamento ' . ($index + 1) . ': ' . implode(' ', $messages));
            }
            foreach (['start_time' => 'started_at', 'end_time' => 'ended_at'] as $input => $column) {
                $item->set($column, (new \Cake\I18n\DateTime($item->service_date . ' ' . $item->get($input), 'America/Sao_Paulo'))->setTimezone('UTC'));
            }
            $item->set('executor', trim($item->executor));
            $item->set('position', $index + 1);
            foreach (['service_date', 'start_time', 'end_time'] as $field) { $item->unset($field); }
            $items[] = $item;
        }

        return $items;
    }

    public static function snapshot($item): array
    {
        return ['description' => $item->description, 'position' => $item->position, 'executor' => $item->executor,
            'started_at' => $item->started_at->format(DATE_ATOM), 'ended_at' => $item->ended_at->format(DATE_ATOM),
            'recorded_by' => $item->recorded_by];
    }
}
