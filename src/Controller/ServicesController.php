<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\RegistrationService;
use Cake\ORM\Exception\PersistenceFailedException;

class ServicesController extends AdminController
{
    protected string $catalogTable = 'Services';
    protected string $catalogTitle = 'Serviços padronizados';
    protected string $catalogLabel = 'Serviço';
    protected string $catalogPath = '/servicos';

    public function index(): void
    {
        $services = $this->paginate($this->fetchTable($this->catalogTable)->find()->orderBy(['name' => 'ASC']));
        $this->catalogView();
        $this->set(compact('services'));
    }

    public function add(): mixed
    {
        return $this->form();
    }

    public function edit(string $id): mixed
    {
        return $this->form($id);
    }

    public function toggle(string $id): mixed
    {
        $this->request->allowMethod(['post']);
        $table = $this->fetchTable($this->catalogTable);
        $service = $table->get($id);
        $before = $service->toArray();
        $service->set('active', !$service->active);

        try {
            (new RegistrationService())->save(
                $table,
                $service,
                $before,
                (int)$this->request->getAttribute('identity')->getIdentifier(),
                $service->active ? 'ACTIVATED' : 'DEACTIVATED',
            );
            $this->Flash->success($service->active ? $this->catalogLabel . ' ativado.' : $this->catalogLabel . ' desativado.');
        } catch (PersistenceFailedException $e) {
            $this->Flash->error('Não foi possível atualizar a situação do serviço.');
        } catch (\PDOException $e) {
            $this->Flash->error('Não foi possível atualizar a situação do serviço.');
        }

        return $this->redirect(['action' => 'index']);
    }

    private function form(?string $id = null): mixed
    {
        $this->request->allowMethod(['get', 'post', 'put', 'patch']);
        $table = $this->fetchTable($this->catalogTable);
        $service = $id === null ? $table->newEmptyEntity() : $table->get($id);
        $before = $id === null ? null : $service->toArray();
        if ($this->request->is(['post', 'put', 'patch'])) {
            $service = $table->patchEntity($service, $this->request->getData(), ['fields' => ['name', 'active']]);
            try {
                (new RegistrationService())->save(
                    $table,
                    $service,
                    $before,
                    (int)$this->request->getAttribute('identity')->getIdentifier(),
                );
                $this->Flash->success($id === null ? $this->catalogLabel . ' cadastrado.' : $this->catalogLabel . ' atualizado.');

                return $this->redirect(['action' => 'index']);
            } catch (PersistenceFailedException $e) {
                $this->Flash->error('Revise o nome do serviço antes de salvar.');
            } catch (\PDOException $e) {
                $this->Flash->error('Não foi possível salvar. Confira se o serviço já está cadastrado e tente novamente.');
            }
        }
        $this->catalogView();
        $this->set(compact('service'));
        $this->render('form');

        return null;
    }
    private function catalogView(): void
    {
        $this->viewBuilder()->setTemplatePath('Services');
        $this->set(['catalogTitle' => $this->catalogTitle, 'catalogLabel' => $this->catalogLabel, 'catalogPath' => $this->catalogPath]);
    }
}