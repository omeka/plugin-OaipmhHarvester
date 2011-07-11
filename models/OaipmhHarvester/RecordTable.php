<?php
/**
 * @package OaipmhHarvester
 * @subpackage Models
 * @copyright Copyright (c) 2009-2011 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

/**
 * Model class for a record table.
 *
 * @package OaipmhHarvester
 * @subpackage Models
 */
class OaipmhHarvester_RecordTable extends Omeka_Db_Table
{
    /**
     * Return records by harvest ID.
     * 
     * @param int $harvsetId
     * @return array An array of OaipmhHarvester_Record objects.
     */
    public function findByHarvestId($harvestId)
    {
        $select = $this->getSelect();
        $select->where('harvest_id = ?');
        return $this->fetchObjects($select, array($harvestId));
    }
    
    /**
     * Return records by OAI-PMH identifier.
     * 
     * @param string $identifier OAI-PMH identifier
     * @return array An array of OaipmhHarvester_Record objects.
     */
    public function findByOaiIdentifier($identifier)
    {
        $select = $this->getSelect();
        $select->where('identifier = ?');
        return $this->fetchObjects($select, array($identifier));
    }
    
    /**
     * Return records by item ID.
     * 
     * @param mixes $itemId Item ID
     * @return OaipmhHarvester_Record Record corresponding to item id.
     */
    public function findByItemId($itemId)
    {
        $select = $this->getSelect();
        $select->where('item_id = ?');
        return $this->fetchObject($select, array($itemId));
    }

    public function applySearchFilters($select, $params)
    {
        $harvestKeys = array(
            'base_url',
            'set_spec',
            'metadata_prefix',
        );
        if (array_intersect($harvestKeys, array_keys($params))) {
            $this->_join($select, 'Harvest');
            foreach ($harvestKeys as $key) {
                if (array_key_exists($key, $params)) {
                    if ($params[$key] === null) {
                        $select->where("h.$key IS NULL");
                    } else {
                        $select->where("h.$key =?", $params[$key]);
                    }
                }
            }
        }
        if (array_key_exists('identifier', $params))
        {
            $select->where("identifier = ?", $params['identifier']);
        }
    }

    private function _join($select, $tableName)
    {
        $tableAlias = $this->getTableAlias();
        switch ($tableName) {
            case 'Harvest':
                $select->joinInner(
                    array('h' => $this->_db->OaipmhHarvester_Harvest),
                    "h.id = $tableAlias.harvest_id",
                    array()
                );
                break;
            default:
                break;
        }
    }
}
