<?php
define('OAIPMH_HARVESTER_PLUGIN_VERSION', '0.2');
define('OAIPMH_HARVESTER_PLUGIN_DIRECTORY', dirname(__FILE__));
define('OAIPMH_HARVESTER_MAPS_DIRECTORY', OAIPMH_HARVESTER_PLUGIN_DIRECTORY 
                                        . DIRECTORY_SEPARATOR 
                                        . 'maps');

require_once 'Oaipmh/Xml.php';
require_once 'OaipmhHarvesterSet.php';
require_once 'OaipmhHarvesterSetTable.php';
require_once 'OaipmhHarvesterRecord.php';
require_once 'OaipmhHarvesterRecordTable.php';

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
    base_url: the OAI-PMH base URL
    set_spec: the OAI-PMH set spec (unique identifier)
    set_name: the OAI-PMH set name
    metadata_prefix: the OAI-PMH metadata prefix used for this harvest
    status: the current harvest status for this set; in progress, completed, error
    status_messages: any messages sent from the harvester, usually only during an error status
    initiated: the datetime the harvest initiated
    completed: the datetime the harvest completed
    */
    $sql = "
    CREATE TABLE IF NOT EXISTS `{$db->prefix}oaipmh_harvester_sets` (
        `id` int(10) unsigned NOT NULL auto_increment,
        `collection_id` int(10) unsigned default NULL,
        `base_url` text collate utf8_unicode_ci NOT NULL,
        `set_spec` text collate utf8_unicode_ci NOT NULL,
        `set_name` text collate utf8_unicode_ci NOT NULL,
        `set_description` text collate utf8_unicode_ci,
        `metadata_prefix` tinytext collate utf8_unicode_ci NOT NULL,
        `status` enum('starting','in progress','completed','error') collate utf8_unicode_ci NOT NULL default 'starting',
        `status_messages` text collate utf8_unicode_ci,
        `initiated` datetime default NULL,
        `completed` datetime default NULL,
        PRIMARY KEY  (`id`)
    ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
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
        `item_id` int(10) unsigned default NULL,
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
    $sql = "DROP TABLE IF EXISTS `{$db->prefix}oaipmh_harvester_records`;";
    $db->query($sql);
}

function oaipmh_harvester_admin_navigation_main($nav)
{
    $nav['OAI-PMH Harvester'] = uri('oaipmh-harvester');
    return $nav;
}