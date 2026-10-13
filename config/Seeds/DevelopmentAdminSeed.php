<?php
declare(strict_types=1);

use App\Domain\Enum\UserRole;
use App\Service\RegistrationService;
use Cake\ORM\TableRegistry;
use Migrations\BaseSeed;
use function Cake\Core\env;

class DevelopmentAdminSeed extends BaseSeed
{
    public function run(): void
    {
        if (env('APP_ENV') !== 'development') {
            throw new RuntimeException('Este seed é exclusivo do ambiente de desenvolvimento.');
        }
        $email = strtolower(trim((string)env('ADMIN_SEED_EMAIL')));
        $username = strtolower(trim((string)env('ADMIN_SEED_USERNAME', 'admin')));
        $password = (string)env('ADMIN_SEED_PASSWORD');
        $users = TableRegistry::getTableLocator()->get('Users');
        if ($users->exists(['active' => true, 'role' => UserRole::ADMIN_PCM->value]) || $users->exists(['username' => $username]) || ($email !== '' && $users->exists(['email' => $email]))) {
            return;
        }
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{0,59}$/', $username) || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) || mb_strlen($password) < 6) {
            throw new RuntimeException('Defina ADMIN_SEED_USERNAME e ADMIN_SEED_PASSWORD (pelo menos 6 caracteres); ADMIN_SEED_EMAIL é opcional.');
        }
        $departmentName = trim((string)env('ADMIN_SEED_DEPARTMENT', ''));
        if ($departmentName === '') { throw new RuntimeException('Defina ADMIN_SEED_DEPARTMENT com o nome do setor do administrador.'); }
        $departments = TableRegistry::getTableLocator()->get('UserDepartments');
        $department = $departments->find()->where(['name' => $departmentName])->first();
        if (!$department) {
            $department = $departments->newEntity(['name' => $departmentName, 'active' => true]);
            (new RegistrationService())->save($departments, $department, null, null);
        }
        $user = $users->newEntity([
            'department_id' => $department->id,
            'name' => 'Administrador PCM', 'username' => $username, 'email' => $email, 'password' => $password,
            'role' => UserRole::ADMIN_PCM->value, 'active' => true,
        ]);
        (new RegistrationService())->save($users, $user, null, null);
    }
}
