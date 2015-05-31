<?php
class OaipmhHarvester_Harvest_MetsTest extends OaipmhHarvester_Test_AppTestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->dbHelper = new Omeka_Test_Helper_Db($this->db->getAdapter(), $this->db->prefix);

        // To avoid the use of the http client, the harvest is extended to force a local
        // path, simpler to check (another test checks the request).
        // Else, its possible to use urls of true online images.
        require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'MetsMock.php';
    }

    public function testHarvestItem()
    {
        $params = array(
            'source' => dirname(__FILE__) . '/_files/ListRecords.mets.xml',
            'defaultFilesCount' => 0,
            'item title' => 'Foo item',
            'files' => array(),
        );

        $this->_harvestAndCheck($params);
    }

    public function testHarvestFiles()
    {
        $params = array(
            'source' => dirname(__FILE__) . '/_files/ListRecords.mets.with_files.xml',
            'defaultFilesCount' => 3,
            'item title' => 'Foo item',
            'files' => array(
                array(
                    'number' => 0,
                    'path' => 'first' . DIRECTORY_SEPARATOR . 'ophfile1.png',
                    'element' => 'Title',
                    'value' => 'File #1',
                ),
                array(
                    'number' => 1,
                    'path' => 'first' . DIRECTORY_SEPARATOR . 'ophfile2.png',
                    'element' => 'Identifier',
                    'value' => 'ark:/12345/b6KN/2',
                ),
                array(
                    'number' => 2,
                    'path' => 'first' . DIRECTORY_SEPARATOR . 'ophfile3.png',
                    'element' => 'Rights',
                    'value' => 'Public domain',
                ),
            ),
        );

        $this->_harvestAndCheck($params);
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
        $params = array(
            'source' => dirname(__FILE__) . '/_files/ListRecords.mets.with_files.updated.xml',
            'defaultFilesCount' => 3,
            'item title' => 'Foo item updated',
            'files' => array(
                array(
                    'number' => 0,
                    'path' => 'first' . DIRECTORY_SEPARATOR . 'ophfile1.png',
                    'element' => 'Title',
                    'value' => 'File #1 unchanged',
                ),
                array(
                    'number' => 1,
                    'path' => 'update' . DIRECTORY_SEPARATOR . 'ophfile3.png',
                    'element' => 'Identifier',
                    'value' => 'ark:/12345/b6KN/3',
                ),
                array(
                    'number' => 2,
                    'path' => 'update' . DIRECTORY_SEPARATOR . 'ophfile4.png',
                    'element' => 'Title',
                    'value' => 'File #4 added',
                ),
            ),
        );

        $this->_harvestAndCheck($params);
    }

    /**
     * @depends testUpdateItemAndFiles
     */
    public function testUpdateOrderOfFiles()
    {
        // First, import the same item than testUpdateItemAndFiles().
        $this->testUpdateItemAndFiles();

        // Second, reorder the files via the updated mets file from repository.
        $params = array(
            'source' => dirname(__FILE__) . '/_files/ListRecords.mets.with_files.reordered.xml',
            'defaultFilesCount' => 3,
            'item title' => 'Foo item updated',
            'files' => array(
                array(
                    'number' => 0,
                    'path' => 'update' . DIRECTORY_SEPARATOR . 'ophfile4.png',
                    'element' => 'Title',
                    'value' => 'File #4 added, reordered as first',
                ),
                array(
                    'number' => 1,
                    'path' => 'first' . DIRECTORY_SEPARATOR . 'ophfile1.png',
                    'element' => 'Title',
                    'value' => 'File #1 unchanged reordered',
                ),
                array(
                    'number' => 2,
                    'path' => 'update' . DIRECTORY_SEPARATOR . 'ophfile3.png',
                    'element' => 'Identifier',
                    'value' => 'ark:/12345/b6KN/3',
                ),
            ),
        );

        $this->_harvestAndCheck($params);
    }

    /**
     * Helper to check the first update and the reorder (same tests).
     *
     * @param array $params
     */
    protected function _harvestAndCheck($params)
    {
        $defaultItemCount = 1;
        $defaultFilesCount = $params['defaultFilesCount'];

        $request = new OaipmhHarvester_Request_Mock();
        $listRecords = file_get_contents($params['source']);
        $request->setResponseXml($listRecords);

        $harvest = new OaipmhHarvester_Harvest();
        $harvest->base_url = 'http://www.example.com/oai-pmh';
        $harvest->metadata_prefix = 'mets';
        $harvest->setRequest($request);

        $harvester = empty($params['files'])
            ? new OaipmhHarvester_Harvest_Mets($harvest)
            : new OaipmhHarvester_Harvest_MetsMock($harvest);
        $harvester->harvest();
        $this->assertTrue($harvest->exists());
        $this->assertEquals(
            OaipmhHarvester_Harvest::STATUS_COMPLETED,
            $harvest->status
        );

        $items = get_records('Item', array());
        $this->assertEquals(1, count($items));

        $records = get_records('OaipmhHarvester_Record', array(
            'harvest_id' => $harvest->id,
        ));
        $this->assertEquals($defaultItemCount, count($records));
        $record = reset($records);

        $item = get_record_by_id('Item', $record->item_id);
        $this->assertNotEmpty($item);
        $title = metadata($item, array('Dublin Core', 'Title'));
        $this->assertEquals($params['item title'], $title);
        $identifier = metadata($item, array('Dublin Core', 'Identifier'));
        $this->assertEquals('ark:/12345/b6KN', $identifier);
        $this->assertEquals($defaultFilesCount, $item->fileCount());

        foreach ($params['files'] as $paramFile) {
            $file = $item->getFile($paramFile['number']);
            $this->assertEquals($paramFile['value'], metadata($file, array('Dublin Core', $paramFile['element'])));
            $filepath = TEST_FILES_DIR . DIRECTORY_SEPARATOR . $paramFile['path'];
            $this->assertEquals(md5_file($filepath), $file->authentication);
        }
    }
}
