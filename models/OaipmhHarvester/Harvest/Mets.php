<?php
/**
 * @package OaipmhHarvester
 * @subpackage Models
 * @copyright Copyright (c) 2009-2011 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

/**
 * Metadata format map for the required Mets Dublin Core format
 *
 * @package OaipmhHarvester
 * @subpackage Models
 */

class OaipmhHarvester_Harvest_Mets extends OaipmhHarvester_Harvest_Abstract
{
    /*Xml schema and OAI prefix for the format represented by this class
     * These constants are required for all maps
     */
    /** OAI-PMH metadata prefix */
    const METADATA_PREFIX = 'mets';

    /** XML namespace for output format */
    const METS_NAMESPACE = 'http://www.loc.gov/METS/';

    /** XML schema for output format */
    const METADATA_SCHEMA = 'http://www.loc.gov/standards/mets/mets.xsd';

    /** XML namespace for unqualified Dublin Core */
    const DUBLIN_CORE_NAMESPACE = 'http://purl.org/dc/elements/1.1/';
    
    protected $_collection;
    
     protected function _beforeHarvest()
    {
        $harvest = $this->_getHarvest();
        $collectionMetadata = array(
            'name'        => $harvest->set_name, 
            'description' => $harvest->set_description, 
            'public'      => $this->getOption('public'), 
            'featured'    => $this->getOption('featured'),
        );
        $this->_collection = $this->_insertCollection($collectionMetadata);
    }
    
    /**
     * Harvest one record.
     *
     * @param SimpleXMLIterator $record XML metadata record
     * @return array Array of item-level, element texts and file metadata.
     */
    protected function _harvestRecord($record)
    {
        $itemMetadata = array(
            'collection_id' => $this->_collection->id, 
            'public'        => $this->getOption('public'), 
            'featured'      => $this->getOption('featured'),
        );
        
        $dcMetadata = $record
                    ->metadata
                    ->children(self::METS_NAMESPACE)
                    ->children(self::DUBLIN_CORE_NAMESPACE);
        
        $elementTexts = array();
        $elements = array('contributor', 'coverage', 'creator', 
                          'date', 'description', 'format', 
                          'identifier', 'language', 'publisher', 
                          'relation', 'rights', 'source', 
                          'subject', 'title', 'type');
        foreach ($elements as $element) {
            if (isset($dcMetadata->$element)) {
                foreach ($dcMetadata->$element as $rawText) {
                    $text = trim($rawText);
                    $elementTexts['Dublin Core'][ucwords($element)][] 
                        = array('text' => (string) $text, 'html' => false);
                }
            }
        }
        
        return array('itemMetadata' => $itemMetadata,
                     'elementTexts' => $elementTexts,
                     'fileMetadata' => array());
    }
}
