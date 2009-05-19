<?php
class OaipmhHarvester_Harvest_Abstract_Cdwalite extends OaipmhHarvester_Harvest_Abstract
{
	/*	XML schema and OAI prefix for the format represented by this class.
		These constants are required for all maps. */
	const METADATA_SCHEMA = "http://www.getty.edu/CDWA/CDWALite/CDWALite-xsd-public-v1-1.xsd";
	const METADATA_PREFIX = "cdwalite";
	
    protected $collection;
    protected $_elementTexts = array();
    protected $_fileMetadata = array();
    
    // Flag to determine if qualified Dublin Core elements are available.
    protected $_qualified = true;
    
    protected function beforeHarvest()
    {
        // Detect if the Dublin Core Extended plugin is installed. If not, add a 
        // status message stating that more elements could have been mapped.
        if (!defined('DUBLIN_CORE_EXTENDED_PLUGIN_VERSION')) {
            $this->_qualified = false;
            $message = 'The Dublin Core Extended plugin is not currently installed. No data will be lost, but some CDWA Lite elements that would have otherwise been mapped to Dublin Core refinements will be mapped to their unqualified parent elements.';
            $this->addStatusMessage($message, self::MESSAGE_CODE_NOTICE);
        }
        
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
        $cdwalite = $record
                  ->metadata
                  ->children('cdwalite', true)
                  ->cdwaliteWrap
                  ->cdwalite;
        
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
            $this->_buildElementTexts('Type', $objectWorkType);
        }
        
        // classificationWrap (non-repeatable, not required) 
        // ->classification (repeatable, not required)
        // Map to Subject
        if ($classifications = $cdwalite->descriptiveMetadata->classificationWrap->classification) {
            foreach ($classifications as $classification) {
                $this->_buildElementTexts('Subject', $classification);
            }
        }
        
        // titleWrap (non-repeatable, required)
        // ->titleSet (repeatable, required)
        // ->title (non-repeatable, required)
        // Map to Title
        $titleSets = $cdwalite->descriptiveMetadata->titleWrap->titleSet;
        foreach ($titleSets as $titleSet) {
            $this->_buildElementTexts('Title', $titleSet->title);
        }
        
        // displayCreator (non-repeatable, required)
        // Map to Creator
        $displayCreator = $cdwalite->descriptiveMetadata->displayCreator;
        $this->_buildElementTexts('Creator', $displayCreator);
        
        // displayCreationDate (non-repeatable, required)
        // Map to Date Created (Date.Created)
        $displayCreationDate = $cdwalite->descriptiveMetadata->displayCreationDate;
        $elementName = $this->_qualified ? 'Date Created' : 'Date';
        $this->_buildElementTexts($elementName, $displayCreationDate);
        
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
                $this->_buildElementTexts('Subject', $locationSet->locationName);
            }
            
            // ->locationName (non-repeatable, required)
            // locationName[type] = formerRepository
            // Map to Source
            if ('formerRepository' == $type) {
                $this->_buildElementTexts('Source', $locationSet->locationName);
            }
            
            // ->workID (repeatable, not required)
            // Map to Identifier
            if ($workIds = $locationSet->workID) {
                foreach ($workIds as $workId) {
                    $this->_buildElementTexts('Identifier', $workId);
                }
            }
            
        }
        
        // styleWrap (non-repeatable, not required)
        // ->style (repeatable, not required)
        // Map to Subject -or- Temporal Coverage (Coverage.Temporal)
        if ($styles = $cdwalite->descriptiveMetadata->styleWrap->style) {
            foreach ($styles as $style) {
                $this->_buildElementTexts('Subject', $style);
            }
        }
        
        // displayMeasurements (non-repeatable, not required)
        // Map to Extent (Format.Extent)
        if ($displayMeasurements = $cdwalite->descriptiveMetadata->displayMeasurements) {
            $elementName = $this->_qualified ? 'Extent' : 'Format';
            $this->_buildElementTexts($elementName, $displayMeasurements);
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
                                $this->_buildElementTexts($elementName, $scaleMeasurement);
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
                    $this->_buildElementTexts($elementName, $termMaterialsTech);
                }
            }
        }
        
        // inscriptionsWrap (non-repeatable, not required)
        // ->inscriptions (repeatable, not required)
        // Map to Description
        if ($inscriptions = $cdwalite->descriptiveMetadata->inscriptionsWrap->inscriptions) {
            foreach ($inscriptions as $inscription) {
                $this->_buildElementTexts('Description', $inscription);
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
                        $this->_buildElementTexts('Subject', $subjectTerm);
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
                    $this->_buildElementTexts('Description', $descriptiveNote);
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
                        $this->_buildElementTexts('Relation', $labelRelatedWork);
                    }
                }
                
                // ->locRelatedWork (repeatable, not required)
                // Map to Relation
                if ($locRelatedWorks = $relatedWorkSet->locRelatedWork) {
                    foreach ($locRelatedWorks as $locRelatedWork) {
                        $this->_buildElementTexts('Relation', $locRelatedWork);
                    }
                }
                
                // ->relatedWorkRelType (non-repeatable, not required)
                // Map to Relation
                if ($relatedWorkRelType = $relatedWorkSet->relatedWorkRelType) {
                    $this->_buildElementTexts('Relation', $relatedWorkRelType);
                }
            }
        }
        
        // rightsWork (repeatable, not required)
        // Map to Rights
        if ($rightsWorks = $cdwalite->administrativeMetadata->rightsWork) {
            foreach ($rightsWorks as $rightsWork) {
                $this->_buildElementTexts('Rights', $rightsWork);
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
                        $this->_buildElementTexts($elementName, $resourceRelType);
                    }
                }
                
                // ->resourceViewType (repeatable, not required)
                // Map to Alternative Title (Title.Alternative) -or- Table Of Contents (Description.TableOfContents) -or- Abstract (Description.Abstract)
                if ($resourceViewTypes = $resourceSet->resourceViewType) {
                    foreach ($resourceViewTypes as $resourceViewType) {
                        $elementName = $this->_qualified ? 'Alternative Title' : 'Title';
                        $this->_buildElementTexts($elementName, $resourceRelType);
                    }
                }
                
                // ->resourceViewSubjectTerm (repeatable, not required)
                // Map to Subject
                if ($resourceViewSubjectTerms = $resourceSet->resourceViewSubjectTerm) {
                    foreach ($resourceViewSubjectTerms as $resourceViewSubjectTerm) {
                        $this->_buildElementTexts('Subject', $resourceViewSubjectTerm);
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
                $this->_buildElementTexts($elementName, $resourceRelType);
            }
        }
        
//var_dump($this->_elementTexts);
//print_r($this->_fileMetadata);
        
        // Insert the item and files.
        $this->insertItem($itemMetadata, $this->_elementTexts, $this->_fileMetadata);
        
        // Reset the built properties before the next record iteration.
        $this->_elementTexts = array();
        $this->_fileMetadata = array();
        
//exit;
    }
    
    // Wrapper method for buildElementTexts() that sets properties common to all 
    // CDWA Lite elements.
    protected function _buildElementTexts($element, $text)
    {
        $this->_elementTexts = $this->buildElementTexts($this->_elementTexts, 'Dublin Core', $element, $text);
    }

	public function getMetadataSchema()
	{
		return self::METADATA_SCHEMA;
	}
	
	public function getMetadataPrefix()
	{
		return self::METADATA_PREFIX;
	}
}