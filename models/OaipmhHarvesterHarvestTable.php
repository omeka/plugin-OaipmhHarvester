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
    
    /**
     * Find a harvest by base URL and set spec.  These are the components
     * required to make a harvest unique.
     *
     * @param string $baseUrl Base URL of the harvest
     * @param string $setSpec Set spec of the harvest
     * @param string $metadataPrefix Metadata prefix of the harvest
     * @return OaipmhHarvesterHarvest Record of existing harvest.
     */
    public function findUniqueHarvest($baseUrl, $setSpec, $metadataPrefix)
    {
        $select = $this->getSelect()->where('base_url = ?', $baseUrl)
                                    ->where('metadata_prefix = ?', $metadataPrefix);
        if($setSpec) 
            $select->where('set_spec = ?', $setSpec);
        else
            $select->where('set_spec IS NULL');
        return $this->fetchObject($select);
    }
}