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
            // Required end of line: https://www.ietf.org/rfc/rfc2616.txt
            $eol = "\r\n";
            $this->_defaultHeader = 'HTTP/1.1 200 OK' . $eol
                . 'Date: Mon, 1 Jun 2015 00:00:00 GMT' . $eol
                . 'Server: Apache/2.4 (Debian)' . $eol
                . 'Content-Type: text/xml;charset=UTF-8' .$eol
                . 'Connection: close' . $eol
                . $eol;
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
