<?php

namespace Patisserie;

use \Cocur\Slugify\Slugify;
use \Patisserie\Entity\Entry;
use Symfony\Component\Yaml\Yaml;

class Patisserie
{
    /** @var \Twig_Environment */
    private $twig;
    private $config = [];
    private $index = [];
    private $plugins = [];

    public function __construct(array $config)
    {
        $yaml = file_get_contents(APPLICATION_PATH . '/config/site.yaml');
        $config = Yaml::parse($yaml);
        if (!array_key_exists('contentLocation', $config)) {
            throw new \RuntimeException("Ensure that 'contentLocation' exists in the configuration");
        }

        $contentPath = realpath(APPLICATION_PATH . '/' . $config['contentLocation']);
        if (!$contentPath) {
            throw new \RuntimeException("'contentLocation' is set to {$config['contentLocation']} which does not appear to exist");
        }

        $config['contentLocation'] = $contentPath;

        $this->config = $config;
        $this->index  = $this->loadIndex();
        $this->loadPlugins();
    }

    public function __destruct()
    {
        $this->saveIndex();
    }

    private function loadPlugins()
    {
        foreach (glob(APPLICATION_PATH . '/plugins/*.php') as $filename) {
            $pathInfo = pathinfo($filename);
            require_once $filename;
            new $pathInfo['filename']($this);
        }
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function registerPlugin($action, $class, $method)
    {
        $action = strtolower($action);
        $this->plugins[$action][] = [
            'class' => $class,
            'method' => $method
        ];
    }

    private function applyPlugin($action, $data)
    {
        $action = strtolower($action);

        if (array_key_exists($action, $this->plugins)) {
            foreach ($this->plugins[$action] as $plugin) {
                $reflection = new \ReflectionObject($plugin['class']);
                $method     = $reflection->getMethod($plugin['method']);
                $data = $method->invoke($plugin['class'], $data);
            }
        }

        return $data;
    }

    public function getTwig()
    {
        if (!$this->twig) {
            $loader = new \Twig\Loader\FilesystemLoader(APPLICATION_PATH . '/templates');
            $this->twig = new \Twig_Environment($loader, ['autoescape' => false]);
        }

        return $this->twig;
    }

    /**
     * Persist any modifications to the index
     */
    private function saveIndex()
    {
        $originalIndex = $this->loadIndex(true);
        if (sha1($originalIndex) !== sha1(serialize($this->index))) {
            file_put_contents( APPLICATION_PATH . '/data/index.serialized', serialize($this->index));
        }
    }

    /**
     * Remove the local index
     */
    private function deleteIndex()
    {
        file_put_contents(APPLICATION_PATH . '/data/index.serialized', null);
    }

    /**
     * Load and return the system index.
     * @param bool $rawContent When set to false (default) it'll return unserialised data. Otherwise it'll be the raw content
     * @return bool|mixed|null|string
     */
    private function loadIndex($rawContent = false)
    {
        $returnData = null;
        if (file_exists(APPLICATION_PATH . '/data/index.serialized')) {
            $fileData = file_get_contents(APPLICATION_PATH . '/data/index.serialized');
            if ($rawContent) {
                $returnData = $fileData;
            } else {
                $returnData = unserialize($fileData);
            }
        }

        return $returnData;
    }

    /**
     * Builds the site outputting rendered content
     * @param bool $rebuildAll When set to true the entire site will be rebuilt. When set to false only changed content is written.
     */
    public function buildSite($rebuildAll)
    {
        $this->indexEntries();
        $entries        = ($rebuildAll) ? array_keys($this->index) : $this->getDirtyEntries();
        $dynamicEntries = [];

        // This should be moved further down. We don't want to fire a 'siteBuilt' event for these dynamic pages
        if (   array_key_exists('dynamicPages', $this->config)
            && is_array($this->config['dynamicPages'])) {
            foreach ($this->config['dynamicPages'] as $dynamicPage) {
                $dynamicEntries[] = $dynamicPage;
            }
        }

        if (!$entries) {
            return;
        }

        foreach (array_merge($entries, $dynamicEntries) as $entryFilename) {
            if ('cli' === php_sapi_name()) {
                echo sprintf("Writing %s\n", $entryFilename);
            }

            try {
                $entry = new Entry($entryFilename, $this->config['contentLocation']);
                $entry->setBaseUrl($this->config['baseUrl']);
                $this->publishEntry($entry);
            } catch (\Exception $exception) {
                echo $exception->getMessage();
            }
        }

        if ($entries) {
            $this->applyPlugin('contentWritten', $entries);
        }
    }

    /**
     * Retrieve a list of items that have been modified since last written
     * @return array Array of modified items
     */
    private function getDirtyEntries()
    {
        $entries = [];

        foreach ($this->index as $key => $val) {
            if ($val['fileModificationTimestamp'] > $val['fileWriteTimestamp']) {
                $entries[] = $key;
            }
        }

        return $entries;
    }

    /**
     * Index all entries
     */
    public function indexEntries()
    {
        $this->deleteIndex();
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $this->config['contentLocation'],
                \RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        // Iterate over the content folder looking for files
        foreach ($iterator as $key => $val) {
            $filename = $val->getFilename();
            $pathname = $val->getPathname();

            if (!in_array(strtolower($filename), $this->config['contentFiles'])) {
                continue;
            }

            $relativePathname = str_replace($this->config['contentLocation'], '', $pathname);
            $entry = new Entry($relativePathname, $this->config['contentLocation']);
            $entry->setBaseUrl($this->config['baseUrl']);
            $this->indexEntry($entry);
        }
    }

    /**
     * Index the given Entry
     * @param Entry $entry Entry to index
     */
    private function indexEntry(Entry $entry) {
        $metadata                   = pathinfo($entry->getFilename());
        $relativePath               = str_replace($this->config['contentLocation'], '', $metadata['dirname']);
        $relativeFilename           = str_replace($this->config['contentLocation'], '', $entry->getFilename());
        $fileWriteTimestamp         = 0;
        $entryCreationTimestamp     = 0;
        $entryModificationTimestamp = 0;

        if ($entry->hasFrontMatter('created_at')) {
            $entryCreationTimestamp = (int)$entry->getFormattedDate('created_at', 'U');
        }

        if ($entry->hasFrontMatter('modified_at')) {
            $entryModificationTimestamp = (int)$entry->getFormattedDate('modified_at', 'U');
        }

        if (file_exists($entry->getOutputFile())) {
            $fileWriteTimestamp = filemtime($entry->getOutputFile());
        }

        if (!$relativePath) {
            $relativePath = '/';
        }

        $this->index[$relativeFilename] = [
            'url'                       => sprintf("%s%s", $this->config['baseUrl'], $relativePath),
            'urlRelative'               => $relativePath,
            'indexableEntry'            => $entry->isIndexable(),
            'fileWriteTimestamp'        => $fileWriteTimestamp,
            'fileModificationTimestamp' => filemtime($entry->getFilename()),
            'entryCreationTimestamp'    => $entryCreationTimestamp,
            'entryModificationTimestamp'=> $entryModificationTimestamp
        ];
    }

    /**
     * @param Entry $entry
     * @return Entry
     */
    public function renderEntry(Entry $entry)
    {
        $frontMatter = $entry->getFrontMatter();
        $template    = ($frontMatter['template']) ?? $this->config['defaultTemplate'];
        $twig        = $this->getTwig();

        $entry->resetContent();
        $entry  = $this->applyPlugin('contentLoaded', $entry);

        $parser = new \ParsedownExtra();
        $html   = $parser->text($entry->getContent());
        $entry->setContent($html);
        $entry  = $this->applyPlugin('contentParsed', $entry);

        /**
         * It's possible that a plugin may have already completed rendering of this entry in which
         * case we'll simply return the content as is.
         */
        if ($entry->isRenderingSuppressed()) {
            return $entry;
        }

        $renderedContent = '';

        try {
            $renderedContent = $twig->render($template, [
                'entry'        => $entry,
                'entryContent' => $entry->getContent()
            ]);
        } catch (\Twig_Error_Loader $exception) {
            // If the template wasn't found and doesn't have an extension then we'll try and append .twig
            $pathInfo = pathinfo($template);
            if (!array_key_exists('extension', $pathInfo)) {
                $template.= '.twig';
                $renderedContent = $twig->render($template, [
                    'entry'        => $entry,
                    'entryContent' => $entry->getContent()
                ]);
            }
        }

        $entry->setContent($renderedContent);
        return $entry;
    }

    /**
     * Publish the given Entry generating rendered output
     * @param Entry $entry
     */
    public function publishEntry(Entry $entry)
    {
        $entry = $this->renderEntry($entry);
        file_put_contents(
            $entry->getOutputFile(), $entry->getContent()
        );

        // Ensure that the file is writeable by all, see https://stackoverflow.com/a/1240731/89783
        chmod($entry->getOutputFile(), fileperms($entry->getOutputFile()) | 128 + 16 + 2);

        /*if ($entry->isIndexable()) {
            $this->indexEntry($entry);
            $this->applyPlugin('contentWritten', [$entry]);
        }*/
    }

    /**
     * @param $filename
     * @param bool $suppressRendering
     * @return Entry
     */
    public function getEntry($filename, $suppressRendering = false)
    {
        $entry = new Entry($filename, $this->config['contentLocation']);
        $entry->setBaseUrl($this->config['baseUrl']);
        $entry->isRenderingSuppressed($suppressRendering);
        return $this->renderEntry($entry);
    }

    /**
     * Retrieve entries based on search conditions
     * @param string $sortBy One of fileWriteTimestamp, fileModificationTimestamp, entryCreationTimestamp or entryModificationTimestamp
     * @param int $sortDirection Sort direction, either SORT_ASC or SORT_DESC
     * @param int $limit Number of entries to restrict results to (0 for all)
     * @param bool $suppressRendering Whether to suppress the rendering of the entries
     * @return Entity\Entry[] Array of entries
     */
    public function getEntries($sortBy, $sortDirection, $limit, $suppressRendering)
    {
        $sortByOptions = [
            'fileWriteTimestamp', 'fileModificationTimestamp', 'entryCreationTimestamp', 'entryModificationTimestamp'
        ];

        if (!in_array($sortBy, $sortByOptions)) {
            throw new \RuntimeException(
                "Invalid sortBy option '${$sortBy}'. Must be one of " . implode(', ', $sortByOptions)
            );
        }

        $sortDirectionOptions = [SORT_ASC, SORT_DESC];
        if (!in_array($sortDirection, $sortDirectionOptions)) {
            throw new \RuntimeException("Invalid sortDirection, must be one of SORT_ASC or SORT_DESC");
        }

        // Reduce the entries down to only those that were indexable at the time of indexing
        $indexableEntries = array_filter($this->index, function($v, $k) {
            return $v['indexableEntry'];
        }, ARRAY_FILTER_USE_BOTH);

        $sortColumn = [];
        foreach ($indexableEntries as $key => $val) {
            $sortColumn[$key] = $val[$sortBy];
        }

        array_multisort($sortColumn, $sortDirection, $indexableEntries);

        if (is_numeric($limit) && $limit > 0) {
            $indexableEntries = array_slice($indexableEntries, 0, $limit);
        }

        $entries = [];
        foreach ($indexableEntries as $key => $val) {
            $entries[] = $this->getEntry($key, $suppressRendering);
        }

        return $entries;
    }

    /**
     * Sanitize a folder name making it safe to use within the filesystem
     * @param string $folder Folder name
     * @return string Sanitized folder
     */
    public static function sanitizeFolder($folder)
    {
        $separator = '-';
        $folder = preg_replace('#([^A-Za-z0-9/_])+#', $separator, $folder);
        $folder = preg_replace('#/+#', '/', $folder);
        $folder = trim($folder, $separator);

        return $folder;
    }

    /**
     * Sanitize a title making it safe to use within the filesystem
     * @param string $title Entry title
     * @return string Sanitized title
     */
    public static function sanitizeTitle($title)
    {
        $slugify  = new Slugify();
        return $slugify->slugify($title);
    }

    public static function browsePath($basePath, $relativePath)
    {
        $pathContents    = [];
        $validExtensions = ['md'];
        $excludedItems   = ['.', '..', '_p_static'];
        $path            = $basePath . $relativePath;

        if (is_dir($path)) {
            if ($directoryHandle = opendir($path)) {
                while (($file = readdir($directoryHandle)) !== false) {
                    if (in_array($file, $excludedItems)) {
                        continue;
                    }

                    $fileType = filetype($path . '/' . $file);
                    if ('dir' === $fileType) {
                        $pathContents[$file] = [
                            'type' => 'directory'
                        ];
                        continue;
                    }

                    $pathInfo = pathinfo($file);
                    if (!in_array($pathInfo['extension'], $validExtensions)) {
                        continue;
                    }

                    $pathContents[$file] = [
                        'type' => 'file'
                    ];
                }
            }
        }

        ksort($pathContents, SORT_NATURAL);

        return $pathContents;
    }

}