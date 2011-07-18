<?php

/**
 * install callback
 * 
 * Sets options and creates tables.
 * 
 * @return void
 */
function oaipmh_harvester_install()
{    
    $db = get_db();
    
    /* Harvests/collections:
        id: primary key
        collection_id: the corresponding collection id in `collections`
        base_url: the OAI-PMH base URL
        metadata_prefix: the OAI-PMH metadata prefix used for this harvest
        set_spec: the OAI-PMH set spec (unique identifier)
        set_name: the OAI-PMH set name
        set_description: the Dublin Core description of the set, if any
        status: the current harvest status for this set: starting, in progress, 
        completed, error, deleted
        status_messages: any messages sent from the harvester, usually during 
        an error status
        initiated: the datetime the harvest initiated
        completed: the datetime the harvest completed
    */
    $sql = "
    CREATE TABLE IF NOT EXISTS `{$db->prefix}oaipmh_harvester_harvests` (
        `id` int unsigned NOT NULL auto_increment,
        `collection_id` int unsigned default NULL,
        `base_url` text collate utf8_unicode_ci NOT NULL,
        `metadata_prefix` tinytext collate utf8_unicode_ci NOT NULL,
        `set_spec` text collate utf8_unicode_ci NULL,
        `set_name` text collate utf8_unicode_ci NULL,
        `set_description` text collate utf8_unicode_ci NULL,
        `status` enum('queued','in progress','completed','error','deleted','killed') collate utf8_unicode_ci NOT NULL default 'queued',
        `status_messages` text collate utf8_unicode_ci NULL,
        `resumption_token` text collate utf8_unicode_ci default NULL,
        `initiated` datetime default NULL,
        `completed` datetime default NULL,
        `start_from` datetime default NULL,
        PRIMARY KEY  (`id`)
    ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
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
        `id` int unsigned NOT NULL auto_increment,
        `harvest_id` int unsigned NOT NULL,
        `item_id` int unsigned default NULL,
        `identifier` text collate utf8_unicode_ci NOT NULL,
        `datestamp` tinytext collate utf8_unicode_ci NOT NULL,
        PRIMARY KEY  (`id`),
        KEY `identifier_idx` (identifier(255)),
        UNIQUE KEY `item_id_idx` (item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
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
    $db = get_db();
    $sql = "DROP TABLE IF EXISTS `{$db->prefix}oaipmh_harvester_harvests`;";
    $db->query($sql);
    $sql = "DROP TABLE IF EXISTS `{$db->prefix}oaipmh_harvester_records`;";
    $db->query($sql);
}

function oaipmh_harvester_admin_append_to_plugin_uninstall_message()
{
    echo '<p>While you will not lose the items and collections created by your 
    harvests, you will lose all harvest-specific metadata and the ability to 
    re-harvest.</p>';
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
    $recordTable = get_db()->getTable('OaipmhHarvester_Record');
    $record = $recordTable->findByItemId($id);
    if($record) {
        $record->delete();
        release_object($record);
    }
}

function oaipmh_harvester_item_search_filters($select, $params)
{
    // Filter based on duplicates of a given oaipmh record.
    $db = get_db();
    $dupKey = 'oaipmh_harvester_duplicate_items';
    if (array_key_exists($dupKey, $params)) {
        $itemId = $params[$dupKey];
        $select->where(
            "i.id IN (
                select records.item_id from $db->OaipmhHarvester_Record records
                where records.identifier IN (
                    select r2.identifier from $db->OaipmhHarvester_Record r2
                    where r2.item_id = ?
                ) and records.item_id != ?
            )",
            $itemId
        );
    }
}

/**
 * Outputs any duplicate harvested records.
 * Appended to admin item show pages.
 */
function oaipmh_harvester_expose_duplicates()
{
    $id = item('id');
    $items = get_db()->getTable('Item')->findBy(
        array(
            'oaipmh_harvester_duplicate_items' => $id,
        )
    );
    
    if(count($items) > 0) { ?>
        <div id="harvester-duplicates" class="info-panel">
        <h2>Duplicate Harvested Items</h2>
        <ul>
        <?php foreach($items as $item): ?>
            <li>
            <?php echo link_to_item(
                    'Item #' . item('id', null, $item),
                    array(),
                    'show',
                    $item
                ); ?>
            </li>
            <?php release_object($item); ?>
        <?php endforeach; ?>
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

function oaipmh_harvester_config($key, $default = null)
{
    $config = Omeka_Context::getInstance()->config;
    $harvesterConfig = $config->plugins->OaipmhHarvester;
    if ($harvesterConfig && isset($harvesterConfig->$key)) {
        return $harvesterConfig->$key;
    } else if ($default) {
        return $default;
    }
}

function oh_snippet($str, $len, $app = '…')
{
    $use = $str;
    if (strlen($str) > $len) {
        $use = substr($str, 0, $len - strlen($app)) . $app;
    } 
    return $use;
}

//from Peter from dezzignz.com 05-Apr-2010 11:30 @ php.net
function mb_string_to_array($str) {
    if (empty($str)) return false;
    $len = mb_strlen($str);
    $array = array();
    for ($i = 0; $i < $len; $i++) {
        $array[] = mb_substr($str, $i, 1);
    }
    return $array;
}

function mb_chunk_split($str, $len, $glue) {
    if (empty($str)) return false;
    $array = mb_string_to_array($str);
    $n = 0;
    $new = '';
    foreach ($array as $char) {
        if ($n < $len) $new .= $char;
        elseif ($n == $len) {
            $new .= $glue . $char;
            $n = 0;
        }
        $n++;
    }
    return $new;
}
