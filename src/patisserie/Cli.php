<?php

namespace Patisserie;

use Symfony\Component\Yaml\Yaml;

class Cli
{
    // https://getcomposer.org/doc/articles/scripts.md
    private static function setup()
    {
        if (!defined('APPLICATION_PATH')) {
            $path = realpath(__DIR__ . '/../../');
            define('APPLICATION_PATH', $path);
        }
    }

    // Mac-Mini:patisserie alistair$ clear && ./docker.sh composer index-site -- --test=me
    public static function indexSite($event)
    {
        self::setup();
        $patisserie = new Patisserie([]);
        $patisserie->indexEntries();
    }

    public static function buildSite()
    {
        self::setup();
        $patisserie = new Patisserie([]);
        $patisserie->buildSite(false);
    }

    public static function rebuildSite()
    {
        self::setup();
        $patisserie = new Patisserie([]);
        $patisserie->buildSite(true);
    }

    public static function migrateEntries()
    {
        /*
         * Scan for index.txt and index.md files in the public folder
         * Determine if they contain YAML-frontmatter at all
         * If not then
         */
        self::setup();
        $contentFiles = ['index.md', 'index.txt'];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                APPLICATION_PATH . '/public',
                \RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        // Iterate over the content folder looking for files
        foreach ($iterator as $key => $val) {
            $filename = $val->getFilename();
            $pathname = $val->getPathname();

            if (!in_array(strtolower($filename), $contentFiles)) {
                continue;
            }

            $page = new \FrontMatter(file_get_contents($pathname));
            $frontMatter = $page->data;

            if (count($frontMatter) > 1) {
                continue;
            }

            echo sprintf("Migrating %s\n", $pathname);

            /** @var \Zend\Mail\Headers $postHeaders */
            $postHeaders = null;
            $postContent = null;

            try {
                \Zend\Mime\Decode::splitMessage(
                    file_get_contents($pathname),
                    $postHeaders,
                    $postContent
                );
            } catch (\Exception $exception) {
                $errorMessage = sprintf("An exception occurred processing %s (%s)", $pathname, $exception->getMessage());
                throw new \RuntimeException($errorMessage);
            }

            $postHeaders = array_change_key_case($postHeaders->toArray(), CASE_LOWER);

            // Re-write some headers
            $headerReplacement = [
                'date'         => 'created_at',
                'updated'      => 'modified_at',
                'last-updated' => 'modified_at',
                'x-index'      => 'indexable',
                'x-template'   => 'template',
                'x-keywords'   => 'keywords'
            ];

            $frontMatter = [];
            foreach ($postHeaders as $k => $v) {
                if (array_key_exists($k, $headerReplacement)) {
                    $k = $headerReplacement[$k];
                }

                switch (strtolower($k)) {
                    case 'template':
                        $pathInfo = pathinfo($v);
                        if (!array_key_exists('extension', $pathInfo)) {
                            $v = sprintf("%s.twig", $v);
                        }
                        break;

                    case 'keywords':
                        $v = str_replace(', ', ',', $v);
                        $v = explode(',', $v);
                        break;

                    case 'created_at':
                        if (stripos($v, '/') === false) {
                            throw new \RuntimeException('Date missing timezone identifier ' . $pathname);
                        }
                        break;

                    case 'indexable':
                        $v = strtolower($v);
                        break;

                    case 'x-link':
                        $k = 'link';
                        $v = ['url' => $v];
                }

                $frontMatter[$k] = $v;
            }

            $migratedContent = sprintf("---\n%s---\n\n%s", Yaml::dump($frontMatter), $postContent);
            $pathInfo = pathinfo($pathname);
            $migratedFilename = $pathname;

            if ('txt' === strtolower($pathInfo['extension'])) {
                $migratedFilename = sprintf("%s/index.md", $pathInfo['dirname']);
                unlink($pathname);
            }
            file_put_contents($migratedFilename, $migratedContent);
        }
    }
}