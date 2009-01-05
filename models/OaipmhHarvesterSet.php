<?php
class OaipmhHarvesterSet extends Omeka_Record
{
	public $id;
	public $collection_id;
	public $status_id;
	public $base_url;
	public $set_spec;
	public $set_name;
	public $set_description;
	public $metadata_prefix;
	public $messages;
	public $initiated;
	public $completed;
    
    public function getStatus()
    {
        return $this->getTable('OaipmhHarvesterSetStatus')->find($this->status_id);
    }
}
