<?php
/**
* OaipmhHarvesterPlugin class - represents the OAI-PMH Harvester plugin
*
* @copyright Copyright 2008-2013 Roy Rosenzweig Center for History and New Media
* @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
* @package OaipmhHarvester
*/


/** Path to plugin directory */
defined('OAIPMH_HARVESTER_PLUGIN_DIRECTORY')
    or define('OAIPMH_HARVESTER_PLUGIN_DIRECTORY', dirname(__FILE__));

/** Path to plugin maps directory */
defined('OAIPMH_HARVESTER_MAPS_DIRECTORY')
    or define('OAIPMH_HARVESTER_MAPS_DIRECTORY', OAIPMH_HARVESTER_PLUGIN_DIRECTORY
                                        . '/models/OaipmhHarvester/Harvest');

require_once dirname(__FILE__) . '/functions.php';

/**
 * OAI-PMH Harvester plugin.
 */
class OaipmhHarvesterPlugin extends Omeka_Plugin_AbstractPlugin
{

    /**
     * @var array Hooks for the plugin.
     */
    protected $_hooks = array(
        'install',
        'upgrade',
        'uninstall',
        'uninstall_message',
        'config_form',
        'config',
        'define_acl',
        'before_delete_item',
        'after_delete_collection',
        'admin_items_show_sidebar',
        'items_browse_sql',
    );

    /**
     * @var array Filters for the plugin.
     */
    protected $_filters = array(
        'admin_navigation_main',
    );

    /**
     * @var array Options and their default values.
     */
    protected $_options = array(
        // With roles, in particular if Guest User is installed.
        'oaipmh_harvester_allow_roles' => 'a:1:{i:0;s:5:"super";}',
    );

