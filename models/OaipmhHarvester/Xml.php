<?php
/**
 * @package OaipmhHarvester
 * @subpackage Libraries
 * @copyright Copyright (c) 2009 Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */


/**
 * Utility class for requesting, parsing, and accessing an OAI-PMH response.
 * 
 * OaipmhHarvester_Xml represents an instance of an OAI-PMH response, containing 
 * a SimpleXMLIterator object and other utility methods.
 *
 * @package OaipmhHarvester
 * @subpackage Libraries
 */
class OaipmhHarvester_Xml
{
    /**
     * OAI-PMH error code for a repository with no set hierarchy
     */
    const ERROR_CODE_NO_SET_HIERARCHY = 'noSetHierarchy';
    
    /**
     * @var SimpleXMLIterator The OAI-PMH object
     */
    private $oaipmh;

    private $_baseUrl;
    private $_requestArguments = array();

    /**
     * Class constructor
     * 
     * Requests the OAI-PMH repository and sets the SimpleXMLIterator object.
     * 
     * @param string $baseUrl The base URL of the repository
     * @param array $requestArguments The array containing the request arguments
     * @return void
     */
    public function __construct($baseUrl, array $requestArguments)
    {
        $this->_baseUrl = $baseUrl;
        $this->_requestArguments = $requestArguments;
    }
    
    /**
     * Build the request query
     *
     * Repositories must support both the GET and POST methods. Here we are 
     * requesting via GET.
     * 
     * @param string $baseUrl The base URL of the repository
     * @param array $requestArguments The array containing the request arguments
     * @return string The full request URL, including valid query string
     */
    private function getRequestUrl($baseUrl, $requestArguments)
    {
        return $baseUrl . '?' . http_build_query($requestArguments);
    }
    
    /**
     * Return the OAI-PMH SimpleXMLIterator object
     * 
     * @return SimpleXMLIterator
     */
    public function getOaipmh()
    {
        if (!$this->oaipmh) {
            $this->oaipmh = $this->_request();
        }
        return $this->oaipmh;
    }
    
    /**
     * Return whether the response is an error
     * 
     * @return bool
     */
    public function isError()
    {
        return (boolean)$this->getError();
    }
    
    /**
     * Return the response error
     * 
     * @return SimpleXMLIterator
     */
    public function getError()
    {
        return $this->getOaipmh()->error;
    }
    
    /**
     * Return the response error code
     * 
     * @return string
     */
    public function getErrorCode()
    {
        return $this->getError()->attributes()->code;
    }
    
    /**
     * Get the response records
     * 
     * @return SimpleXMLIterator
     */
    public function getRecords()
    {
        return $this->getOaipmh()->ListRecords->record;
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
     * Return the response resumption token, if any
     * 
     * @return string|false
     */
    public function getResumptionToken()
    {
        if (isset($this->getOaipmh()->ListRecords->resumptionToken)) {
            $resumptionToken = (string) $this->getOaipmh()->ListRecords->resumptionToken;
            if (!empty($resumptionToken)) {
                return $resumptionToken;
            }
        }
        return false;
    }

    private function _request()
    {
        $userAgent = 'Omeka OAI-PMH Harvester/' . OAIPMH_HARVESTER_PLUGIN_VERSION; 
        $requestUrl = $this->getRequestUrl($this->_baseUrl, $this->_requestArguments);
        $client = new Zend_Http_Client($requestUrl, array(
                'useragent' => $userAgent));
        $response = $client->request('GET');
        if ($response->isSuccessful() && !$response->isRedirect()) {
            return new SimpleXMLIterator($response->getBody());
        } else {
            throw new Zend_Http_Client_Exception("Invalid URL (" 
                . $response->getStatus() . " " . $response->getMessage() 
                . ").");
        }
    }
}
