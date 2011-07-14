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
}

