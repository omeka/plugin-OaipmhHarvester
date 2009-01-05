<?php
// Require the abstract harvest class, which loads the neccessary code and 
// provides a library of handy methods for harvesting.
require_once '../Harvest.php';

class OaipmhHarvester_Harvest_OaiDc extends OaipmhHarvester_Harvest
{
	private $collectionId;
	
	public function __construct()
	{
		parent::__construct();
	}
	
	// Mapping goes here, per page.
	protected function _harvest($oaipmh)
	{
		foreach ($oaipmh->getOaipmh()->ListRecords->record as $record) {
			//echo $record->header->identifier;
			//echo PHP_EOL;
		}
	}
}