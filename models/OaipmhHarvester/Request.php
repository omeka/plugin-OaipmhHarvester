<?php

class OaipmhHarvester_Request
{
    /**
     * OAI-PMH error code for a repository with no set hierarchy
     */
    const ERROR_CODE_NO_SET_HIERARCHY = 'noSetHierarchy';

    /**
     * @var string
     */
    private $_baseUrl;

    /**
     * Constructor.
     *
     * @param string $baseUrl
     */
    public function __construct($baseUrl) 
    {
        $this->_baseUrl = $baseUrl;
    }

    /**
     * List metadata response formats for the provider.
     *
     * @return array Keyed array, where key is the metadataPrefix and value
     * is the schema.
     */
    public function listMetadataFormats()
    {
        $xml = $this->_makeRequest(array(
            'verb' => 'ListMetadataFormats',
        ));
        $formats = array();
        foreach ($xml->ListMetadataFormats->metadataFormat as $format) {
            $prefix = trim((string)$format->metadataPrefix);
            $schema = trim((string)$format->schema);
            $formats[$prefix] = $schema;
        }
        /**
         * It's important to consider that some repositories don't provide 
         * repository
         *  -wide metadata formats. Instead they only provide record level metadata 
         *  formats. Oai_dc is mandatory for all records, so if a
         *  repository doesn't provide metadata formats using ListMetadataFormats, 
         *  only expose the oai_dc prefix. For a data provider that doesn't offer 
         *  repository-wide metadata formats, see: 
         *  http://www.informatik.uni-stuttgart.de/cgi-bin/OAI/OAI.pl
         */
        if (empty($formats)) {
            $formats[OaipmhHarvester_Harvest_OaiDc::METADATA_PREFIX] =
                OaipmhHarvester_Harvest_OaiDc::METADATA_SCHEMA;
        }
        return $formats;
    }

    /**
     * List all records for a given request.
     *
     * @param array $query Args may include: metadataPrefix, set, 
     * resumptionToken, from.
     */
    public function listRecords(array $query)
    {
        $query['verb'] = 'ListRecords';
        $xml = $this->_makeRequest($query);
        $response = array(
            'records' => $xml->ListRecords->record,
        );
        if ($error = $this->_getError($xml)) {
            $response['error'] = $error;
        }
        if ($token = $xml->ListRecords->resumptionToken) {
            $response['resumptionToken'] = (string)$token;
        }
        return $response;
    }

    /**
     * List all available sets from the provider.
     *
     * Resumption token can be given for incomplete lists.
     * 
     * @param string|null $resumptionToken
     */
    public function listSets($resumptionToken = null)
    {
        $query = array(
            'verb' => 'ListSets',
        );
        if ($resumptionToken) {
            $query['resumptionToken'] = $resumptionToken;
        }

        $retVal = array();
        try {
            $xml = $this->_makeRequest($query);
        
            // Handle returned errors, such as "noSetHierarchy". For a data 
            // provider that has no set hierarchy, see: 
            // http://solarphysics.livingreviews.org/register/oai
            if ($error = $this->_getError($xml)) {
                $retVal['error'] = $error;
                if ($error['code'] == 
                        OaipmhHarvester_Request::ERROR_CODE_NO_SET_HIERARCHY
                ) {
                    $sets = array();
                }
            } else {
                $sets = $xml->ListSets->set;
            }
            if (isset($xml->ListSets->resumptionToken)) {
                $retVal['resumptionToken'] = $xml->ListSets->resumptionToken;
            }
        } catch(Exception $e) {
            // If we're here, the provider didn't even respond with valid XML.
            // Try to continue with no sets.
            $sets = array();
        }

        $retVal['sets'] = $sets;
        return $retVal;
    }

    private function _getError($xml)
    {
        $error = array();
        if ($xml->error) {
            $error['message'] = (string)$xml->error;   
            $error['code'] = $xml->error->attributes()->code;
        }
        return $error;
    }

    private function _makeRequest(array $query)
    {
        $client = new Zend_Http_Client(
            $this->_baseUrl,
            array(
                'useragent' => $this->_getUserAgent(),
            )
        );
        $client->setParameterGet($query);
        $response = $client->request('GET');
        if ($response->isSuccessful() && !$response->isRedirect()) {
            try {
                return new SimpleXMLIterator($response->getBody());
            } catch (Exception $e) {
                _log(
                    "[OaipmhHarvester] Could not parse XML: " 
                    . $response->getBody()
                );
                throw new Zend_Http_Client_Exception(
                    "Error occurred in parsing the XML response: " 
                    . $e->getMessage()
                ); 
            }
        } else {
            throw new Zend_Http_Client_Exception("Invalid URL (" 
                . $response->getStatus() . " " . $response->getMessage() 
                . ").");
        }
    }

    private function _getUserAgent()
    {
        $userAgent = 'Omeka OAI-PMH Harvester/' . 
            get_plugin_ini('OaipmhHarvester', 'version');
        return $userAgent;
    }
}
