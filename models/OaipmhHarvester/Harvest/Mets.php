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
        $collectionMetadata['elementTexts']['Dublin Core']['Title'][]
            = array('text' => (string) $harvest->set_name, 'html' => false); 
        $collectionMetadata['elementTexts']['Dublin Core']['Description'][]
            = array('text' => (string) $harvest->set_Description, 'html' => false); 
        
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
        
       
        $map = $this->_getMap($record);
        $isEmpty = count($this->_getMap($record)) == 0;
        $dmdSections = $this->_dmdSecToArray($record, $isEmpty);
        if ($isEmpty) {
            $elementTexts = $dmdSections;
        } else {
            $elementTexts = $dmdSections[$map['itemId']];
        }

        $fileMetadata = array();
        $recordMetadata = $record->metadata;
        $recordMetadata->registerXpathNamespace('mets', self::METS_NAMESPACE);
        $files = $recordMetadata->xpath('mets:mets/mets:fileSec/mets:fileGrp/mets:file');
        foreach($files as $fl){
            $fileAttributes = $fl->attributes();
            $file = $fl->FLocat->attributes(self::XLINK_NAMESPACE);

            // The dmd id can be set in two main places.
            if (isset($fileAttributes['DMDID'])) {
                $dmdId = (string) $fileAttributes['DMDID'];
            }
            // Indirect, if any.
            elseif (isset($fileAttributes['ID'])) {
                $fileId = (string) $fileAttributes['ID'];
                $fileDmdIds = $recordMetadata->xpath("mets:mets/mets:structMap[1]//mets:div[mets:fptr[@FILEID = '$fileId']][1]/@DMDID");
                $dmdId = $fileDmdIds ? (string) reset($fileDmdIds) : null;
                $dmdId = $dmdId != $map['itemId'] ? $dmdId : null;
            }
            // No dmd.
            else {
                $dmdId = null;
            }

            $fileMetadata['files'][] = array(
                'Upload' => null,
                'Url' => (string) $file['href'],
                'source' => (string) $file['href'],
                //'name'   => (string) $file['title'],
                'metadata' => $dmdId ? $dmdSections[$dmdId] : array(),
            );
        }
        
        return array('itemMetadata' => $itemMetadata,
                     'elementTexts' => $elementTexts,
                     'fileMetadata' => $fileMetadata);
    }
    
    /**
     * Convenience function that returns the xml structMap as an array of items
     * and the files associated with it.
     *
     * if the structmap doesn't exist in the xml schema null will be returned.
     *
     * @param type $record
     * @return array|null
     */
    private function _getMap($record)
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
     * Convenience funciton that returns the xmls dmdSec as an Omeka
     * ElementTexts array.
     *
     * @internal Here, only Dublin Core is managed.
     *
     * @param type $record
     * @param boolean $isEmpty
     * @return boolean/array
     */
    private function _dmdSecToArray($record, $isEmpty)
    {
        $mets= $record->metadata->mets->children(self::METS_NAMESPACE);
        $meta = null;
        $elements = array(
            'title' => 'Title',
            'creator' => 'Creator',
            'subject' => 'Subject',
            'description' => 'Description',
            'publisher' => 'Publisher',
            'contributor' => 'Contributor',
            'date' => 'Date',
            'type' => 'Type',
            'format' => 'Format',
            'identifier' => 'Identifier',
            'source' => 'Source',
            'language' => 'Language',
            'relation' => 'Relation',
            'coverage' => 'Coverage',
            'rights' => 'Rights',
        );

        foreach($mets->dmdSec as $k){
            // TODO Currently, mdRef is not managed.
            if (empty($k->mdWrap)) {
                continue;
            }

            $dcMetadata = $k
                    ->mdWrap
                    ->xmlData
                    ->children(self::DUBLIN_CORE_NAMESPACE);

            // Sometime, an intermediate wrapper is added between xmlData
            // and children, as <dc:dc>, so a quick check is done.
            if ($dcMetadata->count() == 1) {
                $firstElement = $dcMetadata[0]->getName();
                if (!in_array($firstElement, array_keys($elements))) {
                    $dcMetadata = $dcMetadata->children(self::DUBLIN_CORE_NAMESPACE);
                }
            }

            if (empty($dcMetadata->count())) {
                continue;
            }

            $elementTexts = array();

            foreach ($elements as $element => $label) {
                if(isset($dcMetadata->$element)){
                    foreach($dcMetadata->$element as $rawText){
                         $text = trim($rawText);
                         $elementTexts['Dublin Core'][$label][]
                             = array('text'=> (string) $text, 'html' => false);
                    }
                }
            }
            if ($isEmpty) {
                $meta = $elementTexts;
            } else {
                $dmdAttributes = $k->attributes();
                $meta[(string) $dmdAttributes['ID']] = $elementTexts;
            }
        }
        
        return $meta;
    }
}
