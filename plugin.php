<?php
/**
 * Plugin hooks and filters.
 * 
 * @package OaipmhHarvester
 * @copyright Copyright (c) 2009 Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

/** Plugin version number */
define('OAIPMH_HARVESTER_PLUGIN_VERSION', get_plugin_ini('OaipmhHarvester', 'version'));

/** Path to plugin directory */
define('OAIPMH_HARVESTER_PLUGIN_DIRECTORY', dirname(__FILE__));

/** Path to plugin maps directory */
define('OAIPMH_HARVESTER_MAPS_DIRECTORY', OAIPMH_HARVESTER_PLUGIN_DIRECTORY 
                                        . '/libraries/OaipmhHarvester/Harvest');
/** OaipmhHarvester_Xml (library) */
require_once 'OaipmhHarvester/Xml.php';

/** OaipmhHarvesterHarvest (model) */
require_once 'OaipmhHarvesterHarvest.php';

/** OaipmhHarvesterHarvestTable (model) */
require_once 'OaipmhHarvesterHarvestTable.php';

/** OaipmhHarvesterRecord (model) */
require_once 'OaipmhHarvesterRecord.php';

/** OaipmhHarvesterRecordTable (model) */
require_once 'OaipmhHarvesterRecordTable.php';

/** Plugin hooks */
add_plugin_hook('install', 'oaipmh_harvester_install');
add_plugin_hook('uninstall', 'oaipmh_harvester_uninstall');
add_plugin_hook('config_form', 'oaipmh_harvester_config_form');
add_plugin_hook('config', 'oaipmh_harvester_config');
add_plugin_hook('define_acl', 'oaipmh_harvester_define_acl');

add_plugin_hook('before_delete_item', 'oaipmh_harvester_before_delete_item');
add_plugin_hook('admin_append_to_items_show_secondary', 'oaipmh_harvester_expose_duplicates');

/** Plugin filters */
add_filter('admin_navigation_main', 'oaipmh_harvester_admin_navigation_main');

/**
 * install callback
 * 
 * Sets options and creates tables.
 * 
 * @return void
 */
