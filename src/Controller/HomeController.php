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
        $this->redirect(\App\Domain\Enum\UserRole::tryFrom($role)?->hasDashboard() ? '/painel' : ($role === \App\Domain\Enum\UserRole::MANTENEDOR->value ? '/acompanhamento' : '/minhas-solicitacoes'));
    }

    public function login(): void
    {
        $this->response = $this->response->withHeader('Cache-Control', 'no-store');
        $this->request->allowMethod(['get', 'post']);
        if ($this->Authentication->getResult()->isValid()) {
            if ($this->request->is('post')) {
                $this->Authentication->setIdentity($this->request->getAttribute('identity'));
            }
            $this->redirect($this->request->getAttribute('identity')->get('must_change_password') ? '/alterar-senha' : '/');

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

        return $this->redirect('/entrar');
    }
}
