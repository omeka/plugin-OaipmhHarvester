<?php
class OaipmhHarvesterHarvest extends Omeka_Record
{
    const STATUS_STARTING    = 'starting';
    const STATUS_IN_PROGRESS = 'in progress';
    const STATUS_COMPLETED   = 'completed';
    const STATUS_ERROR       = 'error';
    const STATUS_DELETED     = 'deleted';
    
    public $id;
    public $collection_id;
    public $base_url;
    public $metadata_prefix;
    public $set_spec;
    public $set_name;
    public $set_description;
    public $status;
    public $status_messages;
    public $initiated;
    public $completed;
}