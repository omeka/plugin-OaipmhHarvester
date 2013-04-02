<?php
/**
 *
 */
class OaipmhHarvester_Job extends Omeka_Job_AbstractJob
{
    private $_memoryLimit;
    private $_harvestId;

    public function perform()
    {
        if ($memoryLimit = oaipmh_harvester_config('memoryLimit')) {
            ini_set('memory_limit', $memoryLimit); 
        }
        // Set the set.
        $harvest = $this->_db->getTable('OaipmhHarvester_Harvest')
                             ->find($this->_harvestId);
        if (!$harvest) {
            throw new UnexpectedValueException(
                "Harvest with id = '$this->_harvestId' does not exist.");
        }

        // Resent jobs can remain queued after all the items themselves have 
        // been deleted. Skip if that's the case.
        if ($harvest->status == OaipmhHarvester_Harvest::STATUS_DELETED) {
            _log("Queued harvest with ID = {$harvest->id} was deleted prior "
               . "to running this job.");
            return;
        }

        require_once 'OaipmhHarvester/Harvest/Abstract.php';
        $harvester = OaipmhHarvester_Harvest_Abstract::factory($harvest);
        $harvester->harvest();
        if ($harvest->isResumable() && !$harvest->isError()) {
            $this->resend();
        }
    }

    public function setHarvestId($id)
    {
        $this->_harvestId = $id;
    }
}
