<?php
/**
 * @package OaipmhHarvester
 * @subpackage Models
 * @copyright Copyright (c) 2009 Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

/**
 * Model class for a harvest table.
 *
 * @package OaipmhHarvester
 * @subpackage Models
 */
class OaipmhHarvesterHarvestTable extends Omeka_Db_Table
{
    /**
     * Return all harvests.
     * 
     * @return array An array of all OaipmhHarvesterHarvest objects, ordered by 
     * ID.
     */
    public function findAllHarvests()
    {
        $select = $this->getSelect()->order('id');
        return $this->fetchObjects($select);
    }
}