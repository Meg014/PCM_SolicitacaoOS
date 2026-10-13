<?php
declare(strict_types=1);

namespace App\Controller;

use App\Domain\Enum\MaintenanceType;
use App\Domain\Enum\RequestStatus;
use App\Domain\Enum\UserRole;
use App\Model\Entity\ServiceRequest;
use App\Service\AssetsUnavailableException;
use App\Service\RequestCreationService;
use Cake\Http\Exception\NotFoundException;
use Cake\ORM\Exception\PersistenceFailedException;

class RequestsController extends AppController
{
    public function add(RequestCreationService $creation): mixed
    {
        $this->request->allowMethod(['get', 'post']);
        $request = $this->fetchTable('ServiceRequests')->newEmptyEntity();
        if ($this->request->is('post')) {
            try {
                $request = $creation->create($this->request->getData(), (int)$this->request->getAttribute('identity')->getIdentifier());
                $this->Flash->set('Solicitação enviada ao PCM.', ['element' => 'request_created',
                    'params' => ['number' => $request->number, 'id' => $request->id]]);

                return $this->redirect(['action' => 'view', $request->id]);
            } catch (PersistenceFailedException $e) {
                if ($e->getEntity() instanceof ServiceRequest) {
                    $request = $e->getEntity();
                }
                $this->Flash->error('Revise os campos indicados. A solicitação ainda não foi enviada.');
            } catch (\App\Service\UploadException $e) {
                $this->Flash->error($e->getMessage() . ' Selecione as fotos novamente antes de enviar.');
            } catch (AssetsUnavailableException $e) {
                $this->Flash->error($e->getMessage() . ' Seus dados continuam no formulário.');
            } catch (\PDOException $e) {
                $this->Flash->error('Não foi possível enviar a solicitação agora. Tente novamente.');
            }
        }
        $types = MaintenanceType::options();
        $sectors = $this->fetchTable('Sectors')->find('list')->where(['active' => true])->orderBy(['name' => 'ASC'])->toArray();
        $this->set(compact('request', 'types', 'sectors'));

        return null;
    }

    public function index(): void
    {
        $query = $this->fetchTable('ServiceRequests')->find()->contain(['Sectors'])
            ->where(['ServiceRequests.user_id' => $this->request->getAttribute('identity')->getIdentifier()])
            ->orderBy(['ServiceRequests.created_at' => 'DESC', 'ServiceRequests.id' => 'DESC']);
        $status = $this->request->getQuery('status');
        if (is_string($status) && RequestStatus::tryFrom($status)) {
            $query->where(['ServiceRequests.status' => $status]);
        }
        $requests = $this->paginate($query, ['limit' => 20]);
        $ownCounts = [];
        foreach (RequestStatus::cases() as $state) {
            $ownCounts[$state->value] = $this->fetchTable('ServiceRequests')->find()->where([
                'user_id' => $this->request->getAttribute('identity')->getIdentifier(), 'status' => $state->value,
            ])->count();
        }
        $this->set(compact('ownCounts'));
        $statuses = [];
        foreach (RequestStatus::cases() as $state) {
            $statuses[$state->value] = $state->label();
        }
        $this->set(compact('requests', 'statuses'));
    }

    public function view(string $id): void
    {
        $identity = $this->request->getAttribute('identity');
        $query = $this->fetchTable('ServiceRequests')->find()->contain(['Users', 'Sectors', 'LinkedUsers', 'CancelledUsers', 'RequestItems', 'RequestAudits.Users', 'RequestAttachments'])
            ->where(['ServiceRequests.id' => $id]);
        $query->where(\App\Service\RequestAccess::conditions($identity->get('role'), (int)$identity->getIdentifier()));
        $request = $query->first();
        if (!$request) {
            throw new NotFoundException('Solicitação não encontrada.');
        }
        $this->set(compact('request'));
    }

    public function link(string $id): mixed
    {
        return $this->handle($id, 'link', $this->request->getData('totvs_os_number'));
    }

    public function cancel(string $id): mixed
    {
        return $this->handle($id, 'cancel', $this->request->getData('reason'));
    }

