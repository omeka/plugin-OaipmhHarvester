<?php
/**
 * Plugin hooks and filters.
 * 
 * @package OaipmhHarvester
 * @copyright Copyright (c) 2009-2011 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

/** Path to plugin directory */
defined('OAIPMH_HARVESTER_PLUGIN_DIRECTORY') 
    or define('OAIPMH_HARVESTER_PLUGIN_DIRECTORY', dirname(__FILE__));

/** Path to plugin maps directory */
defined('OAIPMH_HARVESTER_MAPS_DIRECTORY') 
    or define('OAIPMH_HARVESTER_MAPS_DIRECTORY', OAIPMH_HARVESTER_PLUGIN_DIRECTORY 
                                        . '/models/OaipmhHarvester/Harvest');

require_once dirname(__FILE__) . '/functions.php';

/** Plugin hooks */
add_plugin_hook('install', 'oaipmh_harvester_install');
add_plugin_hook('uninstall', 'oaipmh_harvester_uninstall');
add_plugin_hook('define_acl', 'oaipmh_harvester_define_acl');
add_plugin_hook('admin_append_to_plugin_uninstall_message', 
    'oaipmh_harvester_admin_append_to_plugin_uninstall_message');

add_plugin_hook('before_delete_item', 'oaipmh_harvester_before_delete_item');
add_plugin_hook('admin_append_to_items_show_secondary', 
    'oaipmh_harvester_expose_duplicates');

add_plugin_hook('item_browse_sql', 'oaipmh_harvester_item_search_filters');

/** Plugin filters */
add_filter('admin_navigation_main', 'oaipmh_harvester_admin_navigation_main');


