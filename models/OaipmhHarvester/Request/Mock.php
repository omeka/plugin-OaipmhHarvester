<?php

class OaipmhHarvester_Request_Mock extends OaipmhHarvester_Request
{
    private $_defaultHeader;

    /**
     * @param string|array $response
     */
    public function setResponse($response)
    {
        $client = $this->getClient();
        $client->getAdapter()->setResponse($response);
    }

    public function setResponseXml($xml)
    {
        if (!$this->_defaultHeader) {
        $this->_defaultHeader = 'HTTP/1.1 200 OK
Date: Mon, 1 Jun 2015 00:00:00 GMT
Server: Apache/2.4 (Debian)
Content-Type: text/xml;charset=UTF-8
Connection: close

';
        }
        $this->setResponse($this->_defaultHeader . $xml);
    }

    public function setClient(Zend_Http_Client $client = null)
    {
        $client = new Zend_Http_Client();
        $client->setAdapter('Zend_Http_Client_Adapter_Test');
        parent::setClient($client);
    }
}
