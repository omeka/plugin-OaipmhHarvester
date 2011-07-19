<?php

class OaipmhHarvester_Harvest_AbstractTest extends Omeka_Test_AppTestCase
{
    protected $_coreOptions = array(
        'resources' => array(
            'pluginbroker' => array(
                'plugins' => array('OaipmhHarvester'),
            )
        )
    );

    public function setUp()
    {
        parent::setUp();
        $this->dbHelper = Omeka_Test_Helper_Db::factory($this->core);
    }

    public function testEmptyHarvest()
    {
        $defaultItemCount = 1;
        $harvest = new OaipmhHarvester_Harvest();
        $harvest->base_url = 'http://www.example.com';
        $harvest->metadata_prefix = 'oai_dc';
        $request = new OaipmhHarvester_Request_Mock();
        $listRecords = file_get_contents(
            OAIPMH_HARVESTER_PLUGIN_DIRECTORY 
            . '/tests/_files/ListRecords.response.notoken.txt'
        );
        $request->setResponse($listRecords);
        $harvest->setRequest($request);
        $harvester = new OaipmhHarvester_Harvest_Mock($harvest);
        $harvester->harvest();
        $this->assertTrue($harvest->exists());
        $this->assertEquals(
            OaipmhHarvester_Harvest::STATUS_COMPLETED, 
            $harvest->status
        );
        $this->assertGreaterThan(
            $defaultItemCount, 
            $this->db->getTable('Item')->count()
        );
    }

    public function testRecordExists()
    {
        $record = new OaipmhHarvester_Record();
        $item = insert_item(array('public' => true));
        $harvest = new OaipmhHarvester_Harvest();
        $record->item_id = $item->id;
        $record->identifier = 'foo-bar';
        $record->datestamp = '2010-04-28';
        $harvest->base_url = 'http://example.com';
        $harvest->metadata_prefix = 'oai_dc';
        $harvest->status = OaipmhHarvester_Harvest::STATUS_COMPLETED;
        $request = new OaipmhHarvester_Request_Mock();
        $xmlFile = dirname(__FILE__) . '/_files/ListRecords.xml';
        $request->setResponseXml(file_get_contents($xmlFile));
        $harvest->setRequest($request);
        $harvest->forceSave();
        $record->harvest_id = $harvest->id;
        $record->forceSave();
        $harvester = new OaipmhHarvester_Harvest_OaiDc($harvest);
        $harvester->harvest();
        $item = $this->db->getTable('Item')->find($record->item_id);
        $this->assertEquals("Record Title", item('Dublin Core', 'Title', array(), $item));
    }
}

