<?php

namespace Patisserie\Controllers;

use Patisserie\Patisserie;
use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;
use Symfony\Component\Yaml\Yaml;

class EditEntryController
{
    protected $container;
    /** @var  Twig */
    protected $view;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->view      = $container->get('view');
    }

    public function edit(Request $request, Response $response, array $args)
    {
        $relativePath = $request->getQueryParam('id');
        $relativeFile = $relativePath . DIRECTORY_SEPARATOR . 'index.md';

        if ($relativePath) {
            $relativePath = Patisserie::sanitizeFolder($relativePath);
        }

        $entryPath    = PUBLIC_FOLDER . $relativePath . DIRECTORY_SEPARATOR . 'index.md';
        $uploadFolder = PUBLIC_FOLDER . $relativePath;

        if (!is_file($entryPath) || !is_readable($entryPath)) {
            $this->container['flash']->addMessage('error', 'Unable to access ' . $relativePath);
            return $response->withStatus(302)->withHeader('Location', '/_p/browse');
        }

        $p = new Patisserie([]);

        if ($request->isPost()) {
            // Handle any file uploads
            $uploadedFiles = $request->getUploadedFiles();
            foreach ($uploadedFiles['userUploads'] as $uploadedFile) {
                /** @var \Slim\Http\UploadedFile $uploadedFile */
                if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
                    $uploadedFile->moveTo($uploadFolder . DIRECTORY_SEPARATOR . $uploadedFile->getClientFilename());
                }
            }

            /**
             * We'll trigger a site rebuild if either the new post is indexable or the previous one was. This needs to
             * be done so as to allow RSS feeds or the front page to be regenerated.
             */
            $rebuildSite   = false;
            $originalEntry = $p->getEntry($relativeFile);
            $formData = $request->getParsedBody();
            $output   = sprintf("---\n%s---\n\n%s", $formData['entryFrontMatter'], $formData['entryContent']);
            file_put_contents($entryPath, $output, LOCK_EX);
            $entry = $p->getEntry($relativeFile);

            if ($entry->isIndexable() || $originalEntry->isIndexable()) {
                $rebuildSite = true;
            }

            if ($rebuildSite) {
                $p->buildSite(false);
            } else {
                $p->publishEntry($entry);
            }

            unset($entry);
        }

        $entry = $p->getEntry($relativeFile, true);

        return $this->view->render($response, 'entry/edit.twig', [
            'page'             => 'edit_entry',
            'formAction'       => $this->container['router']->pathFor('edit_entry', [], ['id' => $relativePath]),
            'entry'            => $entry,
            'entryPath'        => $relativePath,
            'entryFrontMatter' => ($entry->getFrontMatter()) ? Yaml::dump($entry->getFrontMatter()) : null,
            'entryContent'     => $entry->getOriginalContent(),
            'userFiles'        => $this->browsePath(PUBLIC_FOLDER, $relativePath)
        ]);
    }

    private function browsePath($basePath, $relativePath)
    {
        $pathContents    = [];
        $validExtensions = [];
        $excludedItems   = ['.', '..', '_p_static', 'index.html', 'index.md'];
        $path            = $basePath . $relativePath;

        if (is_dir($path)) {
            if ($directoryHandle = opendir($path)) {
                while (($file = readdir($directoryHandle)) !== false) {
                    if (in_array($file, $excludedItems)) {
                        continue;
                    }

                    $fileType = filetype($path . '/' . $file);
                    if ('dir' === $fileType) {
                        continue;
                    }

                    // We don't want to include any files that begin with a period (.)
                    if ('.' === $file[0]) {
                        continue;
                    }

                    if ($validExtensions) {
                        $pathInfo = pathinfo($file);
                        if (!in_array($pathInfo['extension'], $validExtensions)) {
                            continue;
                        }
                    }

                    $pathContents[] = $file;
                }
            }
        }

        return $pathContents;
    }
}