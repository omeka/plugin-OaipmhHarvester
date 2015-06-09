<?php
class OaipmhHarvester_Harvest_MetsTest extends OaipmhHarvester_Test_AppTestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->dbHelper = new Omeka_Test_Helper_Db($this->db->getAdapter(), $this->db->prefix);
    }

    public function testHarvestItem()
    {
        $defaultItemCount = 1;

        $request = new OaipmhHarvester_Request_Mock();
        $listRecords = file_get_contents(
            dirname(__FILE__) . '/_files/ListRecords.mets.xml'
        );
        $request->setResponseXml($listRecords);

        $harvest = new OaipmhHarvester_Harvest();
        $harvest->base_url = 'http://www.example.com/oai-pmh';
        $harvest->metadata_prefix = 'mets';
        $harvest->setRequest($request);

        $harvester = new OaipmhHarvester_Harvest_Mets($harvest);
        $harvester->harvest();
        $this->assertTrue($harvest->exists());
        $this->assertEquals(
            OaipmhHarvester_Harvest::STATUS_COMPLETED,
            $harvest->status
        );

        $records = get_records('OaipmhHarvester_Record', array(
            'harvest_id' => $harvest->id,
        ));
        $this->assertEquals($defaultItemCount, count($records));

        $record = reset($records);
        $item = get_record_by_id('Item', $record->item_id);
        $this->assertNotEmpty($item);
        $title = metadata($item, array('Dublin Core', 'Title'));
        $this->assertEquals('Foo item', $title);
        $identifier = metadata($item, array('Dublin Core', 'Identifier'));
        $this->assertEquals('ark:/12345/b6KN', $identifier);
    }
}
