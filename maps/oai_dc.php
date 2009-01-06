<?php
class Oaipmh_Harvest_Abstract_OaiDc extends Oaipmh_Harvest_Abstract
{
	// Mapping goes here, per page.
	protected function _harvestPage()
	{
		foreach ($this->_getRecords() as $record) {
			//echo $record->header->identifier;
			//echo PHP_EOL;
		}
	}
}