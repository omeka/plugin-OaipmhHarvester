<?php
/**
 * @package OaipmhHarvester
 * @subpackage Models
 * @copyright Copyright (c) 2009-2011 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

/**
 * Metadata format map for CDWA Lite.
 *
 * @package OaipmhHarvester
 * @subpackage Models
 * @link http://www.getty.edu/research/conducting_research/standards/cdwa/cdwalite.html
 */
class OaipmhHarvester_Harvest_Cdwalite extends OaipmhHarvester_Harvest_Abstract
{
    /*	XML schema and OAI prefix for the format represented by this class.
	    These constants are required for all maps. */
    const METADATA_SCHEMA = 'http://www.getty.edu/CDWA/CDWALite/CDWALite-xsd-public-v1-1.xsd';
    const METADATA_PREFIX = 'cdwalite';
    
    const CDWALITE_NAMESPACE = 'http://www.getty.edu/CDWA/CDWALite';
    
    /**
     * Collection to insert items into.
     * @var Collection
     */
    protected $_collection;
    
    protected $_elementTexts = array();
    protected $_fileMetadata = array();
    
    // Flag to determine if qualified Dublin Core elements are available.
    protected $_qualified = true;
    
    /**
     * Actions to be carried out before the harvest of any items begins.
     */
    protected function _beforeHarvest()
    {
        // Detect if the Dublin Core Extended plugin is installed. If not, add a 
        // status message stating that more elements could have been mapped.
        if (!defined('DUBLIN_CORE_EXTENDED_PLUGIN_VERSION')) {
            $this->_qualified = false;
            $message = 'The Dublin Core Extended plugin is not currently '
                     . 'installed. No data will be lost, but some CDWA Lite '
                     . 'elements that would have otherwise been mapped to '
                     . 'Dublin Core refinements will be mapped to their '
                     . 'unqualified parent elements.';
            $this->_addStatusMessage($message, self::MESSAGE_CODE_NOTICE);
        }
        
        $harvest = $this->_getHarvest();
        $collectionMetadata = array('name'        => $harvest->set_name, 
                                    'description' => $harvest->set_description, 
                                    'public'      => true, 
                                    'featured'    => false);
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
        $itemMetadata = array('collection_id' => $this->_collection->id, 
                              'public'        => true, 
                              'featured'      => false);
                              
        if (isset($record->metadata->children(self::CDWALITE_NAMESPACE)->cdwaliteWrap->cdwalite)) {
            $cdwalite = $record->metadata->children(self::CDWALITE_NAMESPACE)->cdwaliteWrap->cdwalite;
        } else {
            $cdwalite = $record->metadata->children(self::CDWALITE_NAMESPACE)->cdwalite;
        }
        
        // CDWA Lite to Dublin Core crosswalk:
        // http://www.getty.edu/research/conducting_research/standards/intrometadata/crosswalks.html
    
        // Elements that map to qualified Dublin Core elements should map to the 
        // unqualified parent elements if the Dublin Core Extended plugin is not 
        // installed.
        
        // objectWorkTypeWrap (non-repeatable, required)
        // ->objectWorkType (repeatable, required)
        // Map to Type
        $objectWorkTypes = $cdwalite->descriptiveMetadata->objectWorkTypeWrap->objectWorkType;
        foreach ($objectWorkTypes as $objectWorkType) {
            $this->_addElementText('Type', $objectWorkType);
        }
        
        // classificationWrap (non-repeatable, not required) 
        // ->classification (repeatable, not required)
        // Map to Subject
        if ($classifications = $cdwalite->descriptiveMetadata->classificationWrap->classification) {
            foreach ($classifications as $classification) {
                $this->_addElementText('Subject', $classification);
            }
        }
        
        // titleWrap (non-repeatable, required)
        // ->titleSet (repeatable, required)
        // ->title (non-repeatable, required)
        // Map to Title
        $titleSets = $cdwalite->descriptiveMetadata->titleWrap->titleSet;
        foreach ($titleSets as $titleSet) {
            $this->_addElementText('Title', $titleSet->title);
        }
        
        // displayCreator (non-repeatable, required)
        // Map to Creator
        $displayCreator = $cdwalite->descriptiveMetadata->displayCreator;
        $this->_addElementText('Creator', $displayCreator);
        
        // displayCreationDate (non-repeatable, required)
        // Map to Date Created (Date.Created)
        $displayCreationDate = $cdwalite->descriptiveMetadata->displayCreationDate;
        $elementName = $this->_qualified ? 'Date Created' : 'Date';
        $this->_addElementText($elementName, $displayCreationDate);
        
        // locationWrap (non-repeatable, required)
        // ->locationSet (repeatable, required)
        // Map to [multiple]
        $locationSets = $cdwalite->descriptiveMetadata->locationWrap->locationSet;
        foreach ($locationSets as $locationSet) {
            
            // ->locationName (non-repeatable, required)
            // locationName[type] = creationLocation
            // Map to Subject -or- Spatial Coverage (Coverage.Spatial)
            $type = $locationSet->locationName->attributes('cdwalite', true)->type;
            if ('creationLocation' == $type) {
                $this->_addElementText('Subject', $locationSet->locationName);
            }
            
            // ->locationName (non-repeatable, required)
            // locationName[type] = formerRepository
            // Map to Source
            if ('formerRepository' == $type) {
                $this->_addElementText('Source', $locationSet->locationName);
            }
            
            // ->workID (repeatable, not required)
            // Map to Identifier
            if ($workIds = $locationSet->workID) {
                foreach ($workIds as $workId) {
                    $this->_addElementText('Identifier', $workId);
                }
            }
            
        }
        
        // styleWrap (non-repeatable, not required)
        // ->style (repeatable, not required)
        // Map to Subject -or- Temporal Coverage (Coverage.Temporal)
        if ($styles = $cdwalite->descriptiveMetadata->styleWrap->style) {
            foreach ($styles as $style) {
                $this->_addElementText('Subject', $style);
            }
        }
        
        // displayMeasurements (non-repeatable, not required)
        // Map to Extent (Format.Extent)
        if ($displayMeasurements = $cdwalite->descriptiveMetadata->displayMeasurements) {
            $elementName = $this->_qualified ? 'Extent' : 'Format';
            $this->_addElementText($elementName, $displayMeasurements);
        }
        
        // indexingMeasurementsWrap (non-repeatable, not required)
        // ->indexingMeasurementsSet (repeatable, not required)
        if ($indexingMeasurementsSets = $cdwalite->descriptiveMetadata->indexingMeasurementsWrap->indexingMeasurementsSet) {
            foreach ($indexingMeasurementsSets as $indexingMeasurementsSet) {
                
                // ->measurementsSet (repeatable, not required)
                if ($measurementsSets = $indexingMeasurementsSet->measurementsSet) {
                    foreach ($measurementsSets as $measurementsSet) {
                        
                        // ->scaleMeasurements (repeatable, not required)
                        // Map to Extent (Format.Extent)
                        if ($scaleMeasurements = $measurementsSet->scaleMeasurements) {
                            foreach ($scaleMeasurements as $scaleMeasurement) {
                                $elementName = $this->_qualified ? 'Extent' : 'Format';
                                $this->_addElementText($elementName, $scaleMeasurement);
                            }
                        }
                    }
                }
            }
        }
        
        // indexingMaterialsTechWrap (non-repeatable, not required)
        // ->indexingMaterialsTechSet (repeatable, not required, type = material)
        if ($indexingMaterialsTechSets = $cdwalite->descriptiveMetadata->indexingMaterialsTechWrap->indexingMaterialsTechSet) {
            
            // ->termMaterialsTech (repeatable, not required)
            // Map to Medium (Format.Medium)
            $type = $indexingMaterialsTechSets->attributes('cdwalite', true)->type;
            if ($termMaterialsTechs = $indexingMaterialsTechSets->termMaterialsTech
                && 'material' == $type ) {
                foreach ($termMaterialsTechs as $termMaterialsTech) {
                    $elementName = $this->_qualified ? 'Medium' : 'Format';
                    $this->_addElementText($elementName, $termMaterialsTech);
                }
            }
        }
        
        // inscriptionsWrap (non-repeatable, not required)
        // ->inscriptions (repeatable, not required)
        // Map to Description
        if ($inscriptions = $cdwalite->descriptiveMetadata->inscriptionsWrap->inscriptions) {
            foreach ($inscriptions as $inscription) {
                $this->_addElementText('Description', $inscription);
            }
        }
        
        // indexingSubjectWrap (non-repeatable, not required)
        // ->indexingSubjectSet (repeatable, not required)
        if ($indexingSubjectSets = $cdwalite->descriptiveMetadata->indexingSubjectWrap->indexingSubjectSet) {
            foreach ($indexingSubjectSets as $indexingSubjectSet) {
                
                // ->subjectTerm (repeatable, not required)
                // Map to Subject -or- Spatial Coverage (Coverage.Spatial) -or- Temporal Coverage (Coverage.Temporal)
                if ($subjectTerms = $indexingSubjectSet->subjectTerm) {
                    foreach ($subjectTerms as $subjectTerm) {
                        $this->_addElementText('Subject', $subjectTerm);
                    }
                }
            }
        }
        
        // descriptiveNoteWrap (non-repeatable, not required)
        // ->descriptiveNoteSet (repeatable, not required)
        if ($descriptiveNoteSets = $cdwalite->descriptiveMetadata->descriptiveNoteWrap->descriptiveNoteSet) {
            foreach ($descriptiveNoteSets as $descriptiveNoteSet) {
                
                // ->descriptiveNote (non-repeatable, not required)
                // Map to Description
                if ($descriptiveNote = $descriptiveNoteSet->descriptiveNote) {
                    $this->_addElementText('Description', $descriptiveNote);
                }
            }
        }
        
        // relatedWorksWrap (non-repeatable, not required)
        // ->relatedWorkSet (repeatable, not required)
        if ($relatedWorkSets = $cdwalite->descriptiveMetadata->relatedWorksWrap->relatedWorkSet) {
            foreach ($relatedWorkSets as $relatedWorkSet) {
                
                // ->labelRelatedWork (repeatable, not required)
                // Map to Relation
                if ($labelRelatedWorks = $relatedWorkSet->labelRelatedWork) {
                    foreach ($labelRelatedWorks as $labelRelatedWork) {
                        $this->_addElementText('Relation', $labelRelatedWork);
                    }
                }
                
                // ->locRelatedWork (repeatable, not required)
                // Map to Relation
                if ($locRelatedWorks = $relatedWorkSet->locRelatedWork) {
                    foreach ($locRelatedWorks as $locRelatedWork) {
                        $this->_addElementText('Relation', $locRelatedWork);
                    }
                }
                
                // ->relatedWorkRelType (non-repeatable, not required)
                // Map to Relation
                if ($relatedWorkRelType = $relatedWorkSet->relatedWorkRelType) {
                    $this->_addElementText('Relation', $relatedWorkRelType);
                }
            }
        }
        
        // rightsWork (repeatable, not required)
        // Map to Rights
        if ($rightsWorks = $cdwalite->administrativeMetadata->rightsWork) {
            foreach ($rightsWorks as $rightsWork) {
                $this->_addElementText('Rights', $rightsWork);
            }
        }
        
        // resourceWrap (non-repeatable, not required)
        // ->resourceSet (repeatable, not required)
       if ($resourceSets = $cdwalite->administrativeMetadata->resourceWrap->resourceSet) {
            foreach ($resourceSets as $resourceSet) {
                
                // ->resourceRelType (repeatable, not required)
                // Map to Is Format Of (Relation.IsFormatOf)
                 if ($resourceRelTypes = $resourceSet->resourceRelType) {
                    foreach ($resourceRelTypes as $resourceRelType) {
                        $elementName = $this->_qualified ? 'Is Format Of' : 'Relation';
                        $this->_addElementText($elementName, $resourceRelType);
                    }
                }
                
                // ->resourceViewType (repeatable, not required)
                // Map to Alternative Title (Title.Alternative) -or- Table Of Contents (Description.TableOfContents) -or- Abstract (Description.Abstract)
                if ($resourceViewTypes = $resourceSet->resourceViewType) {
                    foreach ($resourceViewTypes as $resourceViewType) {
                        $elementName = $this->_qualified ? 'Alternative Title' : 'Title';
                        $this->_addElementText($elementName, $resourceRelType);
                    }
                }
                
                // ->resourceViewSubjectTerm (repeatable, not required)
                // Map to Subject
                if ($resourceViewSubjectTerms = $resourceSet->resourceViewSubjectTerm) {
                    foreach ($resourceViewSubjectTerms as $resourceViewSubjectTerm) {
                        $this->_addElementText('Subject', $resourceViewSubjectTerm);
                    }
                }
                
                // ->linkResource (non-repeatable, not required)
                // Map to Omeka:files
                if ($linkResource = $resourceSet->linkResource) {
                    $source = (string) $linkResource;
                    $extension = strrchr($source, '.');
                    $name = isset($resourceSet->resourceID) 
                            ? (string) $resourceSet->resourceID . $extension 
                            : $source;
                    $this->_fileMetadata['files'][] = array('source' => $source, 'name' => $name);
                }
            }
        }
        
        // recordWrap (non-repeatable, required)
        // ->recordSource (repeatable, not required)
        // Map to Is Referenced By (Relation.IsReferencedBy)
        if ($recordSources = $cdwalite->administrativeMetadata->recordWrap->recordSource) {
            foreach ($recordSources as $recordSource) {
                $elementName = $this->_qualified ? 'Is Referenced By' : 'Relation';
                $this->_addElementText($elementName, $resourceRelType);
            }
        }
        
        // Insert the item and files.
        $harvestedRecord = array('itemMetadata' => $itemMetadata,
                                 'elementTexts' => $this->_elementTexts,
                                 'fileMetadata' => $this->_fileMetadata);
        
        // Reset the built properties before the next record iteration.
        $this->_elementTexts = array();
        $this->_fileMetadata = array();
        
        return $harvestedRecord;
    }
    
    /** 
     * Add a Dublin Core element text.
     *
     * @param string $element Element name
     * @param string $text Element text
     */
    private function _addElementText($element, $text)
    {
        $this->_elementTexts = $this->_buildElementTexts(
            $this->_elementTexts, 
            'Dublin Core', 
            $element, 
            $text
        );
    }
}