    /**
     * Install the plugin.
     */
    public function hookInstall()
    {
        $db = $this->_db;

        /* Harvests/collections:
          id: primary key
          collection_id: the corresponding collection id in `collections`
          base_url: the OAI-PMH base URL
          metadata_prefix: the OAI-PMH metadata prefix used for this harvest
          set_spec: the OAI-PMH set spec (unique identifier)
          set_name: the OAI-PMH set name
          set_description: the Dublin Core description of the set, if any
          status: the current harvest status for this set: queued, in progress,
          completed, error, deleted, killed
          status_messages: any messages sent from the harvester, usually during
          an error status
          initiated: the datetime the harvest initiated
          completed: the datetime the harvest completed
        */
        $sql = "
        CREATE TABLE IF NOT EXISTS `{$db->prefix}oaipmh_harvester_harvests` (
          `id` int unsigned NOT NULL auto_increment,
          `collection_id` int unsigned default NULL,
          `base_url` text NOT NULL,
          `metadata_prefix` tinytext NOT NULL,
          `set_spec` text,
          `set_name` text,
          `set_description` text,
          `update_metadata` enum('element', 'keep', 'strict') NOT NULL,
          `update_files` enum(, 'full', 'keep', 'deduplicate', 'remove') NOT NULL,
          `status` enum('queued','in progress','completed','error','deleted','killed') NOT NULL,
          `status_messages` text,
          `resumption_token` text,
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
          `identifier` text NOT NULL,
          `datestamp` tinytext NOT NULL,
          PRIMARY KEY  (`id`),
          KEY `identifier_idx` (identifier(255)),
          UNIQUE KEY `item_id_idx` (item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
        $db->query($sql);

        $this->_installOptions();
    }

    /**
     * Upgrade the plugin.
     */
    public function hookUpgrade($args)
    {
        $db = $this->_db;
        $oldVersion = $args['old_version'];

        if (version_compare($oldVersion, '2.0-dev', '<')) {
            $sql = <<<SQL
ALTER TABLE `{$db->prefix}oaipmh_harvester_harvests`
    DROP COLUMN `metadata_class`,
    DROP COLUMN `pid`,
    ADD COLUMN `resumption_token` TEXT COLLATE utf8_unicode_ci AFTER `status_messages`
SQL;
            $db->query($sql);

            $sql = <<<SQL
ALTER TABLE `{$db->prefix}oaipmh_harvester_records`
    ADD INDEX `identifier_idx` (identifier(255)),
    ADD UNIQUE INDEX `item_id_idx` (item_id)
SQL;
            $db->query($sql);
        }

        if (version_compare($oldVersion, '2.1', '<')) {
            $sql = "
                ALTER TABLE `{$db->prefix}oaipmh_harvester_harvests`
                ADD `update_files` enum('full', 'keep', 'deduplicate', 'remove') NOT NULL AFTER `set_description`
            ";
            $db->query($sql);
        }

        if (version_compare($oldVersion, '2.1.1', '<')) {
            $sql = "
                ALTER TABLE `{$db->prefix}oaipmh_harvester_harvests`
                ADD `update_metadata` enum('element', 'keep', 'strict') NOT NULL AFTER `set_description`
            ";
            $db->query($sql);
        }
    }

    /**
     * Uninstall the plugin.
     */
    public function hookUninstall()
    {
        $db = $this->_db;

        // drop the tables
        $sql = "DROP TABLE IF EXISTS `{$db->prefix}oaipmh_harvester_harvests`;";
        $db->query($sql);
        $sql = "DROP TABLE IF EXISTS `{$db->prefix}oaipmh_harvester_records`;";
        $db->query($sql);

        $this->_uninstallOptions();
    }

    /**
     * Add a message to the confirm form for uninstallation of the plugin.
     */
    public function hookUninstallMessage()
    {
        echo '<p>While you will not lose the items and collections created by your
        harvests, you will lose all harvest-specific metadata and the ability to
        re-harvest.</p>';
    }

    /**
     * Shows plugin configuration page.
     */
    public function hookConfigForm($args)
    {
        $view = get_view();
        echo $view->partial(
            'plugins/oaipmh-harvester-config-form.php'
        );
    }

    /**
     * Saves plugin configuration page.
     *
     * @param array Options set in the config form.
     */
    public function hookConfig($args)
    {
        $post = $args['post'];
        foreach ($this->_options as $optionKey => $optionValue) {
            if (in_array($optionKey, array(
                    'oaipmh_harvester_allow_roles',
                ))) {
               $post[$optionKey] = serialize($post[$optionKey]) ?: serialize(array());
            }
            if (isset($post[$optionKey])) {
                set_option($optionKey, $post[$optionKey]);
            }
        }

    }

    /**
     * Defines the plugin's access control list.
     *
     * @param array $args
     */
    public function hookDefineAcl($args)
    {
        $acl = $args['acl'];
        $resource = 'OaipmhHarvester_Index';

        // TODO This is currently needed for tests for an undetermined reason.
        if (!$acl->has($resource)) {
            $acl->addResource($resource);
        }
        // Hack to disable CRUD actions.
        $acl->deny(null, $resource, array('show', 'add', 'edit', 'delete'));
        $acl->deny(null, $resource);

        $roles = $acl->getRoles();

        // Check that all the roles exist, in case a plugin-added role has
        // been removed (e.g. GuestUser).
        $allowRoles = unserialize(get_option('oaipmh_harvester_allow_roles')) ?: array();
        $allowRoles = array_intersect($roles, $allowRoles);
        if ($allowRoles) {
            $acl->allow($allowRoles, $resource);
        }

        $denyRoles = array_diff($roles, $allowRoles);
        if ($denyRoles) {
            $acl->deny($denyRoles, $resource);
        }
  }

    /**
    * Appended to admin item show pages.
    *
    * @param array $args
    */
    public function hookAdminItemsShowSidebar($args)
    {
        $item = $args['item'];
        echo $this->_expose_duplicates($item);
    }

    /**
     * Returns a view of any duplicate harvested records for an item
     *
     * @param Item $item The item.
     */
    protected function _expose_duplicates($item)
    {
        if (!$item->exists()) {
            return;
        }
        $items = get_db()->getTable('Item')->findBy(
            array(
                'oaipmh_harvester_duplicate_items' => $item->id,
            )
        );
        if (!count($items)) {
            return '';
        }
        return get_view()->partial('index/_duplicates.php', array('items' => $items));
    }

    /**
     * Deletes harvester record associated with a deleted item.
     *
     * @param array $args
     */
    public function hookBeforeDeleteItem($args)
    {
        $db = $this->_db;
        $item = $args['record'];
        $id = $item->id;
        $recordTable = $db->getTable('OaipmhHarvester_Record');
        $record = $recordTable->findByItemId($id);
        if ($record) {
            $record->delete();
            release_object($record);
        }
    }

    /**
     * Reset harvester collection associated with a deleted collection.
     *
     * @param array $args
     */
    public function hookAfterDeleteCollection($args)
    {
        $collection = $args['record'];
        $harvests = $this->_db->getTable('OaipmhHarvester_Harvest')
            ->findBy(array('collection_id' => $collection->id));
        foreach ($harvests as $harvest) {
            $harvest->collection_id = 0;
            $harvest->save();
            release_object($harvest);
        }
    }

    /**
     * Hooks into item_browse_sql to return items in a particular oaipmh record.
     *
     * @param array $args
     */
    public function hookItemsBrowseSql($args)
    {
        $db = $this->_db;
        $select = $args['select'];
        $params = $args['params'];

        // Filter based on duplicates of a given oaipmh record.
        $dupKey = 'oaipmh_harvester_duplicate_items';
        if (array_key_exists($dupKey, $params)) {
            $itemId = $params[$dupKey];
            $select->where(
                "items.id IN (
                    SELECT oaipmhharvestor_records.item_id FROM $db->OaipmhHarvester_Record oaipmhharvestor_records
                    WHERE oaipmhharvestor_records.identifier IN (
                        SELECT oaipmhharvestor_records2.identifier FROM $db->OaipmhHarvester_Record oaipmhharvestor_records2
                        WHERE oaipmhharvestor_records2.item_id = ?
                    ) AND oaipmhharvestor_records.item_id != ?
                )",
                $itemId
            );
        }
    }

    /**
     * Add the OAI-PMH Harvester link to the admin main navigation.
     *
     * @param array Navigation array.
     * @return array Filtered navigation array.
     */
    public function filterAdminNavigationMain($nav)
    {
        $nav[] = array(
            'label' => __('OAI-PMH Harvester'),
            'uri' => url('oaipmh-harvester'),
            'resource' => 'OaipmhHarvester_Index',
            'privilege' => 'index',
            'class' => 'nav-oai-pmh-harvester'
        );
        return $nav;
    }
}
