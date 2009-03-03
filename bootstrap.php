<?php
// Require the necessary files. There is probably a better way to do this.
$baseDir = str_replace('plugins/OaipmhHarvester', '', dirname(__FILE__));
require "{$baseDir}paths.php";
require "{$baseDir}application/libraries/Omeka/Core.php";

// Load only the required core phases.
$core = new Omeka_Core;
$core->phasedLoading('initializePluginBroker');

// Set the memory limit.
$memoryLimit = get_option('oaipmh_harvester_memory_limit');
ini_set('memory_limit', "$memoryLimit");

// Set the command line arguments.
$options = getopt('h:');

// Get the database object.
$db = get_db();

// Set the set ID.
$harvestId = $options['h'];

// Set the set.
$harvest = $db->getTable('OaipmhHarvesterHarvest')->find($harvestId);

// Set the metadata prefix.
$metadataPrefix = $harvest->metadata_prefix;

// Set the metadata prefix class.
$metadataPrefixClass = Inflector::camelize($metadataPrefix);

// Set the metdata prefix class name.
$metadataPrefixClassName = "Oaipmh_Harvest_Abstract_$metadataPrefixClass";

require_once 'Oaipmh/Harvest/Abstract.php';
require_once 'Oaipmh/Xml.php';

require_once OAIPMH_HARVESTER_MAPS_DIRECTORY . "/$metadataPrefix.php";

// Set the harvest object.
new $metadataPrefixClassName($harvest);