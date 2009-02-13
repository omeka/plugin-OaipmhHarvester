<?php
// Set an error handler function to record run-time warnings (non-fatal errors).
set_error_handler('oaipmhHarvesterErrorHandler', E_WARNING);
function oaipmhHarvesterErrorHandler($errno, $errstr, $errfile, $errline)
{
    $dateTime = date('Y-m-d H:i:s');
    file_put_contents(OAIPMH_HARVESTER_PLUGIN_DIRECTORY . '/errors.log', 
                      "($dateTime) Error: $errstr in $errfile on line $errline\n", 
                      FILE_APPEND);
    return true;
}

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
$options = getopt('s:');

// Get the database object.
$db = get_db();

// Set the set ID.
$setId = $options['s'];

// Set the set.
$set = $db->getTable('OaipmhHarvesterSet')->find($setId);

// Set the metadata prefix.
$metadataPrefix = $set->metadata_prefix;

// Set the metadata prefix class.
$metadataPrefixClass = Inflector::camelize($metadataPrefix);

// Set the metdata prefix class name.
$metadataPrefixClassName = "Oaipmh_Harvest_Abstract_$metadataPrefixClass";

require_once 'Oaipmh/Harvest/Abstract.php';
require_once 'Oaipmh/Xml.php';

require_once OAIPMH_HARVESTER_MAPS_DIRECTORY . "/$metadataPrefix.php";

// Set the harvest object.
new $metadataPrefixClassName($set);