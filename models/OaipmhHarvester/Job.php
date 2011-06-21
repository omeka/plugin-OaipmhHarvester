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

        require_once 'OaipmhHarvester/Harvest/Abstract.php';
        $harvester = OaipmhHarvester_Harvest_Abstract::factory($harvest);
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