    public function work(): void
    {
        $identity = $this->request->getAttribute('identity');
        $query = $this->fetchTable('ServiceRequests')->find()->contain(['Users', 'Sectors'])
            ->where(\App\Service\RequestAccess::conditions($identity->get('role'), (int)$identity->getIdentifier()))
            ->where(['ServiceRequests.flow' => \App\Domain\Enum\RequestFlow::ACOMPANHAMENTO->value,
                'ServiceRequests.status IN' => RequestStatus::activeValues()])
            ->orderBy('COALESCE(ServiceRequests.last_activity_at, ServiceRequests.created_at) DESC')->orderBy(['ServiceRequests.id' => 'DESC']);
        $requests = $this->paginate($query, ['limit' => 20]);
        $this->set(compact('requests'));
    }

    public function addItem(string $id): mixed
    {
        $this->request->allowMethod(['get', 'post']);
        $this->view($id);
        $request = $this->viewBuilder()->getVar('request');
        if (!\App\Service\RequestAccess::canAppend($request)) {
            throw new \Cake\Http\Exception\ConflictException('Esta O.S. não está disponível para novos apontamentos.');
        }
        if ($this->request->is('post')) {
            try {
                (new \App\Service\RequestExecutionService())->handle((int)$id,
                    (int)$this->request->getAttribute('identity')->getIdentifier(), 'items', $this->request->getData('items'));
                $this->Flash->success('Apontamento incluído na O.S. existente. O PCM verá a nova atividade na fila.');
                return $this->redirect(['action' => 'view', $id]);
            } catch (\InvalidArgumentException | \Cake\Http\Exception\ConflictException $e) {
                $this->Flash->error($e->getMessage());
            } catch (PersistenceFailedException | \PDOException $e) {
                $this->Flash->error('Não foi possível salvar o apontamento. Tente novamente.');
            }
        }
        return null;
    }

    public function items(string $id): mixed { return $this->execute($id, 'items', $this->request->getData('items')); }
    public function ongoing(string $id): mixed { return $this->execute($id, 'ongoing'); }
    public function complete(string $id): mixed { return $this->execute($id, 'complete'); }

    private function execute(string $id, string $action, mixed $data = null): mixed
    {
        $this->request->allowMethod(['post']);
        try {
            (new \App\Service\RequestExecutionService())->handle((int)$id, (int)$this->request->getAttribute('identity')->getIdentifier(), $action, $data);
            $this->Flash->success(match ($action) {
                'items' => 'Apontamentos registrados.', 'ongoing' => 'Serviço mantido em andamento.',
                'complete' => 'Serviço concluído. O PCM recebeu o pedido de encerramento.',
            });
        } catch (\InvalidArgumentException | \Cake\Http\Exception\ConflictException $e) {
            $this->Flash->error($e->getMessage());
            if ($action === 'items') {
                $this->view($id);
                $this->render('view');
                return null;
            }
        } catch (PersistenceFailedException | \PDOException $e) {
            $this->Flash->error('Não foi possível salvar. Tente novamente.');
        }
        return $this->redirect(['action' => 'view', $id]);
    }

    private function handle(string $id, string $action, mixed $value = null): mixed
    {
        $this->request->allowMethod(['post']);
        $identity = $this->request->getAttribute('identity');
        if ($identity->get('role') !== UserRole::ADMIN_PCM->value) {
            throw new \Cake\Http\Exception\ForbiddenException('Somente o PCM pode tratar solicitações.');
        }
        try {
            (new \App\Service\RequestHandlingService())->handle((int)$id, (int)$identity->getIdentifier(), $action, $value);
            $this->Flash->success(match ($action) {
                'link' => 'O.S. TOTVS vinculada.', 'cancel' => 'Solicitação cancelada.',
            });
        } catch (\InvalidArgumentException | \Cake\Http\Exception\ConflictException $e) {
            $this->Flash->error($e->getMessage());
        } catch (PersistenceFailedException | \PDOException $e) {
            $this->Flash->error('Não foi possível salvar a ação. Atualize a página e tente novamente.');
        }

        return $this->redirect(['action' => 'view', $id]);
    }
}
