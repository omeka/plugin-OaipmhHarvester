<?php
class Oaipmh_Xml
{
    const ERROR_CODE_NO_SET_HIERARCHY = 'noSetHierarchy';
    
    private $oaipmh;
    
    public function __construct($baseUrl, array $requestArguments)
    {
        $requestUrl = $this->getRequestUrl($baseUrl, $requestArguments);
        $requestContent = file_get_contents($requestUrl);
        $this->oaipmh = new SimpleXMLIterator($requestContent);
    }
    
    // Repositories must support both the GET and POST methods. Here we are 
    // requesting via GET.
    private function getRequestUrl($baseUrl, $requestArguments)
    {
        return $baseUrl . '?' . http_build_query($requestArguments);
    }
    
    public function getOaipmh()
    {
        return $this->oaipmh;
    }
    
    public function isError()
    {
        return isset($this->oaipmh->error);
    }
    
    public function getError()
    {
        return $this->oaipmh->error;
    }
    
    public function getErrorCode()
    {
        return $this->oaipmh->error->attributes()->code;
    }
    
    public function getRecords()
    {
        return $this->oaipmh->ListRecords->record;
    }
    
    public function isDeletedRecord($record)
    {
        if (isset($record->header->attributes()->status) 
            && $record->header->attributes()->status == 'deleted') {
            return true;
        }
        return false;
    }
    
    public function getResumptionToken()
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