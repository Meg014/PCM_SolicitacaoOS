<?php
declare(strict_types=1);

namespace App\Controller;

class HomeController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->Authentication->allowUnauthenticated(['login']);
    }

    public function index(): void
    {
        $role = $this->request->getAttribute('identity')->get('role');
        $this->redirect(\App\Domain\Enum\UserRole::tryFrom($role)?->hasDashboard()
            ? ['controller' => 'Dashboard', 'action' => 'index']
            : ['controller' => 'Requests', 'action' => $role === \App\Domain\Enum\UserRole::MANTENEDOR->value ? 'work' : 'index']);
    }

    public function login(): void
    {
        $this->response = $this->response->withHeader('Cache-Control', 'no-store');
        $this->request->allowMethod(['get', 'post']);
        if ($this->Authentication->getResult()->isValid()) {
            if ($this->request->is('post')) {
                $this->Authentication->setIdentity($this->request->getAttribute('identity'));
            }
            $this->redirect($this->request->getAttribute('identity')->get('must_change_password')
                ? ['controller' => 'Profile', 'action' => 'password'] : ['controller' => 'Home', 'action' => 'index']);

            return;
        }
        if ($this->request->is('post')) {
            $this->Flash->error('Usuário, e-mail ou senha incorretos, ou conta desativada. Confira os dados e tente novamente.');
        }
    }

    public function logout(): mixed
    {
        $this->request->allowMethod(['post']);
        $this->Authentication->logout();

        return $this->redirect(['controller' => 'Home', 'action' => 'login']);
    }
}
