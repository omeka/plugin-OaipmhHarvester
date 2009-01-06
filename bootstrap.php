<?php
// Set a high memory limit to avoid memory allocation issues.
ini_set('memory_limit', '500M');

// Require the necessary files. There is probably a better way to do this.
$baseDir = str_replace('plugins/OaipmhHarvester', '', dirname(__FILE__));
require "{$baseDir}paths.php";
require "{$baseDir}application/libraries/Omeka/Core.php";

// Load only the required core phases.
$core = new Omeka_Core;
$core->phasedLoading('initializePluginBroker');

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

require_once "maps/$metadataPrefix.php";

// Set the harvest object.
new $metadataPrefixClassName($db, $options, $set);