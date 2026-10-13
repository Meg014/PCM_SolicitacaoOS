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

    public function order(string $id, \App\Service\OrdersLookupInterface $orders): void
    {
        $this->request->allowMethod(['get']);
        $request = $this->fetchTable('ServiceRequests')->get($id);
        if (!$request->totvs_os_number) { throw new \Cake\Http\Exception\NotFoundException('Esta solicitação ainda não tem O.S. vinculada.'); }
        $order = null; $error = null;
        try {
            $order = $orders->find($request->totvs_os_number, (string)($request->totvs_os_branch ?? $request->asset_branch), $request->asset_code);
            if (!$order) { $error = 'A O.S. não foi localizada de forma única para este Bem e filial. Confira o vínculo com o PCM.'; }
        } catch (\App\Service\OrdersUnavailableException $e) { $error = $e->getMessage(); }
        $this->response = $this->response->withHeader('Cache-Control', 'no-store');
        $this->set(compact('request', 'order', 'error'));
    }

    private function options(): void
    {
        $statuses = [];
        foreach (RequestStatus::cases() as $status) { $statuses[$status->value] = $status->label(); }
        $sectors = $this->fetchTable('Sectors')->find('list')->orderBy(['name' => 'ASC'])->toArray();
        $users = $this->fetchTable('Users')->find('list')->orderBy(['name' => 'ASC'])->toArray();
        $departments = $this->fetchTable('UserDepartments')->find('list')->orderBy(['name' => 'ASC'])->toArray();
        $this->set(compact('statuses', 'sectors', 'users', 'departments'));
    }
}
