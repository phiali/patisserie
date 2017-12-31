<?php

namespace Patisserie\Controllers;

use Patisserie\Patisserie;
use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;

class BrowseEntryController
{
    protected $container;
    /** @var  Twig */
    protected $view;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->view      = $container->get('view');
    }

    public function browse(Request $request, Response $response, array $args)
    {
        $relativePath = $request->getParam('folder');

        if ($relativePath) {
            $relativePath = Patisserie::sanitizeFolder($relativePath);
        }

        $folderItems       = Patisserie::browsePath(PUBLIC_FOLDER, $relativePath);
        $canCreateItemHere = !array_key_exists('index.md', $folderItems);

        $pathParts = explode('/', $relativePath);
        if (!$pathParts[0]) {
            unset($pathParts[0]);
            $pathParts = array_values($pathParts);
        }

        $tmpPathParts = [];
        for ($index = 0; $index < sizeof($pathParts); $index++) {
            $text = $pathParts[$index];
            $urlParts = array_slice($pathParts, 0, $index + 1);
            $url = implode('/', $urlParts);
            $tmpPathParts[$url] = $text;
        }
        $pathParts = $tmpPathParts;

        return $this->view->render($response, 'entry/browse.twig', [
            'page'         => 'browse_entries',
            'path'         => $relativePath,
            'pathParts'    => $pathParts,
            'pathContents' => $folderItems,
            'canCreateEntryHere' => $canCreateItemHere
        ]);
    }
}