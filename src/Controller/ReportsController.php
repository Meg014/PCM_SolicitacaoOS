<?php
declare(strict_types=1);

namespace App\Controller;

use App\Domain\Enum\RequestStatus;
use App\Service\RequestReportService;

class ReportsController extends DashboardController
{
    public function beforeFilter(\Cake\Event\EventInterface $event): void
    {
        parent::beforeFilter($event);
        if ($event->isStopped()) { return; }
        $identity = $this->request->getAttribute('identity');
        if ($identity && !\App\Domain\Enum\UserRole::tryFrom($identity->get('role'))?->hasReports()) {
            throw new \Cake\Http\Exception\ForbiddenException('Relatórios e indicadores são exclusivos do PCM e dos gestores.');
        }
    }
    public function index(): void
    {
        $filters = $this->request->getQueryParams();
        $error = null;
        try { $query = (new RequestReportService())->query($filters); }
        catch (\InvalidArgumentException $e) {
            $error = $e->getMessage();
            $query = $this->fetchTable('ServiceRequests')->find()->where(['ServiceRequests.id' => -1]);
        }
        $requests = $this->paginate($query->contain(['Users', 'Sectors'])->orderBy(['ServiceRequests.created_at' => 'DESC', 'ServiceRequests.id' => 'DESC']), ['limit' => 25]);
        $this->options();
        $this->set(compact('requests', 'filters', 'error'));
    }

    public function indicators(): void
    {
        $filters = array_intersect_key($this->request->getQueryParams(), array_flip(['start', 'end']));
        $error = null;
        try { $indicators = (new RequestReportService())->indicators($filters); }
        catch (\InvalidArgumentException $e) { $error = $e->getMessage(); $indicators = null; }
        $this->options();
        $this->set(compact('indicators', 'filters', 'error'));
    }

    private function options(): void
    {
        $statuses = [];
        foreach (RequestStatus::cases() as $status) { $statuses[$status->value] = $status->label(); }
        $sectors = $this->fetchTable('Sectors')->find('list')->orderBy(['name' => 'ASC'])->toArray();
        $users = $this->fetchTable('Users')->find('list')->orderBy(['name' => 'ASC'])->toArray();
        $this->set(compact('statuses', 'sectors', 'users'));
    }
}
