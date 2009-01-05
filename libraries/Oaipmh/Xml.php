<?php
class Oaipmh_Xml
{
    private $oaipmh;
    
    public function __construct($baseUrl, array $requestArguments)
    {
        $requestUrl = $this->getRequestUrl($baseUrl, $requestArguments);
        $this->oaipmh = new SimpleXMLElement($requestUrl, null, true);
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
}