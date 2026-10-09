<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use App\Domain\Enum\UserRole;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\TestSuite\Fixture\TestFixture;

class UsersFixture extends TestFixture
{
    public function init(): void
    {
        $password = (new DefaultPasswordHasher())->hash('SenhaTeste123!');
        $roles = [UserRole::ADMIN_PCM, UserRole::GESTOR, UserRole::SOLICITANTE, UserRole::SOLICITANTE];
        foreach ($roles as $index => $role) {
            $this->records[] = [
                'id' => $index + 1, 'name' => 'Usuário ' . ($index + 1),
                'username' => 'user' . ($index + 1), 'email' => 'user' . ($index + 1) . '@example.test', 'password' => $password,
                'role' => $role->value, 'active' => $index !== 3, 'must_change_password' => false,
                'created_at' => '2026-10-07 12:00:00', 'updated_at' => '2026-10-07 12:00:00',
            ];
        }
        parent::init();
    }
}
