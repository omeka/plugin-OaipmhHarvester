<?php
abstract class Oaipmh_Harvest_Abstract
{
    protected $db;
    protected $set;
    protected $statusMessages = array();
    
    // The current, cached OAI-PMH SimpleXML object.
    protected $oaipmh;
    
    public function __construct($db, $set)
    {
        $this->db  = $db;
        $this->set = $set;
        
        try {
            
            // Call the template method that runs before the harvest.
            $this->_beforeHarvest();
            // Initiate the harvest.
            $this->_harvest();
            // Call the template method that runs after the harvest.
            $this->_afterHarvest();
            
            // Mark the set as completed.
            $this->set->status = OaipmhHarvesterSet::STATUS_COMPLETED;
            $this->set->status_messages = $this->_formatStatusMessages();
            $this->set->completed = date('Y:m:d H:i:s');
            $this->set->save();
        
        } catch (Exception $e) {
            // Record the error.
            $this->set->status = OaipmhHarvesterSet::STATUS_ERROR;
            $this->set->status_messages = $this->_formatStatusMessages();
            $this->set->save();
        }
    }
    
    abstract protected function _harvestRecord($record);
    
    protected function _beforeHarvest()
    {
    }
    
    protected function _afterHarvest()
    {
    }
    
    private function _harvest($resumptionToken = false)
    {
        
        // Get the base URL.
        $baseUrl = $this->set->base_url;
        
        // Set the request arguments.
        $requestArguments = array('verb' => 'ListRecords');
        if ($resumptionToken) {
            $requestArguments['resumptionToken'] = $resumptionToken;
        } else {
            $requestArguments['set']            = $this->set->set_spec;
            $requestArguments['metadataPrefix'] = $this->set->metadata_prefix;
        }
        
        // Cache the OAI-PMH SimpleXML object.
        $oaipmhXml = new Oaipmh_Xml($baseUrl, $requestArguments);
        $this->oaipmh = $oaipmhXml->getOaipmh();
        
        // Throw an error if the response is an error.
        if ($this->_isError($this->oaipmh)) {
            $this->_addStatusMessage((string) $this->oaipmh->error);
            throw new Exception;
        }

        // Iterate through the records and hand off the mapping to the classes 
        // inheriting from this class.
        foreach ($this->_getRecords() as $record) {
            $this->_harvestRecord($record);
        }
        
        // If there is a resumption token, recurse this method.
        if ($resumptionToken = $this->_getResumptionToken()) {
            $this->_harvest($resumptionToken);
        }
        
        // If there is no resumption token, we're all done here.
        return;
    }
    
    // Insert a collection.
    protected function _insertCollection()
    {
        
    }
    
    // Insert an item.
    protected function _insertItem()
    {
        
    }
    
    protected function _getRecords()
    {
        return $this->oaipmh->ListRecords->record;
    }
    
    protected function _isError($oaipmh)
    {
        return isset($oaipmh->error);
    }
    
    protected function _addStatusMessage($message)
    {
        $this->statusMessages[] = $message;
    }
    
    protected function _formatStatusMessages($delimiter = "\n\n")
    {
        return implode($delimiter, $this->statusMessages);
    }
    
    protected function _getResumptionToken()
    {
        if (isset($this->oaipmh->ListRecords->resumptionToken)) {
            $resumptionToken = (string) $this->oaipmh->ListRecords->resumptionToken;
            if (!empty($resumptionToken)) {
                return $resumptionToken;
            }
        }
        return false;
    }
}