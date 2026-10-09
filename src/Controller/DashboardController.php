<?php
declare(strict_types=1);

namespace App\Controller;

use App\Domain\Enum\RequestStatus;
use App\Domain\Enum\UserRole;
use Cake\Event\EventInterface;
use Cake\Http\Exception\ForbiddenException;

class DashboardController extends AppController
{
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);
        if ($event->isStopped()) { return; }
        $identity = $this->request->getAttribute('identity');
        if ($identity && !UserRole::tryFrom($identity->get('role'))?->hasDashboard()) {
            throw new ForbiddenException('O painel é exclusivo do PCM e dos gestores.');
        }
    }

    public function index(): void
    {
        $table = $this->fetchTable('ServiceRequests');
        $conditions = [];
        $errors = [];
        $filters = [];
        foreach (['status', 'sector_id', 'start', 'end', 'unit', 'flow'] as $key) {
            $value = $this->request->getQuery($key, '');
            $filters[$key] = is_string($value) ? $value : '';
            if (!is_string($value)) { $errors[] = 'Revise os filtros informados.'; }
        }
        if ($filters['status'] !== '') {
            if (RequestStatus::tryFrom($filters['status'])) { $conditions['ServiceRequests.status'] = $filters['status']; }
            else { $errors[] = 'Selecione uma situação válida.'; }
        }
        if ($filters['flow'] !== '') {
            if (\App\Domain\Enum\RequestFlow::tryFrom($filters['flow'])) { $conditions['ServiceRequests.flow'] = $filters['flow']; }
            else { $errors[] = 'Selecione um tipo de solicitação válido.'; }
        }
        if ($filters['unit'] !== '') {
            if (\App\Domain\Enum\RequestUnit::tryFrom($filters['unit'])) { $conditions['ServiceRequests.unit'] = $filters['unit']; }
            elseif ($filters['unit'] === 'UNSPECIFIED') { $conditions['ServiceRequests.unit IS'] = null; }
            else { $errors[] = 'Selecione uma unidade válida.'; }
        }
        if ($filters['sector_id'] !== '') {
            if (ctype_digit($filters['sector_id'])) { $conditions['ServiceRequests.sector_id'] = (int)$filters['sector_id']; }
            else { $errors[] = 'Selecione uma mão de obra válido.'; }
        }
        $dates = [];
        foreach (['start', 'end'] as $key) {
            if ($filters[$key] === '') { continue; }
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $filters[$key], new \DateTimeZone('America/Sao_Paulo'));
            if (!$date || $date->format('Y-m-d') !== $filters[$key]) { $errors[] = 'Informe datas válidas para o período.'; continue; }
            $dates[$key] = $date;
            $utc = ($key === 'end' ? $date->modify('+1 day') : $date)->setTimezone(new \DateTimeZone('UTC'));
            $conditions['ServiceRequests.created_at ' . ($key === 'end' ? '<' : '>=')] = $utc->format('Y-m-d H:i:s');
        }
        if (isset($dates['start'], $dates['end']) && $dates['start'] > $dates['end']) { $errors[] = 'A data inicial deve ser anterior ou igual à data final.'; }
        if ($errors) { $conditions['ServiceRequests.id'] = -1; }
        $statuses = $counts = [];
        foreach (RequestStatus::cases() as $status) {
            $statuses[$status->value] = $status->label();
            $countConditions = $conditions;
            unset($countConditions['ServiceRequests.status']);
            $counts[$status->value] = $table->find()->where($countConditions)->where(['ServiceRequests.status' => $status->value])->count();
        }
        $query = $table->find()->contain(['Users', 'Sectors'])->where($conditions);
        $priority = [RequestStatus::AGUARDANDO_ENCERRAMENTO, RequestStatus::AGUARDANDO_PCM, RequestStatus::EM_ANALISE,
            RequestStatus::OS_ABERTA, RequestStatus::EM_EXECUCAO, RequestStatus::ATENDIDA, RequestStatus::ENCERRADA, RequestStatus::CANCELADA];
        $order = 'CASE ServiceRequests.status';
        foreach ($priority as $rank => $state) { $order .= " WHEN '" . $state->value . "' THEN " . $rank; }
        $query->orderBy($order . ' ELSE 8 END ASC')
            ->orderBy(['ServiceRequests.created_at' => 'DESC', 'ServiceRequests.id' => 'DESC']);
        $requests = $this->paginate($query, ['limit' => 20]);
        $sectors = $this->fetchTable('Sectors')->find('list')->orderBy(['name' => 'ASC'])->toArray();
        $this->set(compact('requests', 'counts', 'statuses', 'sectors', 'filters', 'errors'));
    }
}
