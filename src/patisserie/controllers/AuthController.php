<?php

namespace Patisserie\Controllers;

use Patisserie\Patisserie;
use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;

class AuthController
{
    protected $container;
    /** @var  Twig */
    protected $view;

    /** @var  \Patisserie\Auth */
    protected $auth;

    /** @var  \Slim\Router */
    protected $router;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->view      = $container->get('view');
        $this->auth      = $container->get('auth');
        $this->router    = $container->get('router');
    }

    public function login(Request $request, Response $response, array $args)
    {
        $username       = null;
        $password       = null;
        $hashedPassword = null;
        $loginFailed    = false;

        if ($request->isPost()) {
            $username = $request->getParam('username');
            $password = $request->getParam('password');


            if ($this->auth->attempt($username, $password)) {
                $this->container['flash']->addMessage('success', 'Logged in!');

                $timezone = $request->getParam('timezone');
                if ($timezone) {
                    $_SESSION['timezone'] = $timezone;
                }

                return $response->withRedirect($this->router->pathFor('browse_entry'));
            } else {
                $loginFailed = true;
            }
        }

        return $this->view->render($response, 'auth/login.twig', [
            'username'    => $username,
            'loginFailed' => $loginFailed
        ]);
    }

    public function logout(Request $request, Response $response, array $args)
    {
        $this->auth->logout();
        $this->container['flash']->addMessage('success', 'Successfully logged out');
        return $response->withRedirect($this->router->pathFor('login'));
    }
}