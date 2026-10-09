<?php
declare(strict_types=1);

namespace App\Middleware;

use Cake\Http\Exception\InvalidCsrfTokenException;
use Cake\Http\Response;
use Cake\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** Rejeita o envio inválido e orienta a obter um formulário novo, sem reaproveitar a senha. */
final class LoginCsrfRecoveryMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (InvalidCsrfTokenException $e) {
            $params = $request->getAttribute('params', []);
            if ($request->getMethod() !== 'POST' || ($params['controller'] ?? '') !== 'Home' || ($params['action'] ?? '') !== 'login') {
                throw $e;
            }
            $request->getFlash()->error('A página de acesso está desatualizada. Digite usuário e senha novamente para entrar.');
            return (new Response())->withStatus(303)
                ->withHeader('Location', Router::url(['controller' => 'Home', 'action' => 'login']))
                ->withHeader('Cache-Control', 'no-store');
        }
    }
}
