<?php
/**
 * @package OaipmhHarvester
 * @subpackage Libraries
 * @copyright Copyright (c) 2009 Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

/**
 * Metadata format map for the required oai_dc Dublin Core format
 *
 * @package OaipmhHarvester
 * @subpackage Libraries
 */
class OaipmhHarvester_Harvest_OaiDc extends OaipmhHarvester_Harvest_Abstract
{
    /*  XML schema and OAI prefix for the format represented by this class.
        These constants are required for all maps. */
    const METADATA_SCHEMA = 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd';
    const METADATA_PREFIX = 'oai_dc';

    const OAI_DC_NAMESPACE = 'http://www.openarchives.org/OAI/2.0/oai_dc/';
    const DUBLIN_CORE_NAMESPACE = 'http://purl.org/dc/elements/1.1/';

    /**
     * Collection to insert items into.
     * @var Collection
     */
    protected $collection;
    
    /**
     * Actions to be carried out before the harvest of any items begins.
     */
    protected function beforeHarvest()
    {
        $harvest = $this->getHarvest();
        $collectionMetadata = array('name'        => $harvest->set_name, 
                                    'description' => $harvest->set_description, 
                                    'public'      => true, 
                                    'featured'    => false);
        $this->collection = $this->insertCollection($collectionMetadata);
    }
    
    /**
     * Harvest one record.
     *
     * @param SimpleXMLIterator $record XML metadata record
     * @return array Array of item-level, element texts and file metadata.
     */
    protected function harvestRecord($record)
    {
        $itemMetadata = array('collection_id' => $this->collection->id, 
                              'public'        => true, 
                              'featured'      => false);
        
        $dcMetadata = $record
                    ->metadata
                    ->children(self::OAI_DC_NAMESPACE)
                    ->children(self::DUBLIN_CORE_NAMESPACE);
        
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
        
        return array('itemMetadata' => $itemMetadata,
                     'elementTexts' => $elementTexts,
                     'fileMetadata' => array());
    }
    
    /**
     * Return the metadata schema URI.
     *
     * @return string Schema URI
     */
    public function getMetadataSchema()
    {
        return self::METADATA_SCHEMA;
    }
    
    /**
	 * Return the metadata prefix.
	 *
	 * @return string Metadata prefix
	 */
    public function getMetadataPrefix()
    {
        return self::METADATA_PREFIX;
    }
}
