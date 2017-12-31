<?php

class GenerateRelativeUrls implements Patisserie\PluginInterface
{
    /** @var \Patisserie\Patisserie */
    private $patisserie;

    public function __construct(\Patisserie\Patisserie $patisserie)
    {
        $this->patisserie = $patisserie;
        $this->patisserie->registerPlugin('contentParsed', $this, 'rewriteUrls');
    }

    /**
     * Ensure that all URLs reference the entry path. This helps to reduce errors such as when a URL doesn't end in a /
     * which would result in images/links not working.
     *
     * @param \Patisserie\Entity\Entry $input
     * @return \Patisserie\Entity\Entry
     */
    public function rewriteUrls(\Patisserie\Entity\Entry $input)
    {
        if (!$input->getContent()) {
            return $input;
        }

        /*
         * DOMDocument doesn't understand HTML5 attributes so we need to suppress warnings.
         * See https://stackoverflow.com/questions/6090667/php-domdocument-errors-warnings-on-html5-tags
         */
        $domDocument = new DOMDocument();
        libxml_use_internal_errors(true);
        $domDocument->loadHTML($input->getContent());
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
                || $this->isAbsoluteLink($link)
                || $this->isRelativeLink($link)) {
                continue;
            }

            $urlSearch[] = $link;
            $urlReplace[] = sprintf("%s/%s", $input->getRelativeUrl(), $link);
        }

        $content = str_replace($urlSearch, $urlReplace, $input->getContent());
        $input->setContent($content);

        return $input;
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