<?php
abstract class OaipmhHarvester_Harvest
{
	private $db;
	private $options;
	private $set;
	private $oaipmh;
	
	public function __construct()
	{
		$this->_require();
		$this->_load();
		
		$this->_setDb();
		$this->_setOptions();
		$this->_setSet();
	}
	
	abstract protected function _harvest($oaipmh);
	
	// Require the necessary files. There is probably a better way to do this.
	private function _require()
	{
		require '../../../../paths.php';
		require '../../../../application/libraries/Omeka/Core.php';
 	}
 	
	// Load only the required core phases.
 	private function _load()
 	{
		$core = new Omeka_Core;
		$core->phasedLoading('initializePluginBroker');
	}
	
	// Get the database object.
	private function _setDb()
	{
    	$this->db = get_db();
	}

	// Set the command line arguments.
	private function _setOptions()
	{
		$this->options = getopt('s:');
	}
	
	// Set the set ID.
	private function _setSet()
	{
		$setId = $this->options['s'];
    	$this->set = $this->db->getTable('OaipmhHarvesterSet')->find($setId);
	}
	
// For some reason there have been instances where a recursion starts from the 
// beginning and sets the request arguments to the first "page" of the request. 
// For example, I tested this on Getty's data provider and it iterated fine 
// until it got to "page" 4200 out of 4652 records, and instead of going to 
// "page" 4300 out of 4652 records, it started from the beginning, i.e. without 
// a resumption token. THIS IS NOT GOOD. either there needs to be protections to 
// prevent this from happening, or I need to rethink this entire code. I'm 
// thinking that SimpleXML did not detect the resumptionToken node, or this may 
// be an anomoly. Much testing needs to be done, given it appears that SimpleXML 
// and/or OAI-PMH data providers are unreliable.
//
// ** It did it again, on the same "page" too. So something's probably up with 
// Getty's provider, but it may still be a programmatic error. Keep testing.
//
// ** I checked several times and Getty does seem to be sending a resumption 
// token to that "page." This means there's probably something wrong with the 
// code below. Maybe the recursion isn't working properly? I wouldn't be 
// suprised.
//
// ** I added a check to see if the resumptionToken tag is empty and now the 
// script does not start over from the beginning, which is good. But now it 
// simply stops at the 4200 out of 4652 records "page." I am totally baffled why 
// it doesn't continue to the next pages. Weird. Again, it's either SimpleXML 
// screwing things up, or my recursion code below doesn't work.
//
// ** By the way, to recreate the bug use the OaipmhHarvester plugin to harvest 
// the "ggrltap" set from the base URL "http://oai.getty.edu/oaicat/OAIHandler".
// And be sure to overwrite the $requestArguments['metadataPrefix'] below to 
// cdwalite because the provider doesn't offer oai_dc, WHICH IT SHOULD. Then, on 
// the command line run the following command in the oai_dc directory: 
// $ php harvest.php -s 1
//
// ** It occured to me that this could be caused by Getty's provider only 
// allowing a certain number of requests in a certain amount of time, or a 
// certain amount of request tokens in a certain amount of time. This is 
// improbable, but something to investigate. OAI-PMH data providers are about 
// most unreliable services ever.
//
// ** Set "ggmp" consistently stops at 400 of 4652 records! What gives?
//
// ** I tried this on another data provider and it worked! Although, it only had 
// three "pages," so more testing needs to be done on sets with many "pages."
//
// ** At the point where the script stops, it detects an empty resumption token. 
// How can this be? When I use the same resumption token directly on the 
// provider, it responds with a "next page" resumption token. Weird.
//
// ** Iterating through the "pages" by hand (i.e. requesting each "page" 
// individually by hand) also results in an empty resumption token. BUT, after 
// waiting a while, the same resumption token results in a page with a valid 
// resumption token. Is this a timing issue? Does there need to be a pause 
// between the result and the request?
//
// I added a sleep() in between the response and request, but it still stopped 
// processing. So, this makes no sense. If done by hand, and after waiting a 
// bit, it works; but done programmatically, it doesn't.
	public function harvest($resumptionToken = false)
	{

		// Get the base URL.
		$baseUrl = $this->set->base_url;
		
		// Set the request arguments.
		$requestArguments = array('verb' => 'ListRecords');
		if ($resumptionToken) {
			$requestArguments['resumptionToken'] = $resumptionToken;
		} else {
			$requestArguments['set']			= $this->set->set_spec;
			$requestArguments['metadataPrefix'] = $this->set->metadata_prefix;
		}

// Debugging
//echo '+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=+=';
//echo PHP_EOL;
//echo $baseUrl;
//echo PHP_EOL;
//print_r($requestArguments);
//echo PHP_EOL;

		// Set the OAI-PMH object.
		$oaipmh = new OaipmhHarvester_Oaipmh($baseUrl, $requestArguments);
		
		// Hand off the mapping to the classes inheriting from this class.
		$this->_harvest($oaipmh);
		
		// If there is a resumption token, recurse this method.
		if (isset($oaipmh->getOaipmh()->ListRecords->resumptionToken)) {
			
// Debugging
//echo $oaipmh->getOaipmh()->ListRecords->resumptionToken->asXml();
//echo PHP_EOL;
			
			$resumptionToken = (string) $oaipmh->getOaipmh()->ListRecords->resumptionToken;
			if (!empty($resumptionToken)) {
				$this->harvest($resumptionToken);
			}
		}
		
// Debugging
//echo 'return';
//echo PHP_EOL;
		
		// If there is no resumption token, we're all done here.
		return;
	}
	
	// Insert a collection.
	public function insertCollection()
	{
		$collection = new Collection;
		$collection->name = $this->set->set_name;
		$collection->description = $this->set->set_description;
		$collection->save();
		return $collection->id;
	}
	
	public function isError($oaipmh)
	{
		return isset($oaipmh->error);
	}
	
	public function getSet()
	{
		return $this->set;
	}
	
	public function getOaipmh()
	{
		return $this->oaipmh;
	}
}