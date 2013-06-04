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
    
    const XLINK_NAMESPACE = 'http://www.w3.org/1999/xlink';
    
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
                    ->mets
                    ->children(self::METS_NAMESPACE)
                    ->dmdSec
                    ->mdWrap
                    ->xmlData
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
        
        $fileMeta = $record
                    ->metadata
                    ->mets
                    ->children(self::METS_NAMESPACE)
                    ->fileSec
                    ->fileGrp;

                   $fileMetadata = array();
        
         // number of files associated with the item
        $fileCount = count($fileMeta->file)-1;
        $fileMetadata['file_transfer_type'] ='Url';
     
        while($fileCount >= 0){
            $f = $fileMeta->file[$fileCount]->FLocat->attributes(self::XLINK_NAMESPACE);
        
          $filedcMetadata = $fileMeta
                        ->file[$fileCount]
                    ->FContent
                    ->xmlData
                    ->children(self::DUBLIN_CORE_NAMESPACE);
           // print_r($filedcMetadata);
          foreach ($elements as $element) {
            if (isset($filedcMetadata->$element)) {
                foreach ($filedcMetadata->$element as $rawText) {
                    $text = trim($rawText);
                    $s['Dublin Core'][ucwords($element)][] 
                        = array('text' => (string) $text, 'html' => false);
                }
            }
        }
        
            $fileMetadata['files'][] = array(
                'Upload' => null,
                'Url' => (string)$f['href'],
                'source' => (string)$f['href'],
                'name' => (string)$f['title'],
                'metadata'=>isset($s)? $s: null,
                );       
         
            $fileCount--;
        }
             
                      
        return array('itemMetadata' => $itemMetadata,
                     'elementTexts' => $elementTexts,
                     'fileMetadata' => $fileMetadata);
    }
}
