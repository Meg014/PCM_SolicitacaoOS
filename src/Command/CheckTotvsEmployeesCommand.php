<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\TotvsEmployeeQueries;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Datasource\ConnectionManager;

/** Diagnóstico repetível após a TI liberar a leitura, sem retornar funcionários. */
class CheckTotvsEmployeesCommand extends Command
{
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        try {
            $connection = ConnectionManager::get('protheus');
            $rows = $connection->execute(TotvsEmployeeQueries::COLUMNS)->fetchAll('assoc');
            $expected = ['RA_MAT', 'RA_NOME', 'RA_CODFUNC', 'RA_SITFOLH', 'RA_FILIAL', 'D_E_L_E_T_'];
            if (array_diff($expected, array_column($rows, 'COLUMN_NAME'))) {
                $io->err('Os campos necessários de dbo.SRA010 não estão todos visíveis. Consulte a TI.');
                return self::CODE_ERROR;
            }
            foreach ($rows as $row) {
                $io->out($row['COLUMN_NAME'] . ': ' . $row['DATA_TYPE'] . '(' . $row['CHARACTER_MAXIMUM_LENGTH'] . ')');
            }
            $connection->execute(TotvsEmployeeQueries::ACCESS)->fetchAll('assoc');
            $io->success('Leitura dos campos confirmada. Nenhum funcionário retornado e nenhuma gravação realizada.');
            return self::CODE_SUCCESS;
        } catch (\Throwable $e) {
            $io->err('Não foi possível confirmar SELECT dos campos de dbo.SRA010 com a conexão atual. Consulte a TI; nenhuma alternativa de acesso foi tentada.');
            return self::CODE_ERROR;
        }
    }
}
