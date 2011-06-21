<?php
/**
 * @package OaipmhHarvester
 * @subpackage Libraries
 * @copyright Copyright (c) 2009 Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

/**
 * Abstract class on which all other metadata format maps are based.
 *
 * @package OaipmhHarvester
 * @subpackage Libraries
 */
abstract class OaipmhHarvester_Harvest_Abstract
{
    /**
     * Notice message code, used for status messages.
     */
    const MESSAGE_CODE_NOTICE = 1;
    
    /**
     * Error message code, used for status messages.
     */
    const MESSAGE_CODE_ERROR = 2;
    
    /**
     * Date format for OAI-PMH requests.
     * Only use day-level granularity for maximum compatibility with
     * repositories.
     */
    const OAI_DATE_FORMAT = 'Y-m-d';
    
    /**
     * @var OaipmhHarvester_Harvest The OaipmhHarvester_Harvest object model.
     */
    private $_harvest;
    
    /**
     * @var OaipmhHarvester_Xml The current, cached OaipmhHarvester_Xml object.
     */
    private $_oaipmhHarvesterXml;
    
    /**
     * @var SimpleXMLIterator The current, cached SimpleXMLIterator record object.
     */
    private $_record;
    
    /**
     * Class constructor.
     * 
     * Prepares the harvest process.
     * 
     * @param OaipmhHarvester_Harvest $harvest The OaipmhHarvester_Harvest object 
     * model
     * @return void
     */
    public function __construct($harvest)
    {   
        // Set an error handler method to record run-time warnings (non-fatal 
        // errors). Fatal and parse errors cannot be called in this way.
        set_error_handler(array($this, 'errorHandler'), E_WARNING);
        
        $this->_harvest = $harvest;
    }
    
    /**
     * Abstract method that all class extentions must contain.
     * 
     * @param SimpleXMLIterator The current record object
     */
    abstract protected function harvestRecord($record);
    
    /**
     * Checks whether the current record has already been harvested, and
     * returns the record if it does.
     *
     * @param SimpleXMLIterator record to be harvested
     * @return OaipmhHarvester_Record|false The model object of the record,
     *      if it exists, or false otherwise.
     */
    private function _recordExists($record)
    {   
        $existing = false;
        
        $identifier = $record->header->identifier;
        $datestamp = $record->header->datestamp;
        
        $records = get_db()->getTable('OaipmhHarvester_Record')->findByOaiIdentifier($identifier);
        
        /* Ideally, the OAI identifier would be globally-unique, but for
           poorly configured servers that might not be the case.  However,
           the identifier is always unique for that repository, so given
           already-existing identifiers, check against the base URL.
        */
        foreach($records as $existingRecord)
        {
            $harvest_id = $existingRecord->harvest_id;
            $recordHarvest = get_db()->getTable('OaipmhHarvester_Harvest')->find($harvest_id);
            $baseUrl = $recordHarvest->base_url;
            $setSpec = $recordHarvest->set_spec;
            $metadataPrefix = $recordHarvest->metadata_prefix;
            // Check against the URL of the cached harvest object.
            if($baseUrl == $this->_harvest->base_url &&
                $setSpec == $this->_harvest->set_spec &&
                $metadataPrefix == $this->_harvest->metadata_prefix)
            {
                $existing = $existingRecord;
                break;
            }
        }
        return $existing;
    }
    
    /**
     * Recursive method that loops through all requested records
     * 
     * This method hands off mapping to the class that extends off this one and 
     * recurses through all resumption tokens until the response is completed.
     * 
     * @param string|false $resumptionToken
     * @return string|boolean Resumption token if one exists, otherwise true
     * if the harvest is finished.
     */
    private function _harvestRecords()
    {

        // Iterate through the records and hand off the mapping to the classes 
        // inheriting from this class.
        $response = $this->_harvest->listRecords();
        foreach ($response['records'] as $record) {
            
            // Ignore (skip over) deleted records.
            if ($this->isDeletedRecord($record)) {
                continue;
            }
            $existingRecord = $this->_recordExists($record);
            $harvestedRecord = $this->harvestRecord($record);
            
            // Cache the record for later use.
            $this->_record = $record;
            
            // Record has already been harvested
            if($existingRecord) {
                // If datestamp has changed, update the record, otherwise ignore.
                if($existingRecord->datestamp != $record->header->datestamp) {
                    $this->updateItem($existingRecord,
                                      $harvestedRecord['elementTexts'],
                                      $harvestedRecord['fileMetadata']);
                }
                release_object($existingRecord);
            }
            else $this->insertItem($harvestedRecord['itemMetadata'],
                                   $harvestedRecord['elementTexts'],
                                   $harvestedRecord['fileMetadata']);
        }
        
        $resumptionToken = $response['resumptionToken'];
        $this->addStatusMessage("Received resumption token: $resumptionToken");

        return ($resumptionToken ? $resumptionToken : true);
    }
    
