<?php
class OaipmhHarvester_Harvest_Abstract_OaiDc extends OaipmhHarvester_Harvest_Abstract
{
    protected $collection;
    
    protected function beforeHarvest()
    {
        $harvest = $this->getHarvest();
        $collectionMetadata = array('name'        => $harvest->set_name, 
                                    'description' => $harvest->set_description, 
                                    'public'      => true, 
                                    'featured'    => false);
        $this->collection = $this->insertCollection($collectionMetadata);
    }
    
    // Mapping goes here, per record.
    protected function harvestRecord($record)
    {
        $itemMetadata = array('collection_id' => $this->collection->id, 
                              'public'        => true, 
                              'featured'      => false);
        
        $dcMetadata = $record
                    ->metadata
                    ->children('oai_dc', true)
                    ->children('dc', true);
        
        $elementTexts = array();
        $elements = array('contributor', 'coverage', 'creator', 
                          'date', 'description', 'format', 
                          'identifier', 'language', 'publisher', 
                          'relation', 'rights', 'source', 
                          'subject', 'title', 'type');
        foreach ($elements as $element) {
            if (isset($dcMetadata->$element)) {
                foreach ($dcMetadata->$element as $text) {
                    $elementTexts['Dublin Core'][ucwords($element)][] = array('text' => (string) $text, 'html' => false);
                }
            }
        }
        
        $this->insertItem($itemMetadata, $elementTexts);
    }
}