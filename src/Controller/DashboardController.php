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
            if ($this->request->getParam('action') === 'refresh') {
                $this->setResponse($this->response->withStatus(403)->withType('application/json')
                    ->withHeader('Cache-Control', 'no-store')->withStringBody('{"error":"Você não tem acesso à fila do PCM."}'));
                $event->setResult($this->response);
                $event->stopPropagation();
                return;
            }
            throw new ForbiddenException('O painel é exclusivo do PCM e dos gestores.');
        }
    }

    public function index(): void
    {
        $this->request->allowMethod(['get']);
        $this->loadQueue();
        $this->response = $this->response->withHeader('Cache-Control', 'no-store');
    }

    public function refresh(): void
    {
        $this->request->allowMethod(['get']);
        $this->loadQueue();
        $this->viewBuilder()->disableAutoLayout();
        $this->response = $this->response->withHeader('Cache-Control', 'no-store');
        $this->render('/element/pcm_queue');
    }

    private function loadQueue(): void
    {
        $table = $this->fetchTable('ServiceRequests');
        $states = [RequestStatus::AGUARDANDO_ENCERRAMENTO, RequestStatus::AGUARDANDO_PCM,
            RequestStatus::EM_EXECUCAO, RequestStatus::OS_ABERTA];
        $statuses = $counts = [];
        $countQuery = $table->find();
        $grouped = $countQuery->select(['status', 'total' => $countQuery->func()->count('*')])
            ->where(['status IN' => array_column($states, 'value')])->groupBy(['status'])->disableHydration()->toArray();
        $totals = array_column($grouped, 'total', 'status');
        foreach ($states as $state) { $statuses[$state->value] = $state->label(); $counts[$state->value] = (int)($totals[$state->value] ?? 0); }
        $query = $table->find()->contain(['Users', 'Sectors'])->where(['ServiceRequests.status IN' => array_keys($statuses)])
            // Registros antigos sem atividade registrada usam a criação como atividade inicial.
            ->orderBy('COALESCE(ServiceRequests.last_activity_at, ServiceRequests.created_at) DESC')
            ->orderBy(['ServiceRequests.id' => 'DESC']);
        try { $requests = $this->paginate($query, ['limit' => 20, 'maxLimit' => 20, 'allowedParameters' => ['page']]); }
        catch (\Cake\Http\Exception\NotFoundException $e) {
            // Uma página pode desaparecer enquanto trabalhos são encerrados.
            $this->setRequest($this->request->withQueryParams(['page' => '1']));
            $requests = $this->paginate($query, ['limit' => 20, 'maxLimit' => 20, 'allowedParameters' => ['page']]);
            $this->response = $this->response->withHeader('X-Queue-Page', '1');
        }
        $this->set(compact('requests', 'counts', 'statuses'));
    }
}
