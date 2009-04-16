<?php
/**
 * @package OaipmhHarvester
 * @subpackage Models
 * @copyright Copyright (c) 2009 Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

/**
 * Model class for a record.
 *
 * @package OaipmhHarvester
 * @subpackage Models
 */
class OaipmhHarvesterRecord extends Omeka_Record
{
    public $id;
    public $harvest_id;
    public $item_id;
    public $identifier;
    public $datestamp;
}