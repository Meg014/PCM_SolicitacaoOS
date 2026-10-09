<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\RegistrationService;
use Cake\ORM\Exception\PersistenceFailedException;

class SectorsController extends AdminController
{
    public function index(): void
    {
        $sectors = $this->paginate($this->fetchTable('Sectors')->find()->orderBy(['name' => 'ASC']));
        $this->set(compact('sectors'));
    }

    public function add(): mixed
    {
        return $this->form();
    }

    public function edit(string $id): mixed
    {
        return $this->form($id);
    }

    private function form(?string $id = null): mixed
    {
        $this->request->allowMethod(['get', 'post', 'put', 'patch']);
        $table = $this->fetchTable('Sectors');
        $sector = $id === null ? $table->newEmptyEntity() : $table->get($id);
        $before = $id === null ? null : $sector->toArray();
        if ($this->request->is(['post', 'put', 'patch'])) {
            $sector = $table->patchEntity($sector, $this->request->getData(), ['fields' => ['code', 'name', 'active']]);
            try {
                (new RegistrationService())->save($table, $sector, $before, (int)$this->request->getAttribute('identity')->getIdentifier());
                $this->Flash->success($id === null ? 'Mão de obra cadastrada.' : 'Mão de obra atualizada.');

                return $this->redirect(['action' => 'index']);
            } catch (PersistenceFailedException $e) {
                $this->Flash->error('Revise os campos indicados para salvar a mão de obra.');
            } catch (\PDOException $e) {
                $this->Flash->error('Não foi possível salvar agora. Confira se a mão de obra já está cadastrada e tente novamente.');
            }
        }
        $this->set(compact('sector'));
        $this->render('form');

        return null;
    }
}
