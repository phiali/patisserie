<?php

class FrontPage implements Patisserie\PluginInterface
{
    /** @var \Patisserie\Patisserie */
    private $patisserie;

    public function __construct(\Patisserie\Patisserie $patisserie)
    {
        $this->patisserie = $patisserie;
        $this->patisserie->registerPlugin('contentParsed', $this, 'generateFrontPage');
    }

    public function generateFrontPage(\Patisserie\Entity\Entry $input)
    {
        $pluginCall = '<plugin name="frontPage" />';
        if (stristr($input->getContent(), $pluginCall) === false) {
            return $input;
        }

        $recentEntries = $this->patisserie->getEntries('entryCreationTimestamp', SORT_DESC, 5, true);
        $templateData  = ['entries' => $recentEntries];

        $twig    = $this->patisserie->getTwig();
        $content = $twig->render('frontpage-content.twig', $templateData);

        $input->setContent(
            str_replace($pluginCall, $content, $input->getContent())
        );

        return $input;
    }
}