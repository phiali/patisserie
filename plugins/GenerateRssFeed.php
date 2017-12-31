<?php

class GenerateRssFeed implements Patisserie\PluginInterface
{
    /** @var \Patisserie\Patisserie */
    private $patisserie;

    public function __construct(\Patisserie\Patisserie $patisserie)
    {
        $this->patisserie = $patisserie;
        $this->patisserie->registerPlugin('contentWritten', $this, 'generateRssFeed');
    }

    public function generateRssFeed(array $input)
    {
        $config        = $this->patisserie->getConfig();
        $recentEntries = $this->patisserie->getEntries('entryCreationTimestamp', SORT_DESC, 6, true);

        $feed = new \Zend\Feed\Writer\Feed();
        $feed->setTitle($config['siteTitle']);
        $feed->setDescription($config['siteDescription']);
        $feed->setLink($config['baseUrl']);
        $feed->setDateModified(time());

        foreach ($recentEntries as $entry) {
            if (!$entry->getContent()) {
                continue;
            }

            $entry = $this->rewriteLinks($entry);
            $feedItem = $feed->createEntry();
            $feedItem->setLink($entry->getUrl());
            $feedItem->setContent($entry->getContent());

            if ($entry->hasFrontMatter('title')) {
                $feedItem->setTitle($entry->getFrontMatter('title'));
            } elseif ($entry->hasFrontMatter('created_at')) {
                $feedItem->setTitle($entry->getFormattedDate('created_at', 'jS F Y @ H:i'));
                $feedItem->setDateCreated($entry->getFormattedDate('created_at', null));
            } else {
                $feedItem->setTitle('Untitled');
            }

            if ($entry->hasFrontMatter('modified_at')) {
                $id = sprintf("%s?%s", $entry->getUrl(), $entry->getFormattedDate('modified_at', 'U'));
                $feedItem->setId($id);
                $feedItem->setDateModified($entry->getFormattedDate('modified_at', null));
            } else {
                $id = sprintf("%s?%s", $entry->getUrl(), $entry->getFormattedDate('created_at', 'U'));
                $feedItem->setId($id);
                $feedItem->setDateModified($entry->getFormattedDate('created_at', null));
            }

            $feed->addEntry($feedItem);
        }

        if ($config['rss']['atomFile']) {
            $feedUrl  = sprintf("%s%s", $config['baseUrl'], $config['rss']['atomFile']);
            $feedPath = sprintf("%s%s", $config['contentLocation'], $config['rss']['atomFile']);
            $feed->setFeedLink($feedUrl, 'atom');
            file_put_contents($feedPath, $feed->export('atom'));
        }

        if ($config['rss']['rssFile']) {
            $feedUrl  = sprintf("%s%s", $config['baseUrl'], $config['rss']['rssFile']);
            $feedPath = sprintf("%s%s", $config['contentLocation'], $config['rss']['rssFile']);
            $feed->setFeedLink($feedUrl, 'rss');
            file_put_contents($feedPath, $feed->export('rss'));
        }

        return $input;
    }

    private function rewriteLinks(\Patisserie\Entity\Entry $entry)
    {
        /*
         * DOMDocument doesn't understand HTML5 attributes so we need to suppress warnings.
         * See https://stackoverflow.com/questions/6090667/php-domdocument-errors-warnings-on-html5-tags
         */
        $domDocument = new DOMDocument();
        libxml_use_internal_errors(true);
        $domDocument->loadHTML($entry->getContent());
        libxml_clear_errors();
        $xPath = new DOMXPath($domDocument);
        $nodes = $xPath->query('//a[@href]|//img[@src]');
        $urlSearch  = [];
        $urlReplace = [];

        foreach ($nodes as $node) {
            $link = '';

            switch ($node->tagName) {
                case 'a':
                    $link = $node->getAttribute('href');
                    break;

                case 'img':
                    $link = $node->getAttribute('src');
                    break;
            }

            if (   !$link
                || $this->isAbsoluteLink($link)) {
                continue;
            }

            $urlSearch[]  = $link;
            if ($this->isRelativeLink($link)) {
                $urlReplace[] = sprintf("%s%s", $entry->getBaseUrl(), $link);
            } else {
                $urlReplace[] = sprintf("%s%s/%s", $entry->getBaseUrl(), $entry->getRelativeUrl(), $link);
            }
        }

        if ($urlSearch && $urlReplace) {
            $content = str_replace($urlSearch, $urlReplace, $entry->getContent());
            $entry->setContent($content);
        }

        return $entry;
    }

    private function isAbsoluteLink($link)
    {
        $link = strtolower($link);

        if ((substr($link, 0, 7) === 'http://') || (substr($link, 0, 8) === 'https://')) {
            return true;
        }

        return false;
    }

    private function isRelativeLink($link)
    {
        $link = strtolower($link);

        if (substr($link, 0, 1) === '/') {
            return true;
        }

        return false;
    }
}