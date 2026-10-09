<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/bootstrap.php';

\Cake\Core\Configure::write('App.fullBaseUrl', 'http://localhost');
session_id('cli');

\Cake\TestSuite\ConnectionHelper::addTestAliases();
$testConfig = \Cake\Datasource\ConnectionManager::get('test')->config();
if (!empty($testConfig['username']) && !empty($testConfig['password'])) {
    $defaultConfig = \Cake\Datasource\ConnectionManager::getConfig('default');
    if ($testConfig['database'] === $defaultConfig['database']) {
        throw new RuntimeException('Os testes exigem um banco PostgreSQL separado do banco principal.');
    }
    (new \Migrations\TestSuite\Migrator())->run();
}
