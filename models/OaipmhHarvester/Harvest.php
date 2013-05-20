<?php
/**
 * @package OaipmhHarvester
 * @subpackage Models
 * @copyright Copyright (c) 2009-2011 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

/**
 * Model class for a harvest.
 *
 * @package OaipmhHarvester
 * @subpackage Models
 */
class OaipmhHarvester_Harvest extends Omeka_Record_AbstractRecord
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
    public $set_spec;
    public $set_name;
    public $set_description;
    public $status;
    public $status_messages;
    public $resumption_token;
    public $initiated;
    public $completed;
    public $start_from;

    private $_request;

    public function setRequest(OaipmhHarvester_Request $request = null)
    {
        if ($request === null) {
            $request = new OaipmhHarvester_Request();
        }
        $this->_request = $request;
    }

    public function getRequest()
    {
        if (!$this->_request) {
            $this->setRequest();
        }
        return $this->_request;
    }

    public function isResumable()
    {
        return ($this->resumption_token !== null);
    }

    public function isError()
    {
        return ($this->status == self::STATUS_ERROR);
    }

    public function listRecords()
    {
        $query = array();
        $resumptionToken = $this->resumption_token;
        if ($resumptionToken) {
            // Harvest a list reissue. 
            $query['resumptionToken'] = $resumptionToken;
        } 
        else {
            if ($this->set_spec) {
                // Harvest a set.
                $query['set'] = $this->set_spec;
            } 
            $query['metadataPrefix'] = $this->metadata_prefix;

            // Perform date-selective harvesting if a "from" date is
            // specified.
            if(($startFrom = $this->start_from)) {
                $oaiDate = $this->_datetimeToOai($startFrom);
                $query['from'] = $oaiDate;
                $this->addStatusMessage("Resuming harvest from $oaiDate.");
            }
        }
        
        $client = $this->getRequest();
        $client->setBaseUrl($this->base_url);
        $response = $client->listRecords($query);

        if (isset($response['error'])) {
            if ($response['error']['code'] == 'noRecordsMatch') {
                $this->addStatusMessage("The repository returned no records.");
            } else {
                $this->addStatusMessage($response['error']['code'] . ': '
                    . $response['error']['message']);
            }
        } 
        return $response;
    }

    public function addStatusMessage($message, $messageCode = null, $delimiter = "\n\n")
    {
        if (0 == strlen($this->status_messages)) {
            $delimiter = '';
        }
        $date = $this->_getCurrentDateTime();
        $messageCodeText = $this->_getMessageCodeText($messageCode);
        
        $this->status_messages .= "$delimiter$messageCodeText: $message ($date)";
        $this->save();
    }

    protected function _validate()
    {
        $validators = array(
            'base_url' => new Omeka_Validate_Uri(),
            'metadata_prefix' => new Zend_Validate_InArray(
                array(
                    'oai_dc',
                    'cdwalite',
                    'mets',
                )
            ),
        );
        foreach ($validators as $column => $validator) {
            if (!$validator->isValid($this->$column)) {
                $this->addError($column, join(', ', $validator->getMessages()));
            }
        }
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
     * Converts the given MySQL datetime to an OAI datestamp, for
     * sending dates in OAI-PMH requests.
     *
     * @param string $datestamp MySQL datetime
     * @return string OAI-PMH datestamp
     */
    private function _datetimeToOai($datestamp)
    {
        return gmdate(OaipmhHarvester_Harvest_Abstract::OAI_DATE_FORMAT, strtotime($datestamp));
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