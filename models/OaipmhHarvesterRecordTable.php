<?php
/**
 * @package OaipmhHarvester
 * @subpackage Models
 * @copyright Copyright (c) 2009 Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

/**
 * Model class for a record table.
 *
 * @package OaipmhHarvester
 * @subpackage Models
 */
class OaipmhHarvesterRecordTable extends Omeka_Db_Table
{
    /**
     * Return records by harvest ID.
     * 
     * @param int $harvsetId
     * @return array An array of OaipmhHarvesterRecord objects.
     */
    public function findByHarvestId($harvestId)
    {
        $select = $this->getSelect();
        $select->where('harvest_id = ?');
        return $this->fetchObjects($select, array($harvestId));
    }
}