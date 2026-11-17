<?php
declare(strict_types=1);
namespace App\Command;

use App\Domain\Enum\UserRole;
use App\Service\{SsoAudit, SsoSettings};
use Cake\Command\Command;
use Cake\Console\{Arguments, ConsoleIo, ConsoleOptionParser};

// Operação administrativa no servidor: nunca procura uma conta por e-mail/nome.
class LinkSsoIdentityCommand extends Command
{
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription('Vincula IDs previamente conferidos no Keycloak e no PCM; exige acesso administrativo ao servidor.')
            ->addArgument('user-id', ['required' => true])->addArgument('subject', ['required' => true])
            ->addOption('actor-id', ['help' => 'ID do ADMIN_PCM responsável.', 'required' => true])
            ->addOption('reason', ['help' => 'Justificativa/referência da conferência.', 'required' => true]);
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        try {
            // Permite preparar vínculos antes de habilitar o SSO.
            $issuer = (string)\Cake\Core\Configure::read('Sso.issuer', '');
            SsoSettings::validateUrl($issuer, (bool)\Cake\Core\Configure::read('Sso.development'));
            $subject = (string)$args->getArgument('subject');
            $userId = filter_var($args->getArgument('user-id'), FILTER_VALIDATE_INT);
            $actorId = filter_var($args->getOption('actor-id'), FILTER_VALIDATE_INT);
            $reason = trim((string)$args->getOption('reason'));
            if (!$userId || !$actorId || $subject === '' || strlen($subject) > 255 || $reason === '' || strlen($reason) > 500) {
                throw new \RuntimeException();
            }
            $users = $this->fetchTable('Users');
            if (!$users->exists(['id' => $userId, 'active' => true]) ||
                !$users->exists(['id' => $actorId, 'active' => true, 'role' => UserRole::ADMIN_PCM->value])) {
                throw new \RuntimeException();
            }
            $table = $this->fetchTable('SsoIdentities');
            $table->getConnection()->transactional(function () use ($table, $userId, $actorId, $issuer, $subject, $reason): void {
                $identity = $table->newEmptyEntity();
                $identity->set('user_id', $userId);
                $identity->set('issuer', $issuer);
                $identity->set('subject', $subject);
                $table->saveOrFail($identity);
                SsoAudit::record('SSO_IDENTITY_LINKED', $actorId, ['target_user_id' => $userId, 'issuer' => $issuer,
                    'subject' => $subject, 'reason' => $reason]);
            });
            $io->out('Identidade vinculada. Perfil e senha local preservados.');
            return self::CODE_SUCCESS;
        } catch (\Throwable $e) {
            $io->err('Vínculo não realizado. Confira IDs, issuer, responsável, justificativa e migrations. Vínculos existentes não são substituídos.');
            return self::CODE_ERROR;
        }
    }
}
