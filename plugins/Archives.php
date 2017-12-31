<?php

class Archives implements Patisserie\PluginInterface
{
    /** @var \Patisserie\Patisserie */
    private $patisserie;

    public function __construct(\Patisserie\Patisserie $patisserie)
    {
        $this->patisserie = $patisserie;
        $this->patisserie->registerPlugin('contentLoaded', $this, 'generateArchive');
    }

    public function generateArchive(\Patisserie\Entity\Entry $input)
    {
        $pluginCall = '<plugin name="generateArchives" />';
        if (stristr($input->getContent(), $pluginCall) === false) {
            return $input;
        }

        $content = null;
        $entries = $this->patisserie->getEntries('entryCreationTimestamp', SORT_DESC, 0, true);
        $dateBreakdown = [];

        if (is_array($entries)) {
            foreach ($entries as $entry) {
                if (!$entry->hasFrontMatter('created_at')) {
                    continue;
                }

                $entryDate  = $entry->getFormattedDate('created_at', null);
                $entryTitle = null;

                if ($entry->hasFrontMatter('title')) {
                    $entryTitle = $entry->getFrontMatter('title');
                } else {
                    $entryTitle = $entryDate->format('jS F Y @ H:i');
                }

                $dateBreakdown[$entryDate->format('Y')][$entryDate->format('F')][] = [
                    'url'   => $entry->getRelativeUrl(),
                    'title' => $entryTitle
                ];
            }
        }

        foreach ($dateBreakdown as $year => $monthEntries) {
            $content.= sprintf("* %s\n", $year);
            foreach ($monthEntries as $monthName => $entries) {
                $content.= sprintf("\t * %s\n", $monthName);
                foreach ($entries as $entry) {
                    $content.= sprintf("\t\t * [%s](%s)\n", $entry['title'], $entry['url']);
                }
            }
        }

        $input->setContent(
            str_replace($pluginCall, $content, $input->getContent())
        );

        return $input;
    }
}