<?php
class OaipmhHarvesterRecordTable extends Omeka_Db_Table
{
    public function findByHarvestId($harvestId)
    {
        $select = $this->getSelect();
        $select->where('harvest_id = ?');
        return $this->fetchObjects($select, array($harvestId));
    }
}