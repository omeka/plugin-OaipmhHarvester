<?php
/**
 * @package OaipmhHarvester
 * @subpackage Controllers
 * @copyright Copyright (c) 2009-2011 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

require_once dirname(__FILE__) . '/../forms/Harvest.php';

/**
 * Index controller
 *
 * @package OaipmhHarvester
 * @subpackage Controllers
 */
class OaipmhHarvester_IndexController extends Omeka_Controller_AbstractActionController
{
    public function init() 
    {
        $this->_helper->db->setDefaultModelName('OaipmhHarvester_Harvest');
    }
    
    /**
     * Prepare the index view.
     * 
     * @return void
     */
    public function indexAction()
    {
        $harvests = $this->_helper->db->getTable('OaipmhHarvester_Harvest')->findAll();
        $this->view->harvests = $harvests;
        $this->view->harvestForm = new OaipmhHarvester_Form_Harvest();
        $this->view->harvestForm->setAction($this->_helper->url('sets'));
    }
    
    /**
     * Prepares the sets view.
     * 
     * @return void
     */
    public function setsAction()
    {
        // Get the available OAI-PMH to Omeka maps, which should correspond to 
        // OAI-PMH metadata formats.
        $maps = $this->_getMaps();
        
        $waitTime = oaipmh_harvester_config('requestThrottleSecs', 5);
        if ($waitTime) {
            $request = new OaipmhHarvester_Request_Throttler(
                new OaipmhHarvester_Request($this->_getParam('base_url')),
                array('wait' => $waitTime)
            );
        } else {
            $request = new OaipmhHarvester_Request(
                $this->_getParam('base_url')
            );
        }
        
        // Catch errors such as "String could not be parsed as XML"
        $extraMsg = 'Please check to be certain the URL is correctly formatted '
                  . 'for OAI-PMH harvesting.';
        try {
            $metadataFormats = $request->listMetadataFormats();
        } catch (Zend_Uri_Exception $e) {
            $errorMsg = "Invalid URL given. $extraMsg";
        } catch (Zend_Http_Client_Exception $e) {
            $errorMsg = $e->getMessage() . " $extraMsg";
        } catch (OaipmhHarvester_Request_ThrottlerException $e) {
            $errorMsg = $e->getMessage();
        }
        if (isset($errorMsg)) {
            $this->_helper->flashMessenger($errorMsg, 'error');
            return $this->_helper->redirector->goto('index');
        }

        /* Compare the available OAI-PMH metadataFormats with the available 
        Omeka maps and extract only those that are common to both.         
        The comparison is made between the metadata schemata, not the prefixes.
        */
        $availableMaps = array_intersect($maps, $metadataFormats);
        
        // For a data provider that uses a resumption token for sets, see: 
        // http://www.ajol.info/oai/
        $response = $request->listSets($this->_getParam('resumption_token'));
        
        // Set the variables to the view object.
        $this->view->availableMaps   = array_combine(
            array_keys($availableMaps),
            array_keys($availableMaps)
        );
        $this->view->sets            = $response['sets'];
        $this->view->resumptionToken = 
            array_key_exists('resumptionToken', $response)
            ? $response['resumptionToken'] : false;
        $this->view->baseUrl         = $this->_getParam('base_url'); // Watch out for injection!
        $this->view->maps            = $maps;
    }
    
    /**
     * Launch the harvest process.
     * 
     * @return void
     */
    public function harvestAction()
    {
        // Only set on re-harvest
        $harvest_id = $this->_getParam('harvest_id');
        
        // If true, this is a re-harvest, all parameters will be the same
        if ($harvest_id) {
            $harvest = $this->_helper->db->getTable('OaipmhHarvester_Harvest')->find($harvest_id);
            
            // Set vars for flash message
            $setSpec = $harvest->set_spec;
            $baseUrl = $harvest->base_url;
            $metadataPrefix = $harvest->metadata_prefix;
          
            // Only on successfully-completed harvests: use date-selective
            // harvesting to limit results.
            if ($harvest->status == OaipmhHarvester_Harvest::STATUS_COMPLETED) {
                $harvest->start_from = $harvest->initiated;
            } else {
                $harvest->start_from = null;
            } 
        } else {
            $baseUrl        = $this->_getParam('base_url');
            $metadataSpec   = $this->_getParam('metadata_spec');
            $setSpec        = $this->_getParam('set_spec');
            $setName        = $this->_getParam('set_name');
            $setDescription = $this->_getParam('set_description');
        
            $metadataPrefix = $metadataSpec;
            $harvest = $this->_helper->db->getTable('OaipmhHarvester_Harvest')->findUniqueHarvest($baseUrl, $setSpec, $metadataPrefix);
         
            if (!$harvest) {
                // There is no existing identical harvest, create a new entry.
                $harvest = new OaipmhHarvester_Harvest;
                $harvest->base_url        = $baseUrl;
                $harvest->set_spec        = $setSpec;
                $harvest->set_name        = $setName;
                $harvest->set_description = $setDescription;
                $harvest->metadata_prefix = $metadataPrefix;
            }
        }
            
        // Insert the harvest.
        $harvest->status          = OaipmhHarvester_Harvest::STATUS_QUEUED;
        $harvest->initiated       = date('Y:m:d H:i:s');
        $harvest->save();
        
        $jobDispatcher = Zend_Registry::get('bootstrap')->getResource('jobs');        
        $jobDispatcher->setQueueName('imports');

        try {
            $jobDispatcher->sendLongRunning('OaipmhHarvester_Job', array('harvestId' => $harvest->id));
        } catch (Exception $e) {
            $harvest->status = OaipmhHarvester_Harvest::STATUS_ERROR;
            $harvest->addStatusMessage(
                get_class($e) . ': ' . $e->getMessage(),
                OaipmhHarvester_Harvest_Abstract::MESSAGE_CODE_ERROR
            );
            throw $e;
        }

        if ($setSpec) {
            $message = "Set \"$setSpec\" is being harvested using \"$metadataPrefix\". This may take a while. Please check below for status.";
        } else {
            $message = "Repository \"$baseUrl\" is being harvested using \"$metadataPrefix\". This may take a while. Please check below for status.";
        }
        if ($harvest->start_from) {
            $message = $message." Harvesting is continued from $harvest->start_from .";
        }
        $this->_helper->flashMessenger($message, 'success');
        return $this->_helper->redirector->goto('index');
    }
    
    /**
     * Prepare the status view.
     * 
     * @return void
     */
    public function statusAction()
    {
        $harvestId = $this->_getParam('harvest_id');
        $harvest = $this->_helper->db->getTable('OaipmhHarvester_Harvest')->find($harvestId);
        $this->view->harvest = $harvest;
    }
    
    /**
     * Delete all items created during a harvest.
     * 
     * @return void
     */
    public function deleteAction()
    {
        // Throw if harvest does not exist or access is disallowed.
        $harvestId = $this->_getParam('id');
        $harvest = $this->_helper->db->getTable('OaipmhHarvester_Harvest')->find($harvestId);
        $jobDispatcher = Zend_Registry::get('bootstrap')->getResource('jobs');        
        $jobDispatcher->setQueueName('imports');
        $jobDispatcher->sendLongRunning('OaipmhHarvester_DeleteJob',
            array(
                'harvestId' => $harvest->id,
            )
        );
        $msg = 'Harvest has been marked for deletion.';
        $this->_helper->flashMessenger($msg, 'success');
        return $this->_helper->redirector->goto('index');
    }
    
    /**
     * Get the available OAI-PMH to Omeka maps, which should correspond to 
     * OAI-PMH metadata formats.
     * 
     * @return array
     */
    private function _getMaps()
    {
        $dir = new DirectoryIterator(OAIPMH_HARVESTER_MAPS_DIRECTORY);
        $maps = array();
        foreach ($dir as $dirEntry) {
            if ($dirEntry->isFile() && !$dirEntry->isDot()) {
                $filename = $dirEntry->getFilename();
                $pathname = $dirEntry->getPathname();
                if (preg_match('/^(.+)\.php$/', $filename, $match) 
                    && $match[1] != 'Abstract'
                ) {
                    // Get and set only the name of the file minus the extension.
                    require_once($pathname);
                    $class = "OaipmhHarvester_Harvest_${match[1]}";
                    $metadataSchema = constant("$class::METADATA_SCHEMA");
                    $metadataPrefix = constant("$class::METADATA_PREFIX");
                    $maps[$metadataPrefix] = $metadataSchema;
                }
            }
        }
        return $maps;
    }
}
