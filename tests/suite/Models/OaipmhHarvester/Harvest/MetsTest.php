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

    public function testHarvestFiles()
    {
        // To avoid the use of the http client, the harvest is extended to force
        // a local path, simpler to check (another test checks the request).
        // Else, its possible to use urls of true online images.
        require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'MetsMock.php';

        $defaultFilesCount = 3;

        $request = new OaipmhHarvester_Request_Mock();
        $listRecords = file_get_contents(
            dirname(__FILE__) . '/_files/ListRecords.mets.with_files.xml'
        );
        $request->setResponseXml($listRecords);

        $harvest = new OaipmhHarvester_Harvest();
        $harvest->base_url = 'http://www.example.com/oai-pmh';
        $harvest->metadata_prefix = 'mets';
        $harvest->setRequest($request);

        $harvester = new OaipmhHarvester_Harvest_MetsMock($harvest);
        $harvester->harvest();
        $this->assertTrue($harvest->exists());
        $this->assertEquals(
            OaipmhHarvester_Harvest::STATUS_COMPLETED,
            $harvest->status
        );

        $record = get_record('OaipmhHarvester_Record', array(
            'harvest_id ' => $harvest->id,
            'identifier' => 'oai:www.example.com:ark:/12345/b6KN',
        ));
        $this->assertNotEmpty($record);

        $item = get_record_by_id('Item', $record->item_id);
        $this->assertNotEmpty($item);
        $this->assertEquals($defaultFilesCount, $item->fileCount());

        $file = $item->getFile(0);
        $this->assertEquals('File #1', metadata($file, array('Dublin Core', 'Title')));
        $filepath = TEST_FILES_DIR . DIRECTORY_SEPARATOR . 'first' . DIRECTORY_SEPARATOR . 'ophfile1.png';
        $this->assertEquals(md5_file($filepath), $file->authentication);

        $file = $item->getFile(1);
        $this->assertEquals('ark:/12345/b6KN/2', metadata($file, array('Dublin Core', 'Identifier')));
        $filepath = TEST_FILES_DIR . DIRECTORY_SEPARATOR . 'first' . DIRECTORY_SEPARATOR . 'ophfile2.png';
        $this->assertEquals(md5_file($filepath), $file->authentication);

        $file = $item->getFile(2);
        $this->assertEquals('Public domain', metadata($file, array('Dublin Core', 'Rights')));
        $filepath = TEST_FILES_DIR . DIRECTORY_SEPARATOR . 'first' . DIRECTORY_SEPARATOR . 'ophfile3.png';
        $this->assertEquals(md5_file($filepath), $file->authentication);
    }

    /**
     * @depends testHarvestFiles
     */
    public function testUpdateItemAndFiles()
    {
        // First, import the same item than testHarvestFiles().
        $this->testHarvestFiles();

        // Second, update the item via the updated mets file from repository.
        // The item with three files is updated into an item with four files:
        // one unchanged, one removed, one updated, one added.
        $defaultFilesCount = 3;

        $request = new OaipmhHarvester_Request_Mock();
        $listRecords = file_get_contents(
            dirname(__FILE__) . '/_files/ListRecords.mets.with_files.updated.xml'
        );
        $request->setResponseXml($listRecords);

        $harvest = new OaipmhHarvester_Harvest();
        $harvest->base_url = 'http://www.example.com/oai-pmh';
        $harvest->metadata_prefix = 'mets';
        $harvest->setRequest($request);

        $harvester = new OaipmhHarvester_Harvest_MetsMock($harvest);
        $harvester->harvest();
        $this->assertTrue($harvest->exists());
        $this->assertEquals(
            OaipmhHarvester_Harvest::STATUS_COMPLETED,
            $harvest->status
        );

        $items = get_records('Item', array());
        $this->assertEquals(1, count($items));

        $record = get_record('OaipmhHarvester_Record', array(
            'harvest_id ' => $harvest->id,
            'identifier' => 'oai:www.example.com:ark:/12345/b6KN',
        ));
        $this->assertNotEmpty($record);

        $item = get_record_by_id('Item', $record->item_id);
        $this->assertNotEmpty($item);
        $title = metadata($item, array('Dublin Core', 'Title'));
        $this->assertEquals('Foo item updated', $title);
        $identifier = metadata($item, array('Dublin Core', 'Identifier'));
        $this->assertEquals('ark:/12345/b6KN', $identifier);
        $this->assertEquals($defaultFilesCount, $item->fileCount());

        $file = $item->getFile(0);
        $this->assertEquals('File #1 unchanged', metadata($file, array('Dublin Core', 'Title')));
        $filepath = TEST_FILES_DIR . DIRECTORY_SEPARATOR . 'first' . DIRECTORY_SEPARATOR . 'ophfile1.png';
        $this->assertEquals(md5_file($filepath), $file->authentication);

        $file = $item->getFile(1);
        $this->assertEquals('ark:/12345/b6KN/3', metadata($file, array('Dublin Core', 'Identifier')));
        $filepath = TEST_FILES_DIR . DIRECTORY_SEPARATOR . 'update' . DIRECTORY_SEPARATOR . 'ophfile3.png';
        $this->assertEquals(md5_file($filepath), $file->authentication);

        $file = $item->getFile(2);
        $this->assertEquals('File #4 added', metadata($file, array('Dublin Core', 'Title')));
        $filepath = TEST_FILES_DIR . DIRECTORY_SEPARATOR . 'update' . DIRECTORY_SEPARATOR . 'ophfile4.png';
        $this->assertEquals(md5_file($filepath), $file->authentication);
    }
}
