<?php
class Oaipmh_Harvest_Abstract_OaiDc extends Oaipmh_Harvest_Abstract
{
	// Mapping goes here, per page.
	protected function _harvestPage($oaipmh)
	{
		foreach ($oaipmh->getOaipmh()->ListRecords->record as $record) {
			//echo $record->header->identifier;
			//echo PHP_EOL;
		}
	}
}