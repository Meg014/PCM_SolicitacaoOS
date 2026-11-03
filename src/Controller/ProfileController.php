<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\PasswordChangeService;

class ProfileController extends AppController
{
    public function index(): void
    {
        $user = $this->fetchTable('Users')->get($this->request->getAttribute('identity')->getIdentifier());
        $this->set(compact('user'));
    }

    public function password(): mixed
    {
        $this->request->allowMethod(['get', 'post']);
        if ($this->request->is('post')) {
            try {
                $id = (int)$this->request->getAttribute('identity')->getIdentifier();
                (new PasswordChangeService())->change($id, $this->request->getData('current_password'), $this->request->getData('new_password'), $this->request->getData('confirmation'));
                $this->Authentication->setIdentity($this->fetchTable('Users')->get($id));
                $this->Flash->success('Sua senha foi alterada.');

                return $this->redirect(['controller' => 'Home', 'action' => 'index']);
            } catch (\InvalidArgumentException $e) {
                $this->Flash->error($e->getMessage());
            } catch (\PDOException | \Cake\ORM\Exception\PersistenceFailedException $e) {
                $this->Flash->error('Não foi possível alterar sua senha agora. Tente novamente.');
            }
        }

        return null;
    }
}
