<?php
/**
 * Bootstrap file for the background harvesting process.
 * 
 * @package OaipmhHarvester
 * @copyright Copyright (c) 2009 Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

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

// Begin building the global options array.
$options = array();

// Set the ignore deleted records option.
$options['ignore_deleted_records'] = get_option('oaipmh_harvester_ignore_deleted_records') == 'yes' ? true : false;

// Set the metadata prefix.
$metadataPrefix = $harvest->metadata_prefix;

// Set the metadata prefix class.
$metadataClass = $harvest->metadata_class;
$metadataClassFile = ereg_replace('OaipmhHarvester_Harvest_', '', $metadataClass);

require_once 'OaipmhHarvester/Harvest/Abstract.php';
require_once 'OaipmhHarvester/Xml.php';

require_once OAIPMH_HARVESTER_MAPS_DIRECTORY . "/$metadataClassFile.php";

// Set the harvest object.
new $metadataClass($harvest, $options);