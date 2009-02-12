<?php
class Oaipmh_Xml
{
    private $oaipmh;
    
    public function __construct($baseUrl, array $requestArguments)
    {
        $requestUrl = $this->getRequestUrl($baseUrl, $requestArguments);
        $this->oaipmh = new SimpleXMLIterator($requestUrl, null, true);
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