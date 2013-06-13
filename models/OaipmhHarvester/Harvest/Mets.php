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
            'metadata' => array(
                'public' => $this->getOption('public'),
                'featured' => $this->getOption('featured'),
            ),);
        $collectionMetadata['elementTexts']['Dublin Core']['Title'][]= array(
            'text' => (string)$harvest->set_name, 
            'html' => false); 
        $collectionMetadata['elementTexts']['Dublin Core']['Description'][]= array(
            'text' => (string)$harvest->set_Description, 
            'html' => false); 
        
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
        
      
        $map = $this->getMap($record);
        $dmdSecArr = $this->dmdSecToArray($record);
        
        $fileMeta = $record
                ->metadata
                ->mets
                ->children(self::METS_NAMESPACE)
                ->fileSec
                ->fileGrp;
        
        //number of files associated with the item
         $fileCount = count($fileMeta->file)-1;
         $fileMetadata['file_transfer_type'] = 'Url';
         
         while($fileCount >= 0){
             $file = $fileMeta->file[$fileCount]->FLocat->attributes(self::XLINK_NAMESPACE);
             
             $fileDmdId = $fileMeta->file[$fileCount]->attributes();
             $fm = $dmdSecArr[(string)$fileDmdId['DMDID']];
             
             $fileMetadata['files'][] = array(
                 'upload' => null,
                 'Url'  => (string)$file['href'],
                 'source' => (string)$file['href'],
                 'name'   => (string)$file['title'],
                 'metadata' =>(array_key_exists('Dublin Core', $file))? $file: null,
             );
            
             $fileCount--;
         }
        return array('itemMetadata' => $itemMetadata,
                     'elementTexts' => ($map['itemId'] == null)? $dmdSecArr: $dmdSecArr[$map['itemId']],
                     'fileMetadata' => $fileMetadata);
    }
    
    /**
     * 
     * Convenience function that returns the xml structMap
     * as an array of items and the files associated with it.
     * 
     * if the structmap doesn't exist in the xml schema null
     * will be returned.
     * 
     * @param type $record
     * @return type array/null 
     *        
     */
    private function getMap($record)
    {
        $structMap = $record
                ->metadata
                ->mets
                ->structMap
                ->div;
        
        $map = null;
        if(isset($structMap['DMDID'])){
            $map['itemId'] = (string)$structMap['DMDID'];
            
            $fileCount = count($structMap->fptr);
            
            $map['files'] = null;
            if($fileCount != 0){
                foreach($structMap->fptr as $fileId){
                    $map['files'][] = (string)$fileId['FILEID'];
                }
            }
        }
        
        
        return $map;
    }
    /**
     * 
     * Convenience funciton that returns the 
     * xmls dmdSec as an Omeka ElementTexts array
     * 
     * @param type $record
     * @return boolean/array
     */
    private function dmdSecToArray($record)
    {   $mets= $record->metadata->mets->childrend(self::METADATA_PREFIX);
        $meta = null;
        foreach($mets->dmdSec as $k){
            $dcMetadata = $k
                    ->mdWrap
                    ->xmlData
                    ->children(self::DUBLIN_CORE_NAMESPACE);
            $elementTexts = array();
            $elements = array('contributor', 'coverage', 'creator', 
                          'date', 'description', 'format', 
                          'identifier', 'language', 'publisher', 
                          'relation', 'rights', 'source', 
                          'subject', 'title', 'type');
             
            foreach($elements as $element){
                if(isset($dcMetadata->$element)){
                    foreach($dcMetadata->$element as $rawText){
                         $text = trim($rawText);
                         $elementTexts['Dublin Core'][ucwords($element)][]
                                 = array('text'=> (string) $text, 'html' => false);
                    }
                }
            }
            if($this->getMap($record) == null){
                $meta = $elementTexts;
            }else {
                $meta[(string)$k->attributes()] = $elementTexts;
            }
        }
        
        return $meta;
    }
}
