<?php

namespace Patisserie\Controllers;

use Patisserie\Patisserie;
use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;

class ConfigureController
{
    protected $container;
    /** @var  Twig */
    protected $view;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->view      = $container->get('view');
    }

    public function password(Request $request, Response $response, array $args)
    {
        $password       = null;
        $hashedPassword = null;

        if ($request->isPost()) {
            if (($password = $request->getParam('password'))) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            }
        }

        return $this->view->render($response, 'configure/password.twig', [
            'password'       => $password,
            'hashedPassword' => $hashedPassword
        ]);
    }
}