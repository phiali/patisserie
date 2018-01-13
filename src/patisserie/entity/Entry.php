<?php

namespace Patisserie\Entity;

class Entry
{
    private $filename = '';
    private $outputFilename = '';
    private $frontMatter = [];
    private $content = '';
    private $originalContent = '';
    private $urlRelative = '';
    private $suppressRender = false;
    private $baseUrl = '';

    /**
     * Construct a new Entry loading the supplied file
     * @param string $filename Filename of the entry
     * @param string $contentFolder Folder in which the content exists
     */
    function __construct($filename, $contentFolder)
    {
        $fullyQualifiedFile = $contentFolder . $filename;
        if (!is_file($fullyQualifiedFile)) {
            throw new \RuntimeException("{$fullyQualifiedFile} is not a file\n");
        }

        if (!is_readable($fullyQualifiedFile)) {
            throw new \RuntimeException("{$fullyQualifiedFile} is not readable\n");
        }

        $this->filename = $fullyQualifiedFile;
        $this->outputFilename = dirname($fullyQualifiedFile) . '/index.html';
        $page           = new \FrontMatter(file_get_contents($fullyQualifiedFile));
        $frontMatter    = $page->data;

        if (array_key_exists('content', $frontMatter)) {
            unset($frontMatter['content']);
        }

        $this->content = $this->originalContent = $page->fetch('content');
        if (is_array($frontMatter)) {
            $frontMatter = $this->array_change_key_case_recursive($frontMatter);
        }

        $this->frontMatter = $frontMatter;

        $pathInfo = pathInfo($fullyQualifiedFile);
        $this->urlRelative = str_replace($contentFolder, '', $pathInfo['dirname']);

        if (!$this->urlRelative) {
            $this->urlRelative = '/';
        }
    }

    /**
     * Reset the content back to what was loaded from the file. This will result in a loss of data if anything
     * has previously changed the content (such as plugins).
     */
    public function resetContent()
    {
        $this->content = $this->originalContent;
    }

    /**
     * Determine if the Front Matter contains the given key
     * @param string|array $key Key to check. Supply an array of keys to check a multidimensional path
     * @return bool True if the key exists otherwise false
     */
    public function hasFrontMatter($key)
    {
        if (is_array($key)) {
            if (count($key) > 2) {
                throw new \RuntimeException("Cannot handle keys with a depth > 2");
            }

            if (array_key_exists($key[0], $this->frontMatter)) {
                if (array_key_exists($key[1], $this->frontMatter[$key[0]])) {
                    return true;
                }
            }
        } else {
            return array_key_exists($key, $this->frontMatter);
        }

        return false;
    }

    /**
     * Return the Front Matter of the Entry
     * @param mixed $key Key
     * @return mixed Array of Front Matter
     */
    public function getFrontMatter($key = null)
    {
        if ($key) {
            if (!$this->hasFrontMatter($key)) {
                throw new \RuntimeException("{$key} does not exist");
            }

            if (!is_array($key)) {
                return $this->frontMatter[$key];
            }

            if (count($key) > 2) {
                throw new \RuntimeException("Cannot handle keys with a depth > 2");
            }

            return $this->frontMatter[$key[0]][$key[1]];
        }

        return $this->frontMatter;
    }

    public function setContent($content)
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Return the content of the Entry
     * @return string Entry content
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Return the content of the Entry before any plugins would have been applied
     * @return string Original content in the post
     */
    public function getOriginalContent()
    {
        return $this->originalContent;
    }

    /**
     * Return the filename backing this entry
     * @return string Filename backing this entry
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Return the filename in which the Entry will be rendered
     * @return string Output filename
     */
    public function getOutputFile()
    {
        return $this->outputFilename;
    }

    /**
     * Determine whether the Entry should be indexed
     * @return bool Whether the Entry should be indexed
     */
    public function isIndexable()
    {
        $isFutureDate = $this->isFutureDated();

        if ($isFutureDate) {
            return false;
        }

        if (array_key_exists('indexable', $this->frontMatter)) {
            if (is_bool($this->frontMatter['indexable'])) {
                return $this->frontMatter['indexable'];
            }

            switch (strtolower($this->frontMatter['indexable'])) {
                case 'no':
                    return false;
                    break;

                case 'yes':
                    return true;
                    break;
            }
        }

        return true;
    }

    /**
     * Determine if the entry has a Created At date in the future
     * @return bool True if the entry is future-dated
     */
    public function isFutureDated()
    {
        if ($this->hasFrontMatter('created_at')) {
            $now       = new \DateTime();
            $createdAt = new \DateTime($this->getFrontMatter('created_at'));

            return $createdAt > $now;
        }

        return false;
    }

    public function getFormattedDate($date, $format)
    {
        if (!($date instanceof \DateTime)) {
            $date = new \DateTime($this->getFrontMatter($date));
        }

        if ($format) {
            return $date->format($format);
        }

        return $date;
    }

    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;
        return $this;
    }

    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    public function getUrl()
    {
        return sprintf("%s%s", $this->baseUrl, self::getRelativeUrl());
    }

    public function getRelativeUrl()
    {
        return $this->urlRelative;
    }

    public function isRenderingSuppressed($state = null)
    {
        if (is_null($state)) {
            return $this->suppressRender;
        }

        $this->suppressRender = (bool)$state;
        return $this;
    }

    /**
     * Change all keys to lowercase (recursively)
     * @param array $array Array to convert
     * @return array Array with lowercase keys
     */
    private function array_change_key_case_recursive($array)
    {
        return array_map(
            function ($item) {
                if (is_array($item)) {
                    $item  = self::array_change_key_case_recursive($item);
                }

                return $item;
            }, array_change_key_case($array)
        );
    }
}