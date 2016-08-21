<?php

function oaipmh_harvester_config($key, $default = null)
{
    $config = Zend_Registry::get('bootstrap')->getResource('Config');
    if (isset($config->plugins->OaipmhHarvester->$key)) {
        return $config->plugins->OaipmhHarvester->$key;
    } else if ($default) {
        return $default;
    } else {
        return null;
    }
}

/**
 * Get the available OAI-PMH to Omeka maps, which should correspond to
 * OAI-PMH metadata formats.
 * Derived heavily from OaiPmhRepository's getFormats().
 *
 * @internal This list is needed in OaipmhHarvester_IndexController(),
 * OaipmhHarvester_Harvest_Abstract() and OaipmhHarvester_Harvest().
 *
 * @return array
 */
function oaipmh_harvester_get_maps()
{
    $dir = new DirectoryIterator(OAIPMH_HARVESTER_MAPS_DIRECTORY);
    $maps = array();
    foreach ($dir as $dirEntry) {
        if ($dirEntry->isFile() && !$dirEntry->isDot()) {
            $filename = $dirEntry->getFilename();
            $pathname = $dirEntry->getPathname();
            if (preg_match('/^(.+)\.php$/', $filename, $match)
                && $match[1] != 'Abstract'
            ) {
                // Get and set only the name of the file minus the extension.
                require_once($pathname);
                $class = "OaipmhHarvester_Harvest_${match[1]}";
                $metadataSchema = constant("$class::METADATA_SCHEMA");
                $metadataPrefix = constant("$class::METADATA_PREFIX");
                $maps[$metadataPrefix] = array(
                    'class' => $class,
                    'schema' => $metadataSchema,
                );
            }
        }
    }

    return apply_filters('oai_pmh_harvester_maps', $maps);
}
