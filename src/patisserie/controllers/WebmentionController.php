<?php

namespace Patisserie\Controllers;

use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

class WebmentionController
{
    /** @var array */
    protected $siteConfig;

    public function __construct(ContainerInterface $container)
    {
        $this->siteConfig = $container->get('siteConfig');
    }

    public function index(Request $request, Response $response, array $args)
    {
        $source = $request->getParam('source');
        $target = $request->getParam('target');
        if (!filter_var($source, FILTER_VALIDATE_URL)
            || !filter_var($target, FILTER_VALIDATE_URL)) {
            return $response->withStatus(400);
        }

        try {
            $this->queueWebmention($source, $target);
        } catch (\Exception $exception) {
            return $response->withStatus(500);
        }

        return $response->withStatus(202);
    }

    /**
     * Queue a webmention request for processing later
     * @param string $source Source URL
     * @param string $target Target URL
     */
    private function queueWebmention($source, $target)
    {
        $data = ['source' => $source, 'target' => $target, 'timestamp' => date('c')];
        $folder = sprintf('%s%sdata/webmention', APPLICATION_PATH, DIRECTORY_SEPARATOR);

        if (!is_dir($folder) && !mkdir($folder, 0777, true) && !is_dir($folder)) {
            throw new \RuntimeException('Unable to create the webmention folder');
        }

        $dataFile = tempnam($folder, 'queue-');
        if (!$dataFile) {
            throw new \RuntimeException('Unable to queue entry');
        }

        file_put_contents($dataFile, json_encode($data));
    }
}
