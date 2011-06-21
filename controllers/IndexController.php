<?php
/**
 * @package OaipmhHarvester
 * @subpackage Controllers
 * @copyright Copyright (c) 2009 Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

/**
 * Index controller
 *
 * @package OaipmhHarvester
 * @subpackage Controllers
 */
class OaipmhHarvester_IndexController extends Omeka_Controller_Action
{
    /**
     * Prepare the index view.
     * 
     * @return void
     */
    public function indexAction()
    {
        $harvests = $this->getTable('OaipmhHarvester_Harvest')->findAll();
        $this->view->harvests = $harvests;
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
        
        $request = new OaipmhHarvester_Request_Throttler(
            new OaipmhHarvester_Request($this->_getParam('base_url')),
            array('wait' => 5)
        );
        
        // Catch errors such as "String could not be parsed as XML"
        try {
            $metadataFormats = $request->listMetadataFormats();
        } catch (Zend_Http_Client_Exception $e) {
            $this->flashError($e->getMessage());
            $this->_helper->redirector->goto('index');
        } catch (OaipmhHarvester_Request_ThrottlerException $e) {
            $this->flashError($e->getMessage());
            $this->_helper->redirector->goto('index');
        } catch (Exception $e) {
            if (OaipmhHarvester_Xml::ERROR_XML_PARSE == $e->getMessage()) {
                $this->flashError("Response error: " . $e->getMessage());
                $this->redirect->goto('index');
            } else {
                throw $e;
            }
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
        if($harvest_id) {
            $harvest = $this->getTable('OaipmhHarvester_Harvest')->find($harvest_id);
            
            // Set vars for flash message
            $setSpec = $harvest->set_spec;
            $baseUrl = $harvest->base_url;
            $metadataPrefix = $harvest->metadata_prefix;
            
            // Only on successfully-completed harvests: use date-selective
            // harvesting to limit results.
            if($harvest->status == OaipmhHarvester_Harvest::STATUS_COMPLETED)
                $harvest->start_from = $harvest->initiated;
            else 
                $harvest->start_from = null;
        }
        else {
            $baseUrl        = $this->_getParam('base_url');
            $metadataSpec   = $this->_getParam('metadata_spec');
            $setSpec        = $this->_getParam('set_spec');
            $setName        = $this->_getParam('set_name');
            $setDescription = $this->_getParam('set_description');
        
            $metadataPrefix = $metadataSpec;
            $harvest = $this->getTable('OaipmhHarvester_Harvest')
                ->findUniqueHarvest($baseUrl, $setSpec, $metadataPrefix);
        
            if(!$harvest) {
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
        $harvest->forceSave();
        
        $jobDispatcher = Zend_Registry::get('job_dispatcher');
        $jobDispatcher->setQueueName('imports');
        $jobDispatcher->send('OaipmhHarvester_Job', array('harvestId' => $harvest->id));
        
        if ($setSpec) {
            $message = "Set \"$setSpec\" is being harvested using \"$metadataPrefix\". This may take a while. Please check below for status.";
        } else {
            $message = "Repository \"$baseUrl\" is being harvested using \"$metadataPrefix\". This may take a while. Please check below for status.";
        }
        if($harvest->start_from)
            $message = $message." Harvesting is continued from $harvest->start_from .";
        
        $this->flashSuccess($message);
        
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
        
        $harvest = $this->getTable('OaipmhHarvester_Harvest')->find($harvestId);
        
        $this->view->harvest = $harvest;
    }
    
    /**
     * Delete all items created during a harvest.
     * 
     * @return void
     */
    public function deleteAction()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->_helper->redirector->goto('index'); 
        }
        $this->_helper->db->setDefaultModelName('OaipmhHarvester_Harvest');
        // Throw if harvest does not exist or access is disallowed.
        $harvest = $this->findById();
        $jobDispatcher = Zend_Registry::get('job_dispatcher');
        $jobDispatcher->send('OaipmhHarvester_DeleteJob',
            array(
                'harvestId' => $harvest->id,
            )
        );
        $this->flashSuccess(
            'Harvest has been successfully marked for deletion.'
        );
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
                if(preg_match('/^(.+)\.php$/', $filename, $match) && $match[1] != 'Abstract') {
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
