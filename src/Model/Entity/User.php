<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\ORM\Entity;

class User extends Entity
{
    protected array $_accessible = [
        'name' => true, 'username' => true, 'email' => true, 'password' => true,
        'role' => true, 'active' => true,
    ];

    protected array $_hidden = ['password'];

    protected function _setPassword(string $password): string
    {
        return (new DefaultPasswordHasher(['hashType' => PASSWORD_ARGON2ID]))->hash($password);
    }

    protected function _setUsername(string $username): string
    {
        return strtolower(trim($username));
    }

    protected function _setEmail(?string $email): ?string
    {
        $email = strtolower(trim($email ?? ''));

        return $email === '' ? null : $email;
    }
}
