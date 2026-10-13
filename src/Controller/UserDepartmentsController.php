<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\RegistrationService;
use Cake\ORM\Exception\PersistenceFailedException;

class UserDepartmentsController extends AdminController
{
    public function index(): void
    {
        $departments = $this->paginate($this->fetchTable('UserDepartments')->find()->orderBy(['name' => 'ASC']));
        $this->set(compact('departments'));
    }

    public function add(): mixed { return $this->form(); }
    public function edit(string $id): mixed { return $this->form($id); }

    private function form(?string $id = null): mixed
    {
        $this->request->allowMethod(['get', 'post', 'put', 'patch']);
        $table = $this->fetchTable('UserDepartments');
        $department = $id === null ? $table->newEmptyEntity() : $table->get($id);
        $before = $id === null ? null : $department->toArray();
        if ($this->request->is(['post', 'put', 'patch'])) {
            $table->patchEntity($department, $this->request->getData(), ['fields' => ['name', 'active']]);
            try {
                (new RegistrationService())->save($table, $department, $before, (int)$this->request->getAttribute('identity')->getIdentifier());
                $this->Flash->success('Setor salvo.');
                return $this->redirect(['action' => 'index']);
            } catch (PersistenceFailedException | \PDOException $e) {
                $this->Flash->error('Revise o nome do setor. Ele deve estar preenchido e não pode se repetir.');
            }
        }
        $this->set(compact('department'));
        $this->render('form');
        return null;
    }
}