    /**
     * Return whether the record is deleted
     * 
     * @param SimpleXMLIterator The record object
     * @return bool
     */
    public function isDeletedRecord($record)
    {
        if (isset($record->header->attributes()->status) 
            && $record->header->attributes()->status == 'deleted') {
            return true;
        }
        return false;
    }
    
    /**
     * Insert a record into the database.
     * 
     * @param Item $item The item object corresponding to the record.
     * @return void
     */
    private function _insertRecord($item)
    {
        $record = new OaipmhHarvester_Record;
        
        $record->harvest_id = $this->_harvest->id;
        $record->item_id    = $item->id;
        $record->identifier = (string) $this->_record->header->identifier;
        $record->datestamp  = (string) $this->_record->header->datestamp;
        $record->save();
        
        release_object($record);
    }
    
    /**
     * Update a record in the database with information from this harvest.
     * 
     * @param OaipmhHarvester_Record The model object corresponding to the record.
     */
    private function _updateRecord(OaipmhHarvester_Record $record)
    {   
        $record->datestamp  = (string) $this->_record->header->datestamp;
        $record->save();
    }
    
    /**
     * Return the current, formatted date.
     * 
     * @return string
     */
    private function _getCurrentDateTime()
    {
        return date('Y-m-d H:i:s');
    }
    
    /**
     * Template method.
     * 
     * May be overwritten by classes that extend of this one. This method runs 
     * once, prior to record iteration.
     * 
     * @see self::__construct()
     */
    protected function beforeHarvest()
    {
    }
    
    /**
     * Template method.
     * 
     * May be overwritten by classes that extend of this one. This method runs 
     * once, after record iteration.
     * 
     * @see self::__construct()
     */
    protected function afterHarvest()
    {
    }
    
    /**
     * Insert a collection.
     * 
     * @see insert_collection()
     * @param array $metadata
     * @return Collection
     */
    final protected function insertCollection($metadata = array())
    {
        // If collection_id is not null, use the existing collection, do not
        // create a new one.
        if(($collection_id = $this->_harvest->collection_id)) {
            $collection = get_db()->getTable('Collection')->find($collection_id);
        }
        else {
            // There must be a collection name, so if there is none, like when the 
            // harvest is repository-wide, set it to the base URL.
            if (!isset($metadata['name']) || !$metadata['name']) {
                $metadata['name'] = $this->_harvest->base_url;
            }
        
            // The `collections` table does not allow NULL descriptions, so set to 
            // an empty string. This is most likely a bug in Omeka's core.
            if (!isset($metadata['description']) || !$metadata['description']) {
                $metadata['description'] = '';
            }
        
            $collection = insert_collection($metadata);
        
            // Remember to set the harvest's collection ID once it has been saved.
            $this->_harvest->collection_id = $collection->id;
            $this->_harvest->save();
        }
        return $collection;
    }
    
    /**
     * Convenience method for inserting an item and its files.
     * 
     * Method used by map writers that encapsulates item and file insertion. 
     * Items are inserted first, then files are inserted individually. This is 
     * done so Item and File objects can be released from memory, avoiding 
     * memory allocation issues.
     * 
     * @see insert_item()
     * @see insert_files_for_item()
     * @param mixed $metadata Item metadata
     * @param mixed $elementTexts The item's element texts
     * @param mixed $fileMetadata The item's file metadata
     * @return true
     */
    final protected function insertItem($metadata = array(), $elementTexts = array(), $fileMetadata = array())
    {
        // Insert the item.
        $item = insert_item($metadata, $elementTexts);
        
        // Insert the record after the item is saved. The idea here is that the 
        // OaipmhHarvester_Records table should only contain records that have 
        // corresponding items.
        $this->_insertRecord($item);
        
        // If there are files, insert one file at a time so the file objects can 
        // be released individually.
        if (isset($fileMetadata['files'])) {
            
            // The default file transfer type is URL.
            $fileTransferType = isset($fileMetadata['file_transfer_type']) 
                              ? $fileMetadata['file_transfer_type'] 
                              : 'Url';
            
            // The default option is ignore invalid files.
            $fileOptions = isset($fileMetadata['file_ingest_options']) 
                         ? $fileMetadata['file_ingest_options'] 
                         : array('ignore_invalid_files' => true);
            
            // Prepare the files value for one-file-at-a-time iteration.
            $files = array($fileMetadata['files']);
            
            foreach ($files as $file) {
                $file = insert_files_for_item($item, $fileTransferType, $file, $fileOptions);
                // Release the File object from memory. 
                release_object($file);
            }
        }
        
        // Release the Item object from memory.
        release_object($item);
        
        return true;
    }
    
