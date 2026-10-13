<?php
declare(strict_types=1);

namespace App\Service;

use App\Domain\Enum\RequestStatus;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\TableRegistry;

class RequestReportService
{
    public function query(array $filters): SelectQuery
    {
        $query = TableRegistry::getTableLocator()->get('ServiceRequests')->find();
        foreach (['start', 'end', 'status', 'sector_id', 'user_id', 'asset', 'q', 'unit', 'flow', 'maintenance_type', 'totvs_os_number', 'department_id'] as $key) {
            if (isset($filters[$key]) && !is_string($filters[$key])) { throw new \InvalidArgumentException('Revise os filtros informados.'); }
        }
        $dates = [];
        if (($filters['maintenance_type'] ?? '') !== '') {
            if (!\App\Domain\Enum\MaintenanceType::tryFrom($filters['maintenance_type'])) { throw new \InvalidArgumentException('Selecione um tipo de manutenção válido.'); }
            $query->where(['ServiceRequests.maintenance_type' => $filters['maintenance_type']]);
        }
        if (($filters['department_id'] ?? '') !== '') {
            if ($filters['department_id'] === 'UNSPECIFIED') {
                $query->matching('Users', fn($q) => $q->where(['Users.department_id IS' => null]));
            } elseif (ctype_digit($filters['department_id'])) {
                $query->matching('Users', fn($q) => $q->where(['Users.department_id' => (int)$filters['department_id']]));
            } else { throw new \InvalidArgumentException('Selecione um setor do solicitante válido.'); }
        }
        if (trim($filters['totvs_os_number'] ?? '') !== '') {
            $number = trim($filters['totvs_os_number']);
            if (mb_strlen($number) > 100) { throw new \InvalidArgumentException('Use até 100 caracteres no número da O.S.'); }
            $query->where(['btrim(ServiceRequests.totvs_os_number)' => $number]);
        }
        if (($filters['flow'] ?? '') !== '') {
            if (!\App\Domain\Enum\RequestFlow::tryFrom($filters['flow'])) { throw new \InvalidArgumentException('Selecione um tipo de solicitação válido.'); }
            $query->where(['ServiceRequests.flow' => $filters['flow']]);
        }
        if (($filters['unit'] ?? '') !== '') {
            if (\App\Domain\Enum\RequestUnit::tryFrom($filters['unit'])) { $query->where(['ServiceRequests.unit' => $filters['unit']]); }
            elseif ($filters['unit'] === 'UNSPECIFIED') { $query->where(['ServiceRequests.unit IS' => null]); }
            else { throw new \InvalidArgumentException('Selecione uma unidade válida.'); }
        }
        foreach (['start', 'end'] as $key) {
            $value = $filters[$key] ?? '';
            if ($value === '') { continue; }
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('America/Sao_Paulo'));
            if (!$date || $date->format('Y-m-d') !== $value) { throw new \InvalidArgumentException('Informe datas válidas para o período.'); }
            $dates[$key] = $date;
            $utc = ($key === 'end' ? $date->modify('+1 day') : $date)->setTimezone(new \DateTimeZone('UTC'));
            $query->where(['ServiceRequests.created_at ' . ($key === 'end' ? '<' : '>=') => $utc->format('Y-m-d H:i:s')]);
        }
        if (isset($dates['start'], $dates['end']) && $dates['start'] > $dates['end']) { throw new \InvalidArgumentException('A data inicial deve ser anterior ou igual à final.'); }
        if (($filters['status'] ?? '') !== '') {
            if (!RequestStatus::tryFrom($filters['status'])) { throw new \InvalidArgumentException('Selecione uma situação válida.'); }
            $query->where(['ServiceRequests.status' => $filters['status']]);
        }
        foreach (['sector_id', 'user_id'] as $key) {
            if (($filters[$key] ?? '') === '') { continue; }
            if (!ctype_digit($filters[$key])) { throw new \InvalidArgumentException('Selecione uma mão de obra ou solicitante válido.'); }
            $query->where(['ServiceRequests.' . $key => (int)$filters[$key]]);
        }
        foreach (['asset', 'q'] as $key) {
            $value = trim($filters[$key] ?? '');
            if ($value === '') { continue; }
            if (mb_strlen($value) > 100) { throw new \InvalidArgumentException('Use até 100 caracteres para pesquisar.'); }
            $pattern = '%' . strtr($value, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
            $fields = $key === 'asset' ? ['asset_code', 'asset_description'] : ['number', 'description', 'asset_code', 'asset_description', 'totvs_os_number'];
            $or = [];
            foreach ($fields as $field) { $or['ServiceRequests.' . $field . ' ILIKE'] = $pattern; }
            $query->where(['OR' => $or]);
        }

        return $query;
    }

    public function indicators(array $filters): array
    {
        $query = $this->query(array_intersect_key($filters, array_flip(['start', 'end'])));
        $group = static function (SelectQuery $base, string $field): array {
            $q = clone $base;
            return $q->select(['category' => 'ServiceRequests.' . $field, 'total' => $q->func()->count('*')])
                ->groupBy(['ServiceRequests.' . $field])->orderBy(['total' => 'DESC'])->disableHydration()->toArray();
        };
        $equipment = clone $query;
        $equipment->select(['code' => 'ServiceRequests.asset_code', 'branch' => 'ServiceRequests.asset_branch',
            'name' => $equipment->func()->max('ServiceRequests.asset_description'), 'total' => $equipment->func()->count('*')])
            ->groupBy(['ServiceRequests.asset_code', 'ServiceRequests.asset_branch'])->orderBy(['total' => 'DESC'])->limit(10);
        $average = clone $query;
        $average->select(['seconds' => $average->expr('AVG(EXTRACT(EPOCH FROM (ServiceRequests.os_linked_at - ServiceRequests.created_at)))')]);

        return [
            'total' => $query->count(), 'status' => $group($query, 'status'), 'sector' => $group($query, 'sector_id'),
            'user' => $group($query, 'user_id'), 'type' => $group($query, 'maintenance_type'),
            'equipment' => $equipment->disableHydration()->toArray(),
            'average_seconds' => $average->disableHydration()->first()['seconds'],
        ];
    }
}