function oaipmh_harvester_install()
{
    set_option('oaipmh_harvester_plugin_version', OAIPMH_HARVESTER_PLUGIN_VERSION);
    
    $db = get_db();
    
    /* Harvests/collections:
        id: primary key
        collection_id: the corresponding collection id in `collections`
        base_url: the OAI-PMH base URL
        metadata_prefix: the OAI-PMH metadata prefix used for this harvest
        set_spec: the OAI-PMH set spec (unique identifier)
        set_name: the OAI-PMH set name
        set_description: the Dublin Core description of the set, if any
        status: the current harvest status for this set: starting, in progress, completed, error, deleted
        status_messages: any messages sent from the harvester, usually during an error status
        initiated: the datetime the harvest initiated
        completed: the datetime the harvest completed
    */
    $sql = "
    CREATE TABLE IF NOT EXISTS `{$db->prefix}oaipmh_harvester_harvests` (
        `id` int(10) unsigned NOT NULL auto_increment,
        `collection_id` int(10) unsigned default NULL,
        `base_url` text collate utf8_unicode_ci NOT NULL,
        `metadata_prefix` tinytext collate utf8_unicode_ci NOT NULL,
        `metadata_class` text collate utf8_unicode_ci NOT NULL,
        `set_spec` text collate utf8_unicode_ci NULL,
        `set_name` text collate utf8_unicode_ci NULL,
        `set_description` text collate utf8_unicode_ci NULL,
        `status` enum('starting','in progress','completed','error','deleted','killed') collate utf8_unicode_ci NOT NULL default 'starting',
        `status_messages` text collate utf8_unicode_ci NULL,
        `initiated` datetime default NULL,
        `completed` datetime default NULL,
        `start_from` datetime default NULL,
        `pid` int(10) unsigned default NULL,
        PRIMARY KEY  (`id`)
    ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
    $db->query($sql);
    
    /* Harvested records/items.
        id: primary key
        harvest_id: the corresponding set id in `oaipmh_harvester_harvests`
        item_id: the corresponding item id in `items`
        identifier: the OAI-PMH record identifier (unique identifier)
        datestamp: the OAI-PMH record datestamp
    */
    $sql = "
    CREATE TABLE IF NOT EXISTS `{$db->prefix}oaipmh_harvester_records` (
        `id` int(10) unsigned NOT NULL auto_increment,
        `harvest_id` int(10) unsigned NOT NULL,
        `item_id` int(10) unsigned default NULL,
        `identifier` text collate utf8_unicode_ci NOT NULL,
        `datestamp` tinytext collate utf8_unicode_ci NOT NULL,
        PRIMARY KEY  (`id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
    $db->query($sql);
}

/**
 * uninstall callback.
 * 
 * Deletes options and drops tables.
 * 
 * @return void
 */
function oaipmh_harvester_uninstall()
{
    delete_option('oaipmh_harvester_plugin_version');
    delete_option('oaipmh_harvester_php_path');
    
    $db = get_db();
    $sql = "DROP TABLE IF EXISTS `{$db->prefix}oaipmh_harvester_harvests`;";
    $db->query($sql);
    $sql = "DROP TABLE IF EXISTS `{$db->prefix}oaipmh_harvester_records`;";
    $db->query($sql);
}

/**
 * config_form callback.
 * 
 * Prepares and renders the plugin's configuration form.
 * 
 * @return void
 */
function oaipmh_harvester_config_form()
{
    if (!$path = get_option('oaipmh_harvester_php_path')) {
        // Get the path to the PHP-CLI command. This does not account for 
        // servers without a PHP CLI or those with a different command name for 
        // PHP, such as "php5".
        $command = 'which php 2>&0';
        $lastLineOutput = exec($command, $output, $returnVar);
        $path = $returnVar == 0 ? trim($lastLineOutput) : '';
    }
    
    if (!$memoryLimit = get_option('oaipmh_harvester_memory_limit')) {
        $memoryLimit = ini_get('memory_limit');
    }
    
    include 'config_form.php';
}

/**
 * config callback.
 * 
 * Handles a submitted configuration form by setting options.
 * 
 * @return void
 */
function oaipmh_harvester_config()
{
    $path = realpath($_POST['oaipmh_harvester_php_path']);
    if (!$path) {
        throw new Exception('Error: The path to PHP-CLI is invalid.');
    }
    
    set_option('oaipmh_harvester_php_path', $path);
    set_option('oaipmh_harvester_memory_limit', $_POST['oaipmh_harvester_memory_limit']);
}

/**
 * define_acl callback.
 * 
 * Defines the plugin's access control list.
 * 
 * @param object $acl
 */
function oaipmh_harvester_define_acl($acl)
{
    // Allow only super and admin roles to this plugin's controller's actions.
    $acl->loadResourceList(array('OaipmhHarvester_Index' => array('index', 
                                                                  'sets', 
                                                                  'status', 
                                                                  'delete')));
}

/**
 * Deletes harvester record associated with a deleted item.
 * @param Item $item The deleted item.
 */
function oaipmh_harvester_before_delete_item(Item $item)
{
    $id = $item->id;
    $recordTable = get_db()->getTable('OaipmhHarvesterRecord');
    $record = $recordTable->findByItemId($id);
    if($record) {
        $record->delete();
        release_object($record);
    }
}

/**
 * Outputs any duplicate harvested records.
 * Appended to admin item show pages.
 */
function oaipmh_harvester_expose_duplicates()
{
    $id = item('id');
    $recordTable = get_db()->getTable('OaipmhHarvesterRecord');
    $record = $recordTable->findByItemId($id);
    $duplicates = $recordTable->findByOaiIdentifier($record->identifier);
    
    foreach($duplicates as $duplicate) {
        if($duplicate->item_id == $id) continue;
        $items[] = $duplicate->item_id;
    }
    
    if(count($items) > 0) { ?>
        <div id="harvester-duplicates" class="info-panel">
        <h2>Duplicate Harvested Items</h2>
        <ul>
        <?php foreach($items as $itemId) {
            $item = get_db()->getTable('Item')->find($itemId);
            $uri = item_uri('show', $item); ?>
            <li>
            <?php echo "<a href=\"$uri\">Item $itemId</a>"; ?>
            </li>
            <?php release_object($item);
        } ?>
        </ul>
        </div> <?php
    }
}

/**
 * admin_navigation_main filter.
 * 
 * @param array $nav Array of main navigation tabs.
 * @return array Filtered array of main navigation tabs.
 */
function oaipmh_harvester_admin_navigation_main($nav)
{
    if (has_permission('OaipmhHarvester_Index', 'index')) {
        $nav['OAI-PMH Harvester'] = uri('oaipmh-harvester');
    }
    return $nav;
}
