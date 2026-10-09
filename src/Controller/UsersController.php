<?php
declare(strict_types=1);

namespace App\Controller;

use App\Domain\Enum\UserRole;
use App\Service\RegistrationService;
use Cake\ORM\Exception\PersistenceFailedException;

class UsersController extends AdminController
{
    public function index(): void
    {
        $users = $this->paginate($this->fetchTable('Users')->find()->select(['id', 'name', 'username', 'email', 'role', 'active'])->orderBy(['name' => 'ASC']));
        $this->set(compact('users'));
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
        $table = $this->fetchTable('Users');
        $user = $id === null ? $table->newEmptyEntity() : $table->get($id);
        $before = $id === null ? null : $user->toArray();
        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = $this->request->getData();
            if ($id !== null && ($data['password'] ?? '') === '') {
                unset($data['password']);
            }
            $user = $table->patchEntity($user, $data, ['fields' => ['name', 'username', 'email', 'password', 'role', 'active']]);
            if ($user->isDirty('password')) { $user->set('must_change_password', true); }
            $actorId = (int)$this->request->getAttribute('identity')->getIdentifier();
            if ($id !== null && (int)$user->id === $actorId && (!$user->active || $user->role !== UserRole::ADMIN_PCM->value)) {
                $user->setError('active', 'Mantenha sua própria conta ativa com perfil PCM.');
            }
            try {
                (new RegistrationService())->save($table, $user, $before, $actorId);
                $this->Flash->success($id === null ? 'Usuário cadastrado.' : 'Usuário atualizado.');

                return $this->redirect(['action' => 'index']);
            } catch (PersistenceFailedException $e) {
                $this->Flash->error('Revise os campos indicados para salvar o usuário.');
            } catch (\PDOException $e) {
                $this->Flash->error('Não foi possível salvar agora. Confira se o usuário ou e-mail já está cadastrado e tente novamente.');
            }
        }
        $roles = UserRole::options();
        $this->set(compact('user', 'roles'));
        $this->render('form');

        return null;
    }
}
