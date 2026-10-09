<?php
declare(strict_types=1);

use Cake\Database\Driver\Postgres;
use function Cake\Core\env;

return [
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN),
    'App' => [
        'defaultTimezone' => 'UTC',
        'displayTimezone' => 'America/Sao_Paulo',
        'fullBaseUrl' => env('APP_FULL_BASE_URL', 'http://localhost:8765'),
    ],
    'Security' => ['salt' => env('SECURITY_SALT')],
    'Datasources' => [
        'default' => [
            'driver' => Postgres::class,
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => (int)env('DB_PORT', 5432),
            'username' => env('DB_USER'),
            'password' => env('DB_PASSWORD'),
            'database' => env('DB_NAME', 'solicitacao_os'),
            'encoding' => 'utf8',
            'timezone' => 'UTC',
            'url' => null,
        ],
        'test' => [
            'driver' => Postgres::class,
            'host' => env('TEST_DB_HOST', '127.0.0.1'),
            'port' => (int)env('TEST_DB_PORT', 5432),
            'username' => env('TEST_DB_USER'),
            'password' => env('TEST_DB_PASSWORD'),
            'database' => env('TEST_DB_NAME', 'test_solicitacao_os'),
            'encoding' => 'utf8',
            'timezone' => 'UTC',
            'url' => null,
        ],
    ],
];