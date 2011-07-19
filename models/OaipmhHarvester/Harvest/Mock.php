<?php

class OaipmhHarvester_Harvest_Mock extends OaipmhHarvester_Harvest_Abstract
{
    const METADATA_PREFIX = 'mock';
    const METADATA_SCHEMA = 'mock.schema';

    protected function _harvestRecord($record)
    {
        return array(
            'itemMetadata' => array(
                'public' => $this->getOption('public'),
            ),
            'elementTexts' => array(
                'Dublin Core' => array(
                    'Title' => array(
                        array('text' => 'Mock Title', 'html' => 0),
                    ),
                ),
            ),
            'fileMetadata' => array(),
        );        
    }
}
