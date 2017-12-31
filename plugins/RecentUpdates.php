<?php

class RecentUpdates implements Patisserie\PluginInterface
{
    /** @var \Patisserie\Patisserie */
    private $patisserie;

    public function __construct(\Patisserie\Patisserie $patisserie)
    {
        $this->patisserie = $patisserie;
        $this->patisserie->registerPlugin('contentLoaded', $this, 'generateRecentUpdates');
    }

    public function generateRecentUpdates(\Patisserie\Entity\Entry $input)
    {
        $pluginCall = '<plugin name="recentUpdates" />';
        if (stristr($input->getContent(), $pluginCall) === false) {
            return $input;
        }

        return $input;
    }
}