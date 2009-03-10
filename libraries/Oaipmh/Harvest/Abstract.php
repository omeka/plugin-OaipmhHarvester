<?php
abstract class Oaipmh_Harvest_Abstract
{
    const MESSAGE_CODE_NOTICE = 1;
    const MESSAGE_CODE_ERROR = 2;
    
    private $_harvest;
    
    private $_releaseObjects;
    
    // The current, cached Oaipmh_Xml object.
    private $_oaipmhXml;
    
    // The current, cached SimpleXML record object.
    private $_record;
    
    public function __construct(OaipmhHarvesterHarvest $harvest, $releaseObjects = true)
    {        
        // Set an error handler method to record run-time warnings (non-fatal 
        // errors). Fatal and parse errors cannot be called in this way.
        set_error_handler(array($this, 'errorHandler'), E_WARNING);
        
        $this->_harvest = $harvest;
        
        $this->_releaseObjects = $releaseObjects;
        
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
            $this->_harvest->save();
            
        } catch (Exception $e) {
            // Record the error.
            $this->addStatusMessage($e->getMessage(), self::MESSAGE_CODE_ERROR);
            $this->_harvest->status = OaipmhHarvesterHarvest::STATUS_ERROR;
            $this->_harvest->save();
        }
        
        $peakUsage = memory_get_peak_usage();
        $this->addStatusMessage("Peak memory usage: $peakUsage", self::MESSAGE_CODE_NOTICE);
    }
    
    abstract protected function harvestRecord($record);
    
    private function _harvestRecords($resumptionToken = false)
    {
        
        // Get the base URL.
        $baseUrl = $this->_harvest->base_url;
        
        // Set the request arguments.
        $requestArguments = array('verb' => 'ListRecords');
        if ($resumptionToken) {
            // Harvest a list reissue. 
            $requestArguments['resumptionToken'] = $resumptionToken;
        } else if ($this->_harvest->set_spec) {
            // Harvest a set.
            $requestArguments['set']            = $this->_harvest->set_spec;
            $requestArguments['metadataPrefix'] = $this->_harvest->metadata_prefix;
        } else {
            // Harvest a repository.
            $requestArguments['metadataPrefix'] = $this->_harvest->metadata_prefix;
        }
        
        // Cache the Oaipmh_Xml object.
        $this->_oaipmhXml = new Oaipmh_Xml($baseUrl, $requestArguments);
        
        // Throw an error if the response is an error.
        if ($this->_oaipmhXml->isError()) {
            $errorCode = (string) $this->_oaipmhXml->getErrorCode();
            $error     = (string) $this->_oaipmhXml->getError();
            $statusMessage = "$errorCode: $error";
            throw new Exception($statusMessage);
        }

        // Iterate through the records and hand off the mapping to the classes 
        // inheriting from this class.
        foreach ($this->_oaipmhXml->getRecords() as $record) {
            // Cache the record for later use.
            $this->_record = $record;
            $this->harvestRecord($record);
        }
        
        // If there is a resumption token, recurse this method.
        if ($resumptionToken = $this->_oaipmhXml->getResumptionToken()) {
            $this->_harvestRecords($resumptionToken);
        }
        
        // If there is no resumption token, we're all done here.
        return;
    }
    
    private function _insertRecord($item)
    {
        $record = new OaipmhHarvesterRecord;
        
        $record->harvest_id = $this->_harvest->id;
        $record->item_id    = $item->id;
        $record->identifier = (string) $this->_record->header->identifier;
        $record->datestamp  = (string) $this->_record->header->datestamp;
        $record->save();
    }
    
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
    
    private function _getCurrentDateTime()
    {
        return date('Y-m-d H:i:s');
    }
    
    private function _releaseObject($obj)
    {
        if ($this->_releaseObjects) {
            release_object($obj);
            return true;
        }
        return false;
    }
    
    protected function beforeHarvest()
    {
    }
    
    protected function afterHarvest()
    {
    }
    
    // Insert a collection.
    final protected function insertCollection($metadata = array())
    {
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
        
        return $collection;
    }
    
    // Insert an item.
    final protected function insertItem($metadata = array(), $elementTexts = array(), $fileMetadata = array())
    {
        // Insert the item.
        $item = insert_item($metadata, $elementTexts);
        
        // Insert the record after the item is saved. The idea here is that the 
        // OaipmhHarvesterRecords table should only contain records that have 
        // corresponding items.
        $this->_insertRecord($item);
        
        // Insert the files, if any.
        // The default file transfer type is URL.
        $fileTransferType = isset($fileMetadata['file_transfer_type']) 
                          ? $fileMetadata['file_transfer_type'] 
                          : 'Url';
        // The default files are no files.
        $files = isset($fileMetadata['files']) 
               ? $fileMetadata['files'] 
               : array();
        // The default option is ignore invalid files.
        $fileOptions = isset($fileMetadata['file_ingest_options']) 
                     ? $fileMetadata['file_ingest_options'] 
                     : array('ignore_invalid_files' => true);
        // Insert one file at a time so that it can be released individually.
        foreach ($files as $file) {
            $file = insert_files_for_item($item, $fileTransferType, $file, $fileOptions);
            // Release the File object from memory if indicated to do so. 
            $this->_releaseObject($file);
        }
        
        // Release the Item object from memory if indicated to do so. Return 
        // true instead of the item object.
        if ($this->_releaseObject($item)) {
            return true;
        }
        
        return $item;
    }
    
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
    
    final protected function getHarvest()
    {
        return $this->_harvest;
    }
    
    public function errorHandler($errno, $errstr, $errfile, $errline)
    {
        $statusMessage = "$errstr in $errfile on line $errline";
        $this->addStatusMessage($statusMessage, self::MESSAGE_CODE_ERROR);
        return true;
    }
}