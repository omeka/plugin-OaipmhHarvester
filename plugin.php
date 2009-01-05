<?php
require 'config.php';

define('OAIPMH_HARVESTER_PLUGIN_VERSION', '0.2');

add_plugin_hook('install', 'oaipmh_harvester_install');
add_plugin_hook('uninstall', 'oaipmh_harvester_uninstall');

add_filter('admin_navigation_main', 'oaipmh_harvester_admin_navigation_main');

function oaipmh_harvester_install()
{
    set_option('oaipmh_harvester_plugin_version', OAIPMH_HARVESTER_PLUGIN_VERSION);
    
    $db = get_db();
    
    // Harvested sets/collections.
    /*
	id: primary key
	collection_id: the corresponding collection id in `collections`
	status_id: the current harvest status for this set 
	base_url: the OAI-PMH base URL
	set_spec: the OAI-PMH set spec (unique identifier)
	set_name: the OAI-PMH set name
	metadata_prefix: the OAI-PMH metadata prefix used for this harvest
	messages: any messages sent from the harvester, usually only during an error status
	initiated: the datetime the harvest initiated
	completed: the datetime the harvest completed
	*/
	$sql = "
	CREATE TABLE IF NOT EXISTS `{$db->prefix}oaipmh_harvester_sets` (
		`id` int(10) unsigned NOT NULL auto_increment,
  		`collection_id` int(10) unsigned NULL,
  		`status_id` int(10) unsigned NOT NULL,
  		`base_url` text collate utf8_unicode_ci NOT NULL,
  		`set_spec` text collate utf8_unicode_ci NOT NULL,
  		`set_name` text collate utf8_unicode_ci NOT NULL,
  		`set_description` text collate utf8_unicode_ci NULL,
  		`metadata_prefix` tinytext collate utf8_unicode_ci NOT NULL,
  		`messages` text collate utf8_unicode_ci NULL,
  		`initiated` datetime default NULL,
  		`completed` datetime default NULL,
  		PRIMARY KEY  (`id`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
	$db->query($sql);
	
	// Set harvest statuses.
	/*
	id: primary key
	name: the name of the status
	description: the description of the status
	*/
	$sql ="
	CREATE TABLE IF NOT EXISTS `{$db->prefix}oaipmh_harvester_set_statuses` (
  		`id` mediumint(8) unsigned NOT NULL auto_increment,
  		`name` tinytext collate utf8_unicode_ci NOT NULL,
  		`description` tinytext collate utf8_unicode_ci,
  		PRIMARY KEY  (`id`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
	$db->query($sql);
	
	$sql = "
	INSERT INTO `{$db->prefix}oaipmh_harvester_set_statuses` 
	(`id`, `name`, `description`) VALUES
	(NULL, 'In Progress', NULL),
	(NULL, 'Completed', NULL),
	(NULL, 'Error', NULL);";
	$db->query($sql);
		
	// Harvested records/items.
	/*
	id: primary key
	set_id: the corresponding set id in `oaipmh_harvester_sets`
	item_id: the corresponding item id in `items`
	identifier: the OAI-PMH record identifier (unique identifier)
	datestamp: the OAI-PMH record datestamp
	*/
	$sql = "
    CREATE TABLE IF NOT EXISTS `{$db->prefix}oaipmh_harvester_records` (
		`id` int(10) unsigned NOT NULL auto_increment,
		`set_id` int(10) unsigned NOT NULL,
		`item_id` int(10) unsigned NOT NULL,
		`identifier` text collate utf8_unicode_ci NOT NULL,
		`datestamp` tinytext collate utf8_unicode_ci NOT NULL,
		PRIMARY KEY  (`id`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
	$db->query($sql);
}

function oaipmh_harvester_uninstall()
{
    delete_option('oaipmh_harvester_plugin_version');
    
    $db = get_db();
    $sql = "DROP TABLE IF EXISTS `{$db->prefix}oaipmh_harvester_sets`;";
    $db->query($sql);
    $sql = "DROP TABLE IF EXISTS `{$db->prefix}oaipmh_harvester_set_statuses`;";
    $db->query($sql);
    $sql = "DROP TABLE IF EXISTS `{$db->prefix}oaipmh_harvester_records`;";
    $db->query($sql);
}

function oaipmh_harvester_admin_navigation_main($nav)
{
	$nav['OAI-PMH Harvester'] = uri('oaipmh-harvester');
	return $nav;
}