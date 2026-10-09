<?php
declare(strict_types=1);

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

return function (RouteBuilder $routes): void {
    $routes->setRouteClass(DashedRoute::class);
    $routes->scope('/', function (RouteBuilder $builder): void {
        $builder->connect('/', ['controller' => 'Home', 'action' => 'index']);
        $builder->connect('/entrar', ['controller' => 'Home', 'action' => 'login'])->setMethods(['GET', 'POST']);
        $builder->connect('/sair', ['controller' => 'Home', 'action' => 'logout'])->setMethods(['POST']);
        $builder->connect('/painel', ['controller' => 'Dashboard', 'action' => 'index'])->setMethods(['GET']);
        $builder->connect('/meu-perfil', ['controller' => 'Profile', 'action' => 'index'])->setMethods(['GET']);
        $builder->connect('/alterar-senha', ['controller' => 'Profile', 'action' => 'password'])->setMethods(['GET', 'POST']);
        $builder->connect('/fotos/{id}', ['controller' => 'Attachments', 'action' => 'view'])->setPatterns(['id' => '[0-9]+'])->setPass(['id'])->setMethods(['GET']);
        $builder->connect('/indicadores', ['controller' => 'Reports', 'action' => 'indicators'])->setMethods(['GET']);
        $builder->connect('/relatorios', ['controller' => 'Reports', 'action' => 'index'])->setMethods(['GET']);
        foreach (['assumir' => 'assign', 'vincular-os' => 'link', 'cancelar' => 'cancel', 'confirmar-encerramento' => 'close', 'apontamentos' => 'items', 'em-andamento' => 'ongoing', 'concluir-servico' => 'complete'] as $path => $action) {
            $builder->connect('/solicitacoes/{id}/' . $path, ['controller' => 'Requests', 'action' => $action])
                ->setPatterns(['id' => '[0-9]+'])->setPass(['id'])->setMethods(['POST']);
        }
        $builder->connect('/api/bens', ['controller' => 'Lookups', 'action' => 'assets'])->setMethods(['GET']);
        $builder->connect('/api/solicitacoes-ativas', ['controller' => 'Lookups', 'action' => 'active'])->setMethods(['GET']);
        $builder->connect('/acompanhamento', ['controller' => 'Requests', 'action' => 'work'])->setMethods(['GET']);
        $builder->connect('/api/setores', ['controller' => 'Lookups', 'action' => 'sectors'])->setMethods(['GET']);
        $builder->connect('/minhas-solicitacoes', ['controller' => 'Requests', 'action' => 'index'])->setMethods(['GET']);
        $builder->connect('/solicitacoes/nova', ['controller' => 'Requests', 'action' => 'add'])->setMethods(['GET', 'POST']);
        $builder->connect('/solicitacoes/{id}', ['controller' => 'Requests', 'action' => 'view'])
            ->setPatterns(['id' => '[0-9]+'])->setPass(['id'])->setMethods(['GET']);
        foreach (['usuarios' => 'Users', 'setores' => 'Sectors'] as $path => $controller) {
            $builder->connect('/' . $path, ['controller' => $controller, 'action' => 'index'])->setMethods(['GET']);
            $builder->connect('/' . $path . '/novo', ['controller' => $controller, 'action' => 'add'])->setMethods(['GET', 'POST']);
            $builder->connect('/' . $path . '/{id}/editar', ['controller' => $controller, 'action' => 'edit'])
                ->setPatterns(['id' => '[0-9]+'])->setPass(['id'])->setMethods(['GET', 'POST', 'PUT', 'PATCH']);
        }
    });
};
