<?php
/**
 * @package OaipmhHarvester
 * @subpackage Models
 * @copyright Copyright (c) 2009-2011 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

/**
 * Model class for a record.
 *
 * @package OaipmhHarvester
 * @subpackage Models
 */
class OaipmhHarvester_Record extends Omeka_Record_AbstractRecord
{
    public $id;
    public $harvest_id;
    public $item_id;
    public $identifier;
    public $datestamp;
}
