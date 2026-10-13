<?php
declare(strict_types=1);

namespace App\Middleware;

use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class IdleSessionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $session = $request->getSession();
        // O relógio nativo considera qualquer HTTP atividade. Este middleware controla o relógio humano.
        if (!$session->started()) { $session->setSessionLifetime(0); }
        $now = time();
        $poll = $request->getParam('controller') === 'Dashboard' && $request->getParam('action') === 'refresh';
        $last = $session->read('AuthActivity.last_at');
        if ($session->read('Auth') && is_numeric($last) && $now - (int)$last >= (int)Configure::read('Session.timeout', 20) * 60) {
            $session->delete('Auth'); $session->delete('AuthActivity'); $session->renew();
            $session->write('AuthActivity.expired', true);
            return (new Response())->withStatus(303)->withHeader('Cache-Control', 'no-store')
                ->withHeader('Location', Router::url(['controller' => 'Home', 'action' => 'login']));
        }
        // Compatibilidade na primeira requisição de uma sessão anterior à atualização.
        if ($session->read('Auth') && $last === null) { $session->write('AuthActivity.last_at', $now); }
        $response = $handler->handle($request);
        if (!$poll && $session->read('Auth')) {
            $session->write('AuthActivity.last_at', $now); $session->delete('AuthActivity.expired');
        }
        return $response;
    }
}
