<?php
declare(strict_types=1);
namespace App\Command;

use App\Service\TotvsOrderSyncService;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;

class SyncTotvsOrdersCommand extends Command
{
    public function __construct(private TotvsOrderSyncService $sync) {}
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser->setDescription('Consulta O.S. vinculadas no TOTVS somente por SELECT e sincroniza o acompanhamento local.');
    }
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $result = $this->sync->sync();
        $io->out(sprintf('Verificadas: %d. Alteradas: %d. Avisos: %d. Erros: %d.', ...array_values($result)));
        return $result['errors'] ? self::CODE_ERROR : self::CODE_SUCCESS;
    }
}