    /**
     * Convenience method for inserting an item and its files.
     * 
     * Method used by map writers that encapsulates item and file insertion. 
     * Items are inserted first, then files are inserted individually. This is 
     * done so Item and File objects can be released from memory, avoiding 
     * memory allocation issues.
     * 
     * @see insert_item()
     * @see insert_files_for_item()
     * @param OaipmhHarvester_Record $itemId ID of item to update
     * @param mixed $elementTexts The item's element texts
     * @param mixed $fileMetadata The item's file metadata
     * @return true
     */
    final protected function updateItem($record, $elementTexts = array(), $fileMetadata = array())
    {
        // Update the item
        $item = update_item($record->item_id, array('overwriteElementTexts' => true), $elementTexts, $fileMetadata);
        
        // Update the datestamp stored in the database for this record.
        $this->_updateRecord($record);

        // Release the Item object from memory.
        release_object($item);
        
        return true;
    }
    
    /**
     * Adds a status message to the harvest.
     * 
     * @param string $message The error message
     * @param int|null $messageCode The message code
     * @param string $delimiter The string dilimiting each status message
     */
    final protected function addStatusMessage($message, $messageCode = null, $delimiter = "\n\n")
    {
        $this->_harvest->addStatusMessage($message, $messageCode, $delimiter);
    }
    
    /**
     * Return this instance's OaipmhHarvester_Harvest object.
     * 
     * @return OaipmhHarvester_Harvest
     */
    final protected function getHarvest()
    {
        return $this->_harvest;
    }
    
    /**
     * Convenience method that facilitates the building of a correctly formatted 
     * elementTexts array.
     * 
     * @see insert_item()
     * @param array $elementTexts The previously build elementTexts array
     * @param string $elementSet This element's element set
     * @param string $element This element text's element
     * @param mixed $text The text
     * @param bool $html Flag whether this element text is HTML
     * @return array
     */
    final protected function buildElementTexts(array $elementTexts = array(), $elementSet, $element, $text, $html = false)
    {
        $elementTexts[$elementSet][$element][] = array('text' => (string) $text, 'html' => (bool) $html);
        return $elementTexts;
    }
    
    /**
     * Error handler callback.
     * 
     * @see self::__construct()
     */
    public function errorHandler($errno, $errstr, $errfile, $errline)
    {
        if (error_reporting() & $errno) {
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        }
        
        $statusMessage = "$errstr in $errfile on line $errline";
        $this->addStatusMessage($statusMessage, self::MESSAGE_CODE_ERROR);
        return true;
    }

    /**
     * Harvest records from the OAI-PMH repository.
     */
    final public function harvest()
    {
        try {
            $this->_harvest->status = 
                OaipmhHarvester_Harvest::STATUS_IN_PROGRESS;
            $this->_harvest->save();
        
            $this->beforeHarvest();
            // This method does most of the actual work.
            $resumptionToken = $this->_harvestRecords();

            // A return value of true just indicates success, all other values
            // must be valid resumption tokens.
            if ($resumptionToken === true) {
                $this->afterHarvest();
                $this->_harvest->status = 
                    OaipmhHarvester_Harvest::STATUS_COMPLETED;
                $this->_harvest->completed = $this->_getCurrentDateTime();
                $this->_harvest->resumption_token = null;
            } else {
                $this->_harvest->resumption_token = $resumptionToken;
                $this->_harvest->status =
                    OaipmhHarvester_Harvest::STATUS_QUEUED;
            }
        
            $this->_harvest->forceSave();
        
        } catch (Exception $e) {
            // Record the error.
            $this->addStatusMessage($e->getMessage(), self::MESSAGE_CODE_ERROR);
            $this->_harvest->status = OaipmhHarvester_Harvest::STATUS_ERROR;
            // Reset the harvest start_from time if an error occurs during 
            // processing. Since there's no way to know exactly when the 
            // error occured, re-harvests need to start from the beginning.
            $this->_harvest->start_from = null;
            $this->_harvest->forceSave();
        }
    
        $peakUsage = memory_get_peak_usage();
        $this->addStatusMessage("Peak memory usage: $peakUsage", self::MESSAGE_CODE_NOTICE);
    }

    public static function factory($harvest)
    {
        $classSuffix = Inflector::camelize($harvest->metadata_prefix);
        $class = 'OaipmhHarvester_Harvest_' . $classSuffix;
        require_once OAIPMH_HARVESTER_MAPS_DIRECTORY . "/$classSuffix.php";

        // Set the harvest object.
        $harvester = new $class($harvest);
        return $harvester;
    }
}
