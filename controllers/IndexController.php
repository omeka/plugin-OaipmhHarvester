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
        $harvests = $this->getTable('OaipmhHarvesterHarvest')->findAllHarvests();
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
        } catch (Exception $e) {
            $this->flash($e->getMessage());
            $this->redirect->goto('index');
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
                $metadataPrefix = (string) $metadataFormat->metadataPrefix;
                $schema = (string) $metadataFormat->schema;
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
        
        $oaipmh = new OaipmhHarvester_Xml($baseUrl, $requestArguments);
        
        // Handle returned errors, such as "noSetHierarchy". For a data provider 
        // that has no set hierarchy, see: http://solarphysics.livingreviews.org/register/oai
        if ($oaipmh->isError()) {
            $error     = (string) $oaipmh->getError();
            $errorCode = (string) $oaipmh->getErrorCode();
            
            // If the error code is "noSetHierarchy" set the sets to false to 
            // indicate that the repository does not have a set hierarchy.
            if ($errorCode == OaipmhHarvester_Xml::ERROR_CODE_NO_SET_HIERARCHY) {
                $sets = false;
            } else {
                $this->flash("$errorCode: $error");
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
        
        // Set the variables to the view object.
        $this->view->availableMaps   = $availableMaps;
        $this->view->sets            = $sets;
        $this->view->resumptionToken = $resumptionToken;
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
            $harvest = $this->getTable('OaipmhHarvesterHarvest')->find($harvest_id);
            
            // Set vars for flash message
            $setSpec = $harvest->set_spec;
            $baseUrl = $harvest->base_url;
            $metadataPrefix = $harvest->metadata_prefix;
            
            // Only on successfully-completed harvests: use date-selective
            // harvesting to limit results.
            if($harvest->status == OaipmhHarvesterHarvest::STATUS_COMPLETED)
                $harvest->start_from = $harvest->initiated;
            else 
                $harvest->start_from = null;
        }
        else {
            // If $harvest is not null, use the existing harvest record.
            $harvest = $this->getTable('OaipmhHarvesterHarvest')->findUniqueHarvest($baseUrl, $setSpec, $metadataPrefix);
        
            if(!$harvest) {
                // There is no existing identical harvest, create a new entry.
                $harvest = new OaipmhHarvesterHarvest;
                $harvest->base_url        = $baseUrl;
                $harvest->set_spec        = $setSpec;
                $harvest->set_name        = $setName;
                $harvest->set_description = $setDescription;
                $harvest->metadata_prefix = $metadataPrefix;
                $harvest->metadata_class  = $metadataClass;
            }
        }
            
        // Insert the harvest.
        $harvest->status          = OaipmhHarvesterHarvest::STATUS_STARTING;
        $harvest->initiated       = date('Y:m:d H:i:s');
        $harvest->save();
        
        // Set the command arguments.
        $phpCommandPath    = get_option('oaipmh_harvester_php_path');
        $bootstrapFilePath = $this->_getBootstrapFilePath();
        $harvestId         = escapeshellarg($harvest->id);
        
        // Set the command and run the script in the background.
        $command = "$phpCommandPath $bootstrapFilePath -h $harvestId";
        $pid = $this->_fork($command);
        
        // Set the PID after the background process is started.
        // Save twice to assure the process has access to the data it needs.
        $harvest->pid = $pid;
        $harvest->save();
        
        if ($setSpec) {
            $message = "Set \"$setSpec\" is being harvested using \"$metadataPrefix\". This may take a while. Please check below for status.";
        } else {
            $message = "Repository \"$baseUrl\" is being harvested using \"$metadataPrefix\". This may take a while. Please check below for status.";
        }
        if($harvest->start_from)
            $message = $message." Harvesting is continued from $harvest->start_from .";
        
        $this->flashSuccess($message);
        
        $this->redirect->goto('index');
        exit;
    }
    
    /**
     * Prepare the status view.
     * 
     * @return void
     */
    public function statusAction()
    {
        $harvestId = $_GET['harvest_id'];
        
        $harvest = $this->getTable('OaipmhHarvesterHarvest')->find($harvestId);
        
        $this->view->harvest = $harvest;
    }
    
    /**
     * Delete all items created during a harvest.
     * 
     * @return void
     */
    public function deleteAction()
    {
        $harvestId = $_GET['harvest_id'];
        
        $harvest = $this->getTable('OaipmhHarvesterHarvest')->find($harvestId);
        
        $records = $this->getTable('OaipmhHarvesterRecord')->findByHarvestId($harvest->id);
        
        // Delete items if they exist.
        foreach ($records as $record) {
            if ($record->item_id) {
                $item = $this->getTable('Item')->find($record->item_id);
                $item->delete();
                $record->delete();
            }
        }
        
        // Delete collection if exists.
        if ($harvest->collection_id) {
            $collection = $this->getTable('Collection')->find($harvest->collection_id);
            $collection->delete();
            $harvest->collection_id = null;
        }
        
        $harvest->status = OaipmhHarvesterHarvest::STATUS_DELETED;
        $statusMessage = 'All items created for this harvest were deleted on ' 
                       . date('Y-m-d H:i:s');
        $harvest->status_messages = strlen($harvest->status_messages) == 0 
                                  ? $statusMessage 
                                  : "\n\n" . $statusMessage;
        $harvest->save();
        
        $this->flash('All items created for the harvest were deleted.');
        
        $this->redirect->goto('index');
        exit;
    }
    
    /**
     * Kill the background process for a harvest if it is still running.
     */
    public function killAction()
    {
        $harvestId = $_POST['harvest_id'];
        $harvest = $this->getTable('OaipmhHarvesterHarvest')->find($harvestId);
        
        $pid = $harvest->pid;
        
        if($pid) {
            if($harvest->status == OaipmhHarvesterHarvest::STATUS_STARTING ||
               $harvest->status == OaipmhHarvesterHarvest::STATUS_IN_PROGRESS)
                {
                    exec("kill -9 $pid");
                    $harvest->pid = null;
                    $harvest->status = OaipmhHarvesterHarvest::STATUS_KILLED;
                    $statusMessage = 'This harvest was killed by an administrator on ' 
                                  . date('Y-m-d H:i:s');
                    $harvest->status_messages = strlen($harvest->status_messages) == 0 
                                             ? $statusMessage 
                                             : "\n\n" . $statusMessage;
                    $harvest->save(); 
                    $this->flash("Harvest process $pid was killed.");
               }
        }
        $this->redirect->goto('index');
        exit;
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
    
    /**
     * Get the path to the bootstrap file.
     * 
     * @return string
     */
    private function _getBootstrapFilePath()
    {
        return OAIPMH_HARVESTER_PLUGIN_DIRECTORY
             . DIRECTORY_SEPARATOR 
             . 'bootstrap.php';
    }
    
    /**
     * Launch a background process, returning control to the foreground.
     * 
     * @link http://www.php.net/manual/en/ref.exec.php#70135
     * @return int The background process' PID
     */
    private function _fork($command) {
        return exec("$command > /dev/null 2>&1 & echo $!");
    }
}