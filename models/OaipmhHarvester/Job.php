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
        $config = Omeka_Context::getInstance()->config;
        $harvesterConfig = $config->plugins->OaipmhHarvester;
        if ($harvesterConfig && $harvesterConfig->memoryLimit) {
            ini_set('memory_limit', $harvesterConfig->memoryLimit);
        }
        
        // Set the set.
        $harvest = $this->_db->getTable('OaipmhHarvester_Harvest')->find($this->_harvestId);
        if (!$harvest) {
            throw new UnexpectedValueException("Harvest with id = '$this->_harvestId' does not exist.");
        }

        // Begin building the global options array.
        $options = array();

        // Set the ignore deleted records option.
        // FIXME Does this do anything?
        $options['ignore_deleted_records'] = get_option('oaipmh_harvester_ignore_deleted_records') == 'yes' ? true : false;

        // Set the metadata prefix.
        $metadataPrefix = $harvest->metadata_prefix;

        // Set the metadata prefix class.
        require_once 'OaipmhHarvester/Harvest/Abstract.php';
        // FIXME Remove this dependency.
        require_once 'OaipmhHarvester/Xml.php';
        $harvester = OaipmhHarvester_Harvest_Abstract::factory($harvest, $options);
        $harvester->harvest();
        if ($harvest->isResumable()) {
            $this->resend();
        }
    }

    public function setHarvestId($id)
    {
        $this->_harvestId = $id;
    }
}
