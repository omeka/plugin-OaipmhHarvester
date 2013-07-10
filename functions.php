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
