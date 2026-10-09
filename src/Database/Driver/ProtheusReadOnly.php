<?php
declare(strict_types=1);

namespace App\Database\Driver;

use App\Service\TotvsAssetQueries;
use Cake\Database\Driver\Sqlserver;
use Cake\Database\Query;
use Cake\Database\StatementInterface;
use LogicException;
use PDO;
use RuntimeException;

/** Mesma defesa da referência PCM_OrdensServico: allowlist e credencial SELECT-only. */
final class ProtheusReadOnly extends Sqlserver
{
    public function connect(): void
    {
        foreach (['host', 'database', 'username', 'password'] as $key) {
            if (trim((string)($this->_config[$key] ?? '')) === '') {
                throw new RuntimeException('Configure a conexão PROTHEUS_DB_* somente leitura.');
            }
        }
        foreach (['init', 'settings', 'attributes'] as $key) {
            if (!empty($this->_config[$key])) {
                throw new LogicException('Inicialização SQL personalizada bloqueada no Protheus.');
            }
        }
        parent::connect();
        if (defined('PDO::SQLSRV_ATTR_QUERY_TIMEOUT')) {
            $this->pdo->setAttribute(PDO::SQLSRV_ATTR_QUERY_TIMEOUT, 5);
        }
    }

    public function prepare(Query|string $query): StatementInterface
    {
        if (!is_string($query) || !TotvsAssetQueries::allows($query)) {
            throw new LogicException('Protheus somente leitura: consulta fora da lista permitida.');
        }

        return parent::prepare($query);
    }

    public function exec(string $sql): int|false
    {
        throw new LogicException('Execução direta bloqueada no Protheus.');
    }
}
