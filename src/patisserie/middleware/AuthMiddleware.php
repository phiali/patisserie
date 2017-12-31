<?php

namespace Patisserie\Middleware;

use Patisserie\Auth;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Router;

class AuthMiddleware
{
    protected $container;

    /** @var  Auth */
    protected $auth;

    /** @var  Router */
    protected $router;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->auth = $container->get('auth');
        $this->router = $container->get('router');
    }

    public function __invoke(RequestInterface $request, ResponseInterface $response, callable $next)
    {

        if (!$this->auth->check()) {
            $this->container['flash']->addMessage('error', 'Please login before trying to do that');
            return $response->withRedirect($this->router->pathFor('login'));
        }

        /* Everything is ok, call the next middleware. */
        return $next($request, $response);
    }
}