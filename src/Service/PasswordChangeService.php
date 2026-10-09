<?php
declare(strict_types=1);

namespace App\Service;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\ORM\TableRegistry;

class PasswordChangeService
{
    public function change(int $userId, mixed $current, mixed $new, mixed $confirmation): void
    {
        if (!is_string($current) || !is_string($new) || !is_string($confirmation) || mb_strlen($new) < 6) {
            throw new \InvalidArgumentException('Informe sua senha atual e uma nova senha com pelo menos 6 caracteres.');
        }
        if ($new !== $confirmation) { throw new \InvalidArgumentException('A confirmação deve ser igual à nova senha.'); }
        $users = TableRegistry::getTableLocator()->get('Users');
        $users->getConnection()->transactional(function () use ($users, $userId, $current, $new): void {
            $users->getConnection()->execute('SELECT id FROM users WHERE id = :id FOR UPDATE', ['id' => $userId]);
            $user = $users->get($userId);
            if (!$user->active) { throw new \Cake\Http\Exception\ForbiddenException('Sua conta está desativada.'); }
            $hasher = new DefaultPasswordHasher();
            if (!$hasher->check($current, $user->password)) { throw new \InvalidArgumentException('A senha atual não confere. Tente novamente.'); }
            if ($hasher->check($new, $user->password)) { throw new \InvalidArgumentException('Escolha uma senha diferente da senha atual.'); }
            $before = $user->toArray();
            $user->set('password', $new);
            $user->set('must_change_password', false);
            (new RegistrationService())->save($users, $user, $before, $userId, 'PASSWORD_CHANGED');
        });
    }
}
