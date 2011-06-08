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
        $harvests = $this->getTable('OaipmhHarvester_Harvest')->findAllHarvests();
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
        
        // Get the available metadata formats from the data provider.
        $baseUrl = trim($_POST['base_url']);
        $requestArguments = array('verb' => 'ListMetadataFormats');
        
        // Catch errors such as "String could not be parsed as XML"
        try {
            $oaipmh = new OaipmhHarvester_Xml($baseUrl, $requestArguments);
            $oaipmh->getOaipmh();
        } catch (Zend_Http_Client_Exception $e) {
            $this->flashError($e->getMessage());
            $this->redirect->goto('index');
        } catch (Exception $e) {
            if (OaipmhHarvester_Xml::ERROR_XML_PARSE == $e->getMessage()) {
                $this->flashError("Response error: " . $e->getMessage());
                $this->redirect->goto('index');
            } else {
                throw $e;
            }
        }
        
        /* Compare the available OAI-PMH metadataFormats with the available 
        Omeka maps and extract only those that are common to both. It's 
        important to consider that some repositories don't provide repository
        -wide metadata formats. Instead they only provide record level 
        metadata formats. Oai_dc is mandatory for all records, so if a
        repository doesn't provide metadata formats using 
        ListMetadataFormats, only expose the oai_dc prefix. For a data 
        provider that doesn't offer repository-wide metadata formats, see: 
        http://www.informatik.uni-stuttgart.de/cgi-bin/OAI/OAI.pl
        
        The comparison is made between the metadata schemata, not the prefixes.
        */
        $availableMaps = array();
        if (isset($oaipmh->getOaipmh()->ListMetadataFormats)) {
            $metadataFormats = $oaipmh->getOaipmh()->ListMetadataFormats->metadataFormat;
            foreach ($metadataFormats as $metadataFormat) {
                $metadataPrefix = trim((string) $metadataFormat->metadataPrefix);
                $schema = trim((string) $metadataFormat->schema);
                foreach($maps as $mapClass => $mapSchema) {
                    if($mapSchema == $schema) {
                        // Encode the class and prefix together with a pipe.
                        $availableMaps["$mapClass|$metadataPrefix"] = $metadataPrefix;
                        break;
                    }
                }
            }
        }
        else {
            if (in_array('http://www.openarchives.org/OAI/2.0/oai_dc.xsd', $maps)) {
                $availableMaps["OaipmhHarvester_Harvest_OaiDc|oai_dc"] = 'oai_dc';
            }
        }
        
        // Get the sets from the data provider.
        $requestArguments = array('verb' => 'ListSets');
        
        // If a resumption token exists, process it. For a data provider that 
        // uses a resumption token for sets, see: http://www.ajol.info/oai/
        if (isset($_POST['resumption_token'])) {
            $requestArguments['resumptionToken'] = $_POST['resumption_token'];
        }
        
        try {
            $oaipmh = new OaipmhHarvester_Xml($baseUrl, $requestArguments);
        
            // Handle returned errors, such as "noSetHierarchy". For a data provider 
            // that has no set hierarchy, see: http://solarphysics.livingreviews.org/register/oai
            if ($oaipmh->isError()) {
                $error     = (string) $oaipmh->getError();
                $errorCode = (string) $oaipmh->getErrorCode();
            
                // If the error code is "noSetHierarchy" set the sets to an empty array to 
                // indicate that the repository does not have a set hierarchy.
                if ($errorCode == OaipmhHarvester_Xml::ERROR_CODE_NO_SET_HIERARCHY) {
                    $sets = array();
                } else {
                    $this->flashError("$errorCode: $error");
                    $this->redirect->goto('index');
                }
            
            // If no error was returned, it is a valid ListSets response.
            } else {
                $sets = $oaipmh->getOaipmh()->ListSets->set;
            }
            
            // Set the resumption token, if any.
            if (isset($oaipmh->getOaipmh()->ListSets->resumptionToken)) {
                $resumptionToken = $oaipmh->getOaipmh()->ListSets->resumptionToken;
            } else {
                $resumptionToken = false;
            }
        } catch(Exception $e) {
            // If we're here, the provider didn't even respond with valid XML.
            // Try to continue with no sets.
            $sets = array();
        }
        
        // Set the variables to the view object.
        $this->view->availableMaps   = $availableMaps;
        $this->view->sets            = $sets;
        $this->view->resumptionToken = isset($resumptionToken) ? $resumptionToken : false;
        $this->view->baseUrl         = $baseUrl;
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
        $harvest_id     = $_POST['harvest_id'];
        
        $baseUrl        = $_POST['base_url'];
        // metadataSpec is of the form "class|prefix", explode on pipe to get
        // the individual items, 0 => class, 1 => prefix
        $metadataSpec   = explode('|', $_POST['metadata_spec']);
        $setSpec        = isset($_POST['set_spec']) ? $_POST['set_spec'] : null;
        $setName        = isset($_POST['set_name']) ? $_POST['set_name'] : null;
        $setDescription = isset($_POST['set_description']) ? $_POST['set_description'] : null;
        
        $metadataClass = $metadataSpec[0];
        $metadataPrefix = $metadataSpec[1];
        
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
            // If $harvest is not null, use the existing harvest record.
            $harvest = $this->getTable('OaipmhHarvester_Harvest')->findUniqueHarvest($baseUrl, $setSpec, $metadataPrefix);
        
            if(!$harvest) {
                // There is no existing identical harvest, create a new entry.
                $harvest = new OaipmhHarvester_Harvest;
                $harvest->base_url        = $baseUrl;
                $harvest->set_spec        = $setSpec;
                $harvest->set_name        = $setName;
                $harvest->set_description = $setDescription;
                $harvest->metadata_prefix = $metadataPrefix;
                $harvest->metadata_class  = $metadataClass;
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
        $harvestId = $_GET['harvest_id'];
        
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
                    $object = new $class(null, null);
                    $metadataSchema = $object->getMetadataSchema();
                    $metadataPrefix = $object->getMetadataPrefix();
                    $maps[$class] = $metadataSchema;
                }
            }
        }
        return $maps;
    }
}
