<?php
declare(strict_types=1);

namespace App\Command;

use App\Domain\Enum\UserRole;
use App\Service\RegistrationService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use function Cake\Core\env;

class CreateAdminCommand extends Command
{
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription('Cria somente o primeiro administrador, com troca obrigatória de senha.')
            ->addOption('confirm-non-production', ['boolean' => true, 'default' => false,
                'help' => 'Confirma explicitamente a execução fora de APP_ENV=production.']);
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        if (env('APP_ENV') !== 'production' && !$args->getOption('confirm-non-production')) {
            $io->err('Use APP_ENV=production ou confirme explicitamente com --confirm-non-production.');
            return self::CODE_ERROR;
        }
        try {
            $users = $this->fetchTable('Users');
            $created = $users->getConnection()->transactional(function () use ($users): bool {
                // Serializa a verificação e a criação, inclusive com duas execuções simultâneas.
                $users->getConnection()->execute('LOCK TABLE users IN SHARE ROW EXCLUSIVE MODE');
                if ($users->exists(['role' => UserRole::ADMIN_PCM->value])) { return false; }
                $data = [];
                foreach (['name' => 'BOOTSTRAP_ADMIN_NAME', 'username' => 'BOOTSTRAP_ADMIN_USERNAME',
                    'email' => 'BOOTSTRAP_ADMIN_EMAIL', 'password' => 'BOOTSTRAP_ADMIN_PASSWORD'] as $field => $variable) {
                    $value = env($variable, '');
                    if (!is_string($value)) { throw new \InvalidArgumentException(); }
                    $data[$field] = $field === 'password' ? $value : trim($value);
                }
                $user = $users->newEntity($data + ['role' => UserRole::ADMIN_PCM->value, 'active' => true]);
                if ($user->hasErrors()) { throw new \InvalidArgumentException(); }
                $user->set('must_change_password', true);
                (new RegistrationService())->save($users, $user, null, null, 'PRODUCTION_ADMIN_BOOTSTRAPPED');
                return true;
            });
            $io->out($created ? 'Primeiro administrador criado. Troca de senha obrigatória no primeiro acesso.'
                : 'Já existe um ADMIN_PCM, inclusive se inativo. Nenhum usuário foi criado ou alterado.');
            return self::CODE_SUCCESS;
        } catch (\InvalidArgumentException | \Cake\ORM\Exception\PersistenceFailedException $e) {
            $io->err('Confira BOOTSTRAP_ADMIN_NAME e BOOTSTRAP_ADMIN_PASSWORD (mínimo de 6 caracteres). Informe BOOTSTRAP_ADMIN_USERNAME ou BOOTSTRAP_ADMIN_EMAIL, ou ambos. Identificadores preenchidos devem ser válidos e estar disponíveis.');
        } catch (\Throwable $e) {
            // Não imprimir/encadear erros de persistência: podem conter dados sensíveis.
            $io->err('Não foi possível criar o administrador. Verifique a conexão PostgreSQL e as migrations.');
        }
        return self::CODE_ERROR;
    }
}
