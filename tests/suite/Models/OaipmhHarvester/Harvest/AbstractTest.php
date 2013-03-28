<?php
class OaipmhHarvester_Harvest_AbstractTest extends OaipmhHarvester_Test_AppTestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->dbHelper = new Omeka_Test_Helper_Db($this->db->getAdapter(), $this->db->prefix);
    }

    public function testHarvestCreatesItems()
    {
        $defaultItemCount = 1;
        $harvest = new OaipmhHarvester_Harvest();
        $harvest->base_url = 'http://www.example.com';
        $harvest->metadata_prefix = 'oai_dc';
        $request = new OaipmhHarvester_Request_Mock();
        $listRecords = file_get_contents(
            dirname(__FILE__) . '/_files/ListRecords.notoken.xml'
        );
        $request->setResponseXml($listRecords);
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
            $this->db->fetchOne("select count(*) from {$this->db->Item}")
        );

        return $this->db->fetchAll(
            "select * from {$this->db->Item} where id != 1"
        );
    }

    /**
     * @depends testHarvestCreatesItems
     */
    public function testItemsDefaultToNotPublic($rows)
    {
        foreach ($rows as $row) {
            $this->assertEquals(0, $row['public']);
        }
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
        $harvest->save();
        $record->harvest_id = $harvest->id;
        $record->save();
        $harvester = new OaipmhHarvester_Harvest_OaiDc($harvest);
        $harvester->harvest();
        $item = $this->db->getTable('Item')->find($record->item_id);
        $this->assertEquals("Record Title", metadata($item, array('Dublin Core', 'Title')));
    }
}