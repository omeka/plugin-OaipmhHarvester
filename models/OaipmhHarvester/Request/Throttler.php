<?php

/**
 * Decorator for Request that throttles requests based on whether or not
 *
 * @package OaipmhHarvester
 */
class OaipmhHarvester_Request_Throttler
{
    /**
     * @var array
     */
    private $_options = array();
    
    /**
     * @var OaipmhHarvester_Request
     */
    private $_request;

    /**
     * Constructor
     *
     * @param OaipmhHarvester_Request $request
     * @param array $options Currently includes 'wait', which is the required
     * wait time (in seconds) between requests.
     */
    public function __construct($request, $options = array())
    {
        $this->_request = $request;
        $this->_options = $options;
        $this->_session = new Zend_Session_Namespace('OaipmhHarvester_Request_Throttler');
        if (!isset($this->_session->requests)) {
            $this->_session->requests = array();
        }
    }

    /**
     * Wrapper for Request methods to check whether request takes place after
     * the required interval since the last request. If not, an exception is
     * thrown. 
     *
     * @throws OaipmhHarvester_Request_ThrottlerException
     * @param string $m
     * @param array $a
     */
    public function __call($m, $a)
    {
        $waitSecs = $this->_options['wait'];
        if ($this->_requestExceedsThreshold($m, $waitSecs)) {
            throw new OaipmhHarvester_Request_ThrottlerException(
                "Too many requests. Please wait $waitSecs seconds and try again."
            );
        }
        $this->_storeRequestTime($m);
        return call_user_func_array(array($this->_request, $m), $a);                
    }

    /**
     * Return whether or not the current request has been made before the
     * required number of seconds has passed since the last request.
     *
     * @param string $m Method name indicating the type of request made.
     * @param integer $waitSecs Number of seconds that must pass before another
     * request can be made.
     */
    private function _requestExceedsThreshold($m, $waitSecs)
    {
        if (!array_key_exists($m, $this->_session->requests)) {
            return false;
        }
        $lastRequest = $this->_session->requests[$m];        
        if (!$lastRequest) {
            return false;
        }
        return (time() < ($waitSecs + $lastRequest));
    }

    /**
     * Store the current request time in the session.
     */
    private function _storeRequestTime($m)
    {
        $this->_session->requests[$m] = time();
    }
}