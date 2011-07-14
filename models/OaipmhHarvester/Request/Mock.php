<?php

class OaipmhHarvester_Request_Mock extends OaipmhHarvester_Request
{
    /**
     * @param string|array $response
     */
    public function setResponse($response)
    {
        $client = $this->getClient();
        $client->getAdapter()->setResponse($response);
    }

    public function setClient(Zend_Http_Client $client = null)
    {
        $client = new Zend_Http_Client();
        $client->setAdapter('Zend_Http_Client_Adapter_Test');
        parent::setClient($client);
    }
}
