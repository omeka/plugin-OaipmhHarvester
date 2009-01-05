<?php
abstract class Oaipmh_Harvest_Abstract
{
	protected $db;
	protected $options;
	protected $set;
	
	public function __construct($db, $options, $set)
	{
		$this->db	   = $db;
		$this->options = $options;
		$this->set 	   = $set;
		
		try {
			$this->_beforeHarvest();
			$this->_harvest();
			$this->_afterHarvest();
		} catch (Exception $e) {
			echo $e->getMessage();
		}
	}
	
	abstract protected function _harvestPage($oaipmh);

	protected function _beforeHarvest() {}
	protected function _afterHarvest() {}
	
	private function _harvest($resumptionToken = false)
	{

		// Get the base URL.
		$baseUrl = $this->set->base_url;
		
		// Set the request arguments.
		$requestArguments = array('verb' => 'ListRecords');
		if ($resumptionToken) {
			$requestArguments['resumptionToken'] = $resumptionToken;
		} else {
			$requestArguments['set']			= $this->set->set_spec;
			$requestArguments['metadataPrefix'] = 'cdwalite';//$this->set->metadata_prefix;
		}

// Debugging
//echo '+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=';
//echo PHP_EOL;
//echo $baseUrl;
//echo PHP_EOL;
//print_r($requestArguments);
//echo PHP_EOL;

		// Set the OAI-PMH object.
		$oaipmh = new Oaipmh_Xml($baseUrl, $requestArguments);
		
		// Hand off the mapping to the classes inheriting from this class.
		$this->_harvestPage($oaipmh);
		
		// If there is a resumption token, recurse this method.
		if (isset($oaipmh->getOaipmh()->ListRecords->resumptionToken)) {
			
// Debugging
//echo $oaipmh->getOaipmh()->ListRecords->resumptionToken->asXml();
//echo PHP_EOL;
			
			$resumptionToken = (string) $oaipmh->getOaipmh()->ListRecords->resumptionToken;
			if (!empty($resumptionToken)) {
				$this->_harvest($resumptionToken);
			}
		}
		
// Debugging
//echo 'return';
//echo PHP_EOL;
		
		// If there is no resumption token, we're all done here.
		return;
	}
	
	// Insert a collection.
	protected function _insertCollection()
	{
		$collection = new Collection;
		$collection->name = $this->set->set_name;
		$collection->description = $this->set->set_description;
		$collection->save();
		return $collection->id;
	}
	
	protected function _isError($oaipmh)
	{
		return isset($oaipmh->error);
	}
}