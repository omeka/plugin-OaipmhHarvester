<?php
class OaipmhHarvesterSetTable extends Omeka_Db_Table
{
    public function findAllSets()
    {
        $select = $this->getSelect();
        return $this->fetchObjects($select);
    }
}