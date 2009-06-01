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
     * @var OaipmhHarvesterHarvest The OaipmhHarvesterHarvest object model.
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
     * @param OaipmhHarvesterHarvest $harvest The OaipmhHarvesterHarvest object 
     * model
     * @param array $options Options used to configure behavior. These include: 
     *  - ignore_deleted_records: ignores records with a status of deleted
     * @return void
     */
    public function __construct($harvest, $options = array())
    {   
        if($harvest && $options) {     
            // Set an error handler method to record run-time warnings (non-fatal 
            // errors). Fatal and parse errors cannot be called in this way.
            set_error_handler(array($this, 'errorHandler'), E_WARNING);
            
            $this->_harvest = $harvest;
        
            $this->_setOptions($options);
        
            try {
                // Mark the harvest as in progress.
                $this->_harvest->status = OaipmhHarvesterHarvest::STATUS_IN_PROGRESS;
                $this->_harvest->save();
            
                // Call the template method that runs before the harvest.
                $this->beforeHarvest();
                // Initiate the harvest.
                $this->_harvestRecords();
                // Call the template method that runs after the harvest.
                $this->afterHarvest();
            
                // Mark the set as completed.
                $this->_harvest->status    = OaipmhHarvesterHarvest::STATUS_COMPLETED;
                $this->_harvest->completed = $this->_getCurrentDateTime();
                $this->_harvest->pid = null;
                $this->_harvest->save();
            
            } catch (Exception $e) {
                // Record the error.
                $this->addStatusMessage($e->getMessage(), self::MESSAGE_CODE_ERROR);
                $this->_harvest->status = OaipmhHarvesterHarvest::STATUS_ERROR;
                $this->_harvest->pid = null;
                $this->_harvest->save();
            }
        
            $peakUsage = memory_get_peak_usage();
            $this->addStatusMessage("Peak memory usage: $peakUsage", self::MESSAGE_CODE_NOTICE);
        }
    }
    
    /**
     * Abstract method that all class extentions must contain.
     * 
     * @param SimpleXMLIterator The current record object
     */
    abstract protected function harvestRecord($record);
    
    /**
     * Sets class options
     * 
     * @param array $options
     * @return void
     */
    private function _setOptions($options)
    {
        // Placeholder for class options that will potentially be added.
    }
    
    /**
     * Checks whether the current record has already been harvested, and
     * returns the record if it does.
     *
     * @param SimpleXMLIterator record to be harvested
     * @return OaipmhHarvesterRecord|false The model object of the record,
     *      if it exists, or false otherwise.
     */
    private function _recordExists($record)
    {   
        $existing = false;
        
        $identifier = $record->header->identifier;
        $datestamp = $record->header->datestamp;
        
        $records = get_db()->getTable('OaipmhHarvesterRecord')->findByOaiIdentifier($identifier);
        
        /* Ideally, the OAI identifier would be globally-unique, but for
           poorly configured servers that might not be the case.  However,
           the identifier is always unique for that repository, so given
           already-existing identifiers, check against the base URL.
        */
        foreach($records as $existingRecord)
        {
            $harvest_id = $existingRecord->harvest_id;
            $recordHarvest = get_db()->getTable('OaipmhHarvesterHarvest')->find($harvest_id);
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
     * Converts the given MySQL datetime to an OAI datestamp, for
     * sending dates in OAI-PMH requests.
     *
     * @param string $datestamp MySQL datetime
     * @return string OAI-PMH datestamp
     */
    private function _datetimeToOai($datestamp)
    {
        return gmdate(self::OAI_DATE_FORMAT, strtotime($datestamp));
    }
    
    /**
     * Recursive method that loops through all requested records
     * 
     * This method hands off mapping to the class that extends off this one and 
     * recurses through all resumption tokens until the response is completed.
     * 
     * @param string|false $resumptionToken
     * @return void
     */
    private function _harvestRecords($resumptionToken = false)
    {
        // Get the base URL.
        $baseUrl = $this->_harvest->base_url;
        
        // Set the request arguments.
        $requestArguments = array('verb' => 'ListRecords');
        if ($resumptionToken) {
            // Harvest a list reissue. 
            $requestArguments['resumptionToken'] = $resumptionToken;
        } 
        else {
            if ($this->_harvest->set_spec) {
                // Harvest a set.
                $requestArguments['set']            = $this->_harvest->set_spec;
                $requestArguments['metadataPrefix'] = $this->_harvest->metadata_prefix;
            } 
            else {
                // Harvest a repository.
                $requestArguments['metadataPrefix'] = $this->_harvest->metadata_prefix;
            }

            // Perform date-selective harvesting if a "from" date is
            // specified.
            if(($startFrom = $this->_harvest->start_from)) {
                $oaiDate = $this->_datetimeToOai($startFrom);
                $requestArguments['from'] = $oaiDate;
                $this->addStatusMessage("Resuming harvest from $oaiDate.", self::MESSAGE_CODE_NOTICE);
            }
        }
        
        // Cache the OaipmhHarvester_Xml object.
        $this->_oaipmhHarvesterXml = new OaipmhHarvester_Xml($baseUrl, $requestArguments);
        
        // Throw an error if the response is an error.
        if ($this->_oaipmhHarvesterXml->isError()) {
            $errorCode = (string) $this->_oaipmhHarvesterXml->getErrorCode();
            $error     = (string) $this->_oaipmhHarvesterXml->getError();
            
            // Especially with selective harvesting, no records is not
            // necessarily an error.  Print a notice and exit.
            if($errorCode = 'noRecordsMatch') {
                $this->addStatusMessage("The repository returned no records.", self::MESSAGE_CODE_NOTICE);
                return;
            }
            else {
                $statusMessage = "$errorCode: $error";
                throw new Exception($statusMessage);
            }
        }

        // Iterate through the records and hand off the mapping to the classes 
        // inheriting from this class.
        foreach ($this->_oaipmhHarvesterXml->getRecords() as $record) {
            
            // Ignore (skip over) deleted records.
            if ($this->_oaipmhHarvesterXml->isDeletedRecord($record)) {
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
        
        // If there is a resumption token, recurse this method.
        if ($resumptionToken = $this->_oaipmhHarvesterXml->getResumptionToken()) {
            $this->_harvestRecords($resumptionToken);
        }
        
        // If there is no resumption token, we're all done here.
        return;
    }
    
    /**
     * Insert a record into the database.
     * 
     * @param Item $item The item object corresponding to the record.
     * @return void
     */
    private function _insertRecord($item)
    {
        $record = new OaipmhHarvesterRecord;
        
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
     * @param OaipmhHarvesterRecord The model object corresponding to the record.
     */
    private function _updateRecord(OaipmhHarvesterRecord $record)
    {   
        $record->datestamp  = (string) $this->_record->header->datestamp;
        $record->save();
    }
    
    /**
     * Return a message code text corresponding to its constant.
     * 
     * @param int $messageCode
     * @return string
     */
    private function _getMessageCodeText($messageCode)
    {
        switch ($messageCode) {
            case self::MESSAGE_CODE_ERROR:
                $messageCodeText = 'Error';
                break;
            case self::MESSAGE_CODE_NOTICE:
            default:
                $messageCodeText = 'Notice';
                break;
        }
        return $messageCodeText;
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
        // OaipmhHarvesterRecords table should only contain records that have 
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
     * @param OaipmhHarvesterRecord $itemId ID of item to update
     * @param mixed $elementTexts The item's element texts
     * @param mixed $fileMetadata The item's file metadata
     * @return true
     */
    final protected function updateItem($record, $elementTexts = array(), $fileMetadata = array())
    {
        // Update the item
        $item = update_item($record->item_id, array('overwriteElementTexts' => true), $elementTexts);
        
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
        if (0 == strlen($this->_harvest->status_messages)) {
            $delimiter = '';
        }
        $date = $this->_getCurrentDateTime();
        $messageCodeText = $this->_getMessageCodeText($messageCode);
        
        $this->_harvest->status_messages = "{$this->_harvest->status_messages}$delimiter$messageCodeText: $message ($date)";
        $this->_harvest->save();
    }
    
    /**
     * Return this instance's OaipmhHarvesterHarvest object.
     * 
     * @return OaipmhHarvesterHarvest
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
        $statusMessage = "$errstr in $errfile on line $errline";
        $this->addStatusMessage($statusMessage, self::MESSAGE_CODE_ERROR);
        return true;
    }

	/**
	 * Returns the metadataPrefix for the format the mapper supports.
	 *
	 * @return string metadataPrefix
	 */
	public abstract function getMetadataPrefix();
	
	/**
	 * Returns the XML schema for the format the mapper supports.
	 *
	 * @return string XML schema
	 */
	public abstract function getMetadataSchema();
}