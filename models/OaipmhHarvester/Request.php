<?php

class OaipmhHarvester_Request
{
    private $_baseUrl;

    public function __construct($baseUrl) 
    {
        $this->_baseUrl = $baseUrl;
    }

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
        if (empty($formats)) {
            $formats[OaipmhHarvester_Harvest_OaiDc::METADATA_PREFIX] =
                OaipmhHarvester_Harvest_OaiDc::METADATA_SCHEMA;
        }
        return $formats;
    }

    public function listRecords()
    {

    }

    public function listSets()
    {

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
            return new SimpleXMLIterator($response->getBody());
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
