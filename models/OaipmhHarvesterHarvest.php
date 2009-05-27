<?php
/**
 * @package OaipmhHarvester
 * @subpackage Models
 * @copyright Copyright (c) 2009 Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

/**
 * Model class for a harvest.
 *
 * @package OaipmhHarvester
 * @subpackage Models
 */
class OaipmhHarvesterHarvest extends Omeka_Record
{
    const STATUS_STARTING    = 'starting';
    const STATUS_IN_PROGRESS = 'in progress';
    const STATUS_COMPLETED   = 'completed';
    const STATUS_ERROR       = 'error';
    const STATUS_DELETED     = 'deleted';
    const STATUS_KILLED      = 'killed';
    
    public $id;
    public $collection_id;
    public $base_url;
    public $metadata_prefix;
    public $metadata_class;
    public $set_spec;
    public $set_name;
    public $set_description;
    public $status;
    public $status_messages;
    public $initiated;
    public $completed;
    public $start_from;
    public $pid;
}