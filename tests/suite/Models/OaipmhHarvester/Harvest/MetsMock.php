<?php

class OaipmhHarvester_Harvest_MetsMock extends OaipmhHarvester_Harvest_Mets
{
    /**
     * Harvest one record.
     *
     * It extends Mets to force the use of local files, simpler to test.
     *
     * @param SimpleXMLIterator $record XML metadata record
     * @return array Array of item-level, element texts and file metadata.
     */
    protected function _harvestRecord($record)
    {
        $harvestedRecord = parent::_harvestRecord($record);

        if (!empty($harvestedRecord['fileMetadata']['files'])) {
            // Define the transfer type as local file system.
            $harvestedRecord['fileMetadata'][Builder_Item::FILE_TRANSFER_TYPE] = 'Filesystem';
            $harvestedRecord['fileMetadata'][Builder_Item::FILE_INGEST_OPTIONS] = array();

            // Replace each url by a local path.
            foreach ($harvestedRecord['fileMetadata']['files'] as &$file) {
                $path = str_replace('http://www.example.com', TEST_FILES_DIR, $file['source']);
                $file['Filesystem'] = $path;
                $file['source'] = $path;
                unset($file['Url']);
            }
        }

        return $harvestedRecord;
    }
}
