<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\AuthorizedFunctionsImport;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

class ImportAuthorizedFunctionsCommand extends Command
{
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription('Importa funções autorizadas no PostgreSQL local, com auditoria.')
            ->addArgument('file', ['required' => true, 'help' => 'Caminho da planilha XLSX (Funcao, Descricao).'])
            ->addOption('user-id', ['help' => 'ID do ADMIN_PCM ativo responsável.'])
            ->addOption('replace', ['boolean' => true, 'default' => false, 'help' => 'Inativa funções ausentes na planilha; não exclui registros.'])
            ->addOption('dry-run', ['boolean' => true, 'default' => false, 'help' => 'Valida a planilha sem gravar nem acessar bancos.']);
    }

    public function execute(Arguments $args, ConsoleIo $io): int
    {
        try {
            $service = new AuthorizedFunctionsImport();
            $path = $args->getArgument('file');
            if ($args->getOption('dry-run')) {
                $io->out('Funções válidas: ' . count($service->read($path)) . '. Nenhuma gravação realizada.');
                return self::CODE_SUCCESS;
            }
            $actor = filter_var($args->getOption('user-id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$actor) { throw new \InvalidArgumentException('Informe --user-id com o ID do ADMIN_PCM responsável.'); }
            $counts = $service->import($path, $actor, (bool)$args->getOption('replace'));
            $io->out(sprintf('Criadas: %d. Atualizadas: %d. Inativadas: %d. Sem alteração: %d.', ...array_values($counts)));
            return self::CODE_SUCCESS;
        } catch (\InvalidArgumentException $e) {
            $io->err($e->getMessage());
        } catch (\Throwable $e) {
            $io->err('Importação não concluída. Nenhuma alteração do lote foi mantida. Confira o PostgreSQL e as migrations.');
        }
        return self::CODE_ERROR;
    }
}
