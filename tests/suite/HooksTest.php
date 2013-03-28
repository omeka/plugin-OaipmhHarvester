<?php
class OaipmhHarvester_HooksTest extends OaipmhHarvester_Test_AppTestCase
{
    protected $_isAdminTest = true;

    public function setUp()
    {
        parent::setUp();        
        $this->dbHelper = new Omeka_Test_Helper_Db($this->db->getAdapter(), $this->db->prefix);
    }

    public static function aclProvider()
    {
        return array(
            array('super', 'OaipmhHarvester_Index', 'index', true),
            array('super', 'OaipmhHarvester_Index', 'sets', true),
            array('super', 'OaipmhHarvester_Index', 'status', true),
            array('super', 'OaipmhHarvester_Index', 'delete', true),
            array('admin', 'OaipmhHarvester_Index', 'index', true),
            array('admin', 'OaipmhHarvester_Index', 'sets', true),
            array('admin', 'OaipmhHarvester_Index', 'status', true),
            array('admin', 'OaipmhHarvester_Index', 'delete', true),
        );
    }

    public function testDefineAcl()
    {
        $ruleSet = self::aclProvider();
        foreach ($ruleSet as $rule) {
            list($role, $resource, $priv, $allowed) = $rule;
            $this->assertEquals(
                $allowed,
                $this->acl->isAllowed($role, $resource, $priv)
            );
        }
    }

    public function testInstall()
    {
        $tables = array(
            $this->db->OaipmhHarvester_Record,
            $this->db->OaipmhHarvester_Harvest,
        );
        foreach ($tables as $table) {
            $this->assertTrue($this->dbHelper->tableExists($table));
        }
    }

    public function testUninstall()
    {
        $tables = array(
            $this->db->OaipmhHarvester_Record,
            $this->db->OaipmhHarvester_Harvest,
        );
        $loader = Zend_Registry::get('plugin_loader');
        $installer = new Omeka_Plugin_Installer(
            $this->pluginbroker,
            $loader
        );
        $installer->uninstall($loader->getPlugin('OaipmhHarvester'));
        foreach ($tables as $table) {
            $this->assertFalse($this->dbHelper->tableExists($table));
        }
    }

    public function testDeleteItem()
    {
        $item = insert_item();
        $record = new OaipmhHarvester_Record();
        $record->item_id = $item->id;
        $record->identifier = 'foo-bar';
        $record->harvest_id = 10000;
        $record->datestamp = '2011-07-11';
        $record->save();
        $item->delete();
        $table = $this->db->getTable('OaipmhHarvester_Record');
        release_object($item);
        release_object($record);
        $this->assertEquals(
            0, 
            $table->count()
        );
    }

    public function testItemsShowPanel()
    {
        $this->_authenticateUser($this->_getDefaultUser());
        $orig = insert_item();
        $dup1 = insert_item();
        $dup2 = insert_item();
        $identifier = 'my-fake-id';
        $datestamp = '2011-07-24';
        $harvestId = 10000;
        foreach (array($orig, $dup1, $dup2) as $item) {
            $record = new OaipmhHarvester_Record();
            $record->setArray(array(
                'identifier' => $identifier,
                'item_id' => $item->id,
                'datestamp' => $datestamp,
                'harvest_id' => $harvestId,
            ));
            $record->save();
        }
        $this->dispatch('/items/show/' . $orig->id);
        $this->assertQuery('div#harvester-duplicates');
        $this->assertQueryCount(
            'div#harvester-duplicates li', 
            2,
            "Should be 2 duplicates of this item."
        );
    }

    public function testAdminNavigationMain()
    {
        $this->_authenticateUser($this->_getDefaultUser());
        $this->dispatch('/');
        $this->assertQuery('ul.navigation li a.nav-oai-pmh-harvester');
    }
}