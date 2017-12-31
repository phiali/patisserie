<?php

namespace Patisserie\Controllers;

use Patisserie\Patisserie;
use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;

class NewEntryController
{
    protected $container;
    /** @var  Twig */
    protected $view;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->view      = $container->get('view');
    }

    public function new(Request $request, Response $response, array $args)
    {
        $templates = array_keys(Patisserie::browsePath(APPLICATION_PATH . '/templates', ''));
        $folder    = null;
        $title     = null;
        $now       = new \DateTime();

        // If available a timezone would have been set at login.
        if ($_SESSION['timezone']) {
            try {
                $timezone = new \DateTimeZone($_SESSION['timezone']);
                $now->setTimezone($timezone);
            } catch (\Exception $exception) {
                unset($_SESSION['timezone']);
            }
        }

        if ($request->isPost()) {
            $title    = Patisserie::sanitizeTitle($request->getParam('title'));
            $folder   = $request->getParam('folder');
            $template = $request->getParam('template');
            $body     = null;

            // The folder must begin with a /
            if ('/' !== $folder[0]) {
                $folder = "/{$folder}";
            }

            // Ensure that we can access the requested template
            if (in_array($template, $templates)) {
                if (file_exists(APPLICATION_PATH . "/templates/{$template}")) {
                    $body = file_get_contents(APPLICATION_PATH . "/templates/{$template}");
                    $search = [
                        '{{title}}',
                        '{{timestamp}}'
                    ];
                    $replace = [
                        $request->getParam('title', 'Untitled'),
                        $request->getParam('date')
                    ];
                    $body = str_replace($search, $replace, $body);
                }
            }

            $entryComponents = [PUBLIC_FOLDER];

            if ($folder) {
                $entryComponents[] = $folder;
            }

            if ($title) {
                $entryComponents[] = '/' . $title;
            }

            $entryPath = implode($entryComponents);
            $entryPath = Patisserie::sanitizeFolder($entryPath);
            $entryPath.= '/index.md';
            $directory = dirname($entryPath);

            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            // Create the file and redirect the user to the edit page
            if (!file_exists($entryPath)) {
                file_put_contents($entryPath, $body);
            }

            $editPath = str_replace(PUBLIC_FOLDER, '', dirname($entryPath));
            $editUrl  = $this->container['router']->pathFor('edit_entry', [], ['id' => $editPath]);

            return $response->withStatus(302)->withHeader('Location', $editUrl);
        }

        if ($request->isGet()) {
            $title = $now->format('His');

            if ($request->getParam('folder')) {
                $folder = $request->getParam('folder');
            } else {
                $folder = $now->format('/Y/m/d');
            }
        }

        return $this->view->render($response, 'entry/new.twig', [
            'page'      => 'new_entry',
            'templates' => $templates,
            'folder'    => $folder,
            'title'     => $title,
            'date'      => $now->format('Y-m-d H:i:s e')
        ]);
    }
}