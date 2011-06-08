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
class OaipmhHarvester_Harvest extends Omeka_Record
{
    const STATUS_QUEUED      = 'queued';
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
    public $resumption_token;
    public $initiated;
    public $completed;
    public $start_from;

    public function isResumable()
    {
        return ($this->resumption_token !== null);
    }

    public function addStatusMessage($message, $messageCode = null, $delimiter = "\n\n")
    {
        if (0 == strlen($this->status_messages)) {
            $delimiter = '';
        }
        $date = $this->_getCurrentDateTime();
        $messageCodeText = $this->_getMessageCodeText($messageCode);
        
        $this->status_messages .= "$delimiter$messageCodeText: $message ($date)";
        $this->forceSave();
    }

    /**
     * Return a message code text corresponding to its constant.
     * 
     * @param int $messageCode
     * @return string
     */
    private function _getMessageCodeText($messageCode)
    {
        switch ($messageCode) {
            case OaipmhHarvester_Harvest_Abstract::MESSAGE_CODE_ERROR:
                $messageCodeText = 'Error';
                break;
            case OaipmhHarvester_Harvest_Abstract::MESSAGE_CODE_NOTICE:
            default:
                $messageCodeText = 'Notice';
                break;
        }
        return $messageCodeText;
    }
    
    /**
     * Return the current, formatted date.
     * 
     * @return string
     */
    private function _getCurrentDateTime()
    {
        return date('Y-m-d H:i:s');
    }
}
