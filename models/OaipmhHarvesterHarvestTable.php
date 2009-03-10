<?php
class OaipmhHarvesterHarvestTable extends Omeka_Db_Table
{
    public function findAllHarvests()
    {
        $select = $this->getSelect()->order('id');
        return $this->fetchObjects($select);
    }
}