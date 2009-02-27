<?php
class OaipmhHarvesterRecordTable extends Omeka_Db_Table
{
    public function findBySetId($setId)
    {
        $select = $this->getSelect();
        $select->where('set_id = ?');
        return $this->fetchObjects($select, array($setId));
    }
}