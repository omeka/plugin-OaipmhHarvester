<?php
/**
 * DeleteJob model
 */
class OaipmhHarvester_DeleteJob extends Omeka_Job_AbstractJob
{
    private $_memoryLimit;
    private $_harvestId;

    public function perform()
    {
        $config = Zend_Registry::get('bootstrap')->getResource('Config');
        if (isset($config->plugins->OaipmhHarvester->memoryLimit)) {
            ini_set('memory_limit', $config->plugins->OaipmhHarvester->memoryLimit);
        }
        
        // Set the set.
        $harvest = $this->_db
            ->getTable('OaipmhHarvester_Harvest')
            ->find($this->_harvestId);

        if (!$harvest) {
            throw new UnexpectedValueException(
                "Harvest with id = '$this->_harvestId' does not exist."
            );
        }

        // Race conditions are nasty. The easiest thing to do is to just wait
        // until the queued job is finished processing.
        if ($harvest->status == OaipmhHarvester_Harvest::STATUS_IN_PROGRESS) {
            // Then again, it might not be waiting for anything else.
            $harvest->status = OaipmhHarvester_Harvest::STATUS_DELETED;
            $harvest->save();
            return $this->resend();
        }

        $records = $this->_db
            ->getTable('OaipmhHarvester_Record')
            ->findByHarvestId($harvest->id);
        
        // Delete items if they exist.
        foreach ($records as $record) {
            if ($record->item_id) {
                $item = $this->_db
                    ->getTable('Item')
                    ->find($record->item_id);
                if ($item) {
                    $item->delete();
                }
                $record->delete();
            }
        }
        
        // Delete collection if exists.
        if ($harvest->collection_id) {
            $collection = $this->_db
                ->getTable('Collection')
                ->find($harvest->collection_id);

            if ($collection) {
                $collection->delete();
            }
            $harvest->collection_id = null;
        }
        
        $harvest->status = OaipmhHarvester_Harvest::STATUS_DELETED;
        $statusMessage = 'All items created for this harvest were deleted on ' 
                       . date('Y-m-d H:i:s');
        $harvest->status_messages = strlen($harvest->status_messages) == 0 
                                  ? $statusMessage 
                                  : "\n\n" . $statusMessage;
        // Reset the harvest start_from time if an error occurs during 
        // processing. Since there's no way to know exactly when the 
        // error occured, re-harvests need to start from the beginning.
        $harvest->start_from = null;
        $harvest->save();
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