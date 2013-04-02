<?php

require_once 'models/OaipmhHarvester/Request.php';

class OaipmhHarvester_RequestTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->request = new OaipmhHarvester_Request();
        $this->client = new Zend_Http_Client();
        $this->client->setAdapter('Zend_Http_Client_Adapter_Test');
        $this->request->setClient($this->client);
        $this->_okHeader = file_get_contents(
            dirname(__FILE__) . '/_files/responseheader.txt'
        );
    }

    public function testRequestInvalidXml()
    {
        $this->_setXml('<foo/bar>');
        $this->request->setBaseUrl('http://www.example.com');
        try {
            $resp = $this->request->listMetadataFormats();
            $this->fail();
        } catch (Zend_Http_Client_Exception $e) {
            $this->assertContains(
                "error parsing attribute name", 
                $e->getMessage()
            );
        }
    }  

    public function testListMetadataFormats()
    {
        $this->_setXml(file_get_contents(
            dirname(__FILE__) . '/_files/ListMetadataFormats.xml'
        ));        
        $this->request->setBaseUrl('http://www.example.com');
        $formats = $this->request->listMetadataFormats();
        $this->assertEquals(
            array (
              "oai_dc" => "http://www.openarchives.org/OAI/2.0/oai_dc.xsd",
              "uketd_dc" => "http://naca.central.cranfield.ac.uk/ethos-oai/2.0/uketd_dc.xsd",
              "didl" => "http://standards.iso.org/ittf/PubliclyAvailableStandards/MPEG-21_schema_files/did/didmodel.xsd",
            ),
            $formats
        );
    }

    public function testListRecords()
    {
        $this->_setXml(file_get_contents(
            dirname(__FILE__) . '/_files/ListRecords.xml'
        ));    
        $this->request->setBaseUrl('http://www.example.com');
        $resp = $this->request->listRecords();
        $this->assertTrue(array_key_exists('records', $resp), 
            "Records must be available in the response.");
        $this->assertTrue(array_key_exists('resumptionToken', $resp),
            "Resumption token should be available in the response.");
    }

    public function testListSets()
    {
        $this->_setXml(file_get_contents(
            dirname(__FILE__) . '/_files/ListSets.xml'
        ));    
        $this->request->setBaseUrl('http://www.example.com');
        $resp = $this->request->listSets();
        $this->assertEquals(4, count($resp['sets']));
    }

    private function _setXml($str)
    {
        $this->client->getAdapter()->setResponse($this->_okHeader . $str);        
    }
}