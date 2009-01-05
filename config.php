<?php
// This file contains configuration data and code that should be available to 
// all OaipmhHarvester scripts.

define('OAIPMH_HARVESTER_PLUGIN_DIRECTORY', dirname(__FILE__));
define('OAIPMH_HARVESTER_MAPS_DIRECTORY', OAIPMH_HARVESTER_PLUGIN_DIRECTORY . DIRECTORY_SEPARATOR . 'maps');

require_once 'Oaipmh/Xml.php';

require_once 'OaipmhHarvesterSet.php';
require_once 'OaipmhHarvesterSetTable.php';
require_once 'OaipmhHarvesterSetStatus.php';
require_once 'OaipmhHarvesterSetStatusTable.php';