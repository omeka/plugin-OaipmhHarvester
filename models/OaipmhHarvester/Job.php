<?php
/**
 *
 */
class OaipmhHarvester_Job extends Omeka_JobAbstract
{
    private $_memoryLimit;
    private $_harvestId;

    public function perform()
    {
        if ($this->_memoryLimit) {
            ini_set('memory_limit', $this->_memoryLimit);
        }
        
        // Set the set.
        $harvest = $this->_db->getTable('OaipmhHarvesterHarvest')->find($this->_harvestId);
        if (!$harvest) {
            throw new UnexpectedValueException("Harvest with id = '$this->_harvestId' does not exist.");
        }

        // Begin building the global options array.
        $options = array();

        // Set the ignore deleted records option.
        $options['ignore_deleted_records'] = get_option('oaipmh_harvester_ignore_deleted_records') == 'yes' ? true : false;

        // Set the metadata prefix.
        $metadataPrefix = $harvest->metadata_prefix;

        // Set the metadata prefix class.
        require_once 'OaipmhHarvester/Harvest/Abstract.php';
        require_once 'OaipmhHarvester/Xml.php';
        $metadataClass = $harvest->metadata_class;
        
        $metadataClassFile = str_replace('OaipmhHarvester_Harvest_', '', $metadataClass);

        require_once OAIPMH_HARVESTER_MAPS_DIRECTORY . "/$metadataClassFile.php";

        // Set the harvest object.
        new $metadataClass($harvest, $options);
    }

    public function setHarvestId($id)
    {
        $this->_harvestId = $id;
    }

    public function setMemoryLimit($limit)
    {
        $this->_memoryLimit = $limit;
    }
}
