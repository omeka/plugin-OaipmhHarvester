<?php
abstract class Oaipmh_Harvest_Abstract
{
    protected $db;
    protected $options;
    protected $set;
    protected $oaipmh;
    
    public function __construct($db, $options, $set)
    {
        $this->db      = $db;
        $this->options = $options;
        $this->set     = $set;
        
        try {
            
            // Call the template method that runs before the harvest.
            $this->_beforeHarvest();
            // Initiate the harvest.
            $this->_harvest();
            // Call the template method that runs after the harvest.
            $this->_afterHarvest();
            
            // Set the set as completed.
            $this->set->status = OaipmhHarvesterSet::STATUS_COMPLETED;
            $this->set->completed = date('Y:m:d H:i:s');
            $this->set->save();
        
        } catch (Exception $e) {
            // Record the error.
            $this->set->status = OaipmhHarvesterSet::STATUS_ERROR;
            $this->set->status_messages = $this->_appendToStatusMessages($e->getMessage());
            $this->set->save();
        }
    }
    
    abstract protected function _harvestPage();
    
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
        $xml = new Oaipmh_Xml($baseUrl, $requestArguments);
        $this->oaipmh = $xml->getOaipmh();
        
        // Throw an error if the response is an error.
        if ($this->_isError($this->oaipmh)) {
            throw new Exception((string) $this->oaipmh->error);
        }

        // Hand off the page-by-page mapping to the classes inheriting from this 
        // class.
        $this->_harvestPage();
        
        // If there is a resumption token, recurse this method.
        if (isset($this->oaipmh->ListRecords->resumptionToken)) {
            $resumptionToken = (string) $this->oaipmh->ListRecords->resumptionToken;
            if (!empty($resumptionToken)) {
                $this->_harvest($resumptionToken);
            }
        }
        
        // If there is no resumption token, we're all done here.
        return;
    }
    
    // Insert a collection.
    protected function _insertCollection()
    {
        $collection = new Collection;
        $collection->name = $this->set->set_name;
        $collection->description = $this->set->set_description;
        $collection->save();
        return $collection->id;
    }
    
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
    
    protected function _appendToStatusMessages($message, $appendWith = "\n\n")
    {
        if (0 == strlen($this->set->status_messages)) {
            $appendWith = '';
        }
        
        return $this->set->status_messages . $appendWith . $message;
    }
}