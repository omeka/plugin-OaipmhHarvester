<?php
/**
 * @package OaipmhHarvester
 * @subpackage Models
 * @copyright Copyright (c) 2009-2011 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

/**
 * Abstract class on which all other metadata format maps are based.
 *
 * @package OaipmhHarvester
 * @subpackage Models
 */
abstract class OaipmhHarvester_Harvest_Abstract
{
    /**
     * Notice message code, used for status messages.
     */
    const MESSAGE_CODE_NOTICE = 1;

    /**
     * Warning message code, used for status messages.
     */
    const MESSAGE_CODE_WARNING = 3;

    /**
     * Error message code, used for status messages.
     */
    const MESSAGE_CODE_ERROR = 2;
    
    /**
     * Date format for OAI-PMH requests.
     * Only use day-level granularity for maximum compatibility with
     * repositories.
     */
    const OAI_DATE_FORMAT = 'Y-m-d';
    
    /**
     * @var OaipmhHarvester_Harvest The OaipmhHarvester_Harvest object model.
     */
    private $_harvest;
    
    /**
     * @var SimpleXMLIterator The current, cached SimpleXMLIterator record object.
     */
    private $_record;

    private $_options = array(
        'public' => false,
        'featured' => false,
    );

    /**
     * Class constructor.
     * 
     * Prepares the harvest process.
     * 
     * @param OaipmhHarvester_Harvest $harvest The OaipmhHarvester_Harvest object 
     * model
     * @return void
     */
    public function __construct($harvest)
    {   
        // Set an error handler method to record run-time warnings (non-fatal 
        // errors).
        set_error_handler(array($this, 'errorHandler'), E_WARNING);
        
        $this->_harvest = $harvest;
   
    }

    public function setOption($key, $value)
    {
        $this->_options[$key] = $value;
    }

    public function getOption($key)
    {
        return $this->_options[$key];
    }
    
    /**
     * Abstract method that all class extentions must contain.
     * 
     * @param SimpleXMLIterator The current record object
     */
    abstract protected function _harvestRecord($record);

    /**
     * Return the first registered item type of a record, if any.
     *
     * @param array $record
     * @return ItemType|null
     */
    private function _getItemType($record)
    {
        if (isset($record['elementTexts']['Dublin Core']['Type'])) {
            $db = get_db();
            $quotedTypes = array();
            foreach ($record['elementTexts']['Dublin Core']['Type'] as $type) {
                $quotedTypes[] = $db->quote($type['text']);
            }
            $quotedTypes = implode(',', $quotedTypes);

            $sql = "
                SELECT id
                FROM `{$db->ItemType}`
                WHERE `name` IN ($quotedTypes)
                ORDER BY FIELD(`name`, $quotedTypes)
                LIMIT 1;";
            $result = $db->fetchOne($sql);

            if ($result) {
                return get_record_by_id('ItemType', $result);
            }
        }
    }

    /**
     * Checks whether the current record has already been harvested, and
     * returns the record if it does.
     *
     * @param SimpleXMLIterator record to be harvested
     * @return OaipmhHarvester_Record|false The model object of the record,
     *      if it exists, or false otherwise.
     */
    private function _recordExists($xml)
    {   
        $identifier = trim((string)$xml->header->identifier);
        
        /* Ideally, the OAI identifier would be globally-unique, but for
           poorly configured servers that might not be the case.  However,
           the identifier is always unique for that repository, so given
           already-existing identifiers, check against the base URL.
        */
        $table = get_db()->getTable('OaipmhHarvester_Record');
        $record = $table->findBy(
            array(
                'base_url' => $this->_harvest->base_url,
                'set_spec' => $this->_harvest->set_spec,
                'metadata_prefix' => $this->_harvest->metadata_prefix,
                'identifier' => (string)$identifier,
            ),
            1,
            1
        );
        
        // Ugh, gotta be a better way to do this.
        if ($record) {
            $record = $record[0];
        }
        return $record;
    }

    private function _isIterable($var)
    {
        return (is_array($var) || $var instanceof Traversable);
    }

    /**
     * Recursive method that loops through all requested records
     * 
     * This method hands off mapping to the class that extends off this one and 
     * recurses through all resumption tokens until the response is completed.
     * 
     * @param string|false $resumptionToken
     * @return string|boolean Resumption token if one exists, otherwise true
     * if the harvest is finished.
     */
    private function _harvestRecords()
    {

        // Iterate through the records and hand off the mapping to the classes 
        // inheriting from this class.
        $response = $this->_harvest->listRecords();
        if ($this->_isIterable($response['records'])) {
            // Sometimes, the files are not available. To avoid potential errors
            // and to allows harvest of next records, a try/catch is needed.
            // The item will be updated during next harvest.
            foreach ($response['records'] as $record) {
                try {
                    $this->_harvestLoop($record);
                } catch (Omeka_File_Ingest_Exception $e) {
                    $this->_continueWithWarning($e);
                    _log('[OaipmhHarvester] ' . $e->getMessage(), Zend_Log::WARN);
                } catch (Exception $e) {
                    $this->_stopWithError($e);
                    // For real errors need to be logged and debugged.
                    _log($e, Zend_Log::ERR);
                }
            }
        } else {
            $this->_addStatusMessage("No records were found.");
        }
        
        $resumptionToken = @$response['resumptionToken'];
        if ($resumptionToken) {
            $this->_addStatusMessage("Received resumption token: $resumptionToken");
        } else {
            $this->_addStatusMessage("Did not receive a resumption token.");
        }

        return ($resumptionToken ? $resumptionToken : true);
    }

    /**
     * @internal Bad names for all of these methods, fixme.
     */
    private function _harvestLoop($record)
    {
        // Ignore (skip over) deleted records.
        if ($this->isDeletedRecord($record)) {
            return;
        }

        $harvestedRecord = $this->_harvestRecord($record);
        if (empty($harvestedRecord)
                || (empty($harvestedRecord['itemMetadata'])
                    && empty($harvestedRecord['elementTexts'])
                    && empty($harvestedRecord['fileMetadata'])
                )
            ) {
            return;
        }

        $itemType = $this->_getItemType($harvestedRecord);
        if (!empty($itemType)) {
            // The name is used to simplify potential other checks.
            unset($harvestedRecord['itemMetadata']['item_type_id']);
            $harvestedRecord['itemMetadata']['item_type_name'] = $itemType->name;
        }

        // Set some default values for the harvested record.
        if (!empty($fileMetadata['files'])) {
            // The default file transfer type is URL.
            if (empty($harvestedRecord['fileMetadata'][Builder_Item::FILE_TRANSFER_TYPE])) {
                $harvestedRecord['fileMetadata'][Builder_Item::FILE_TRANSFER_TYPE] = 'Url';
            }

            // The default option is ignore invalid files.
            if (!isset($harvestedRecord['fileMetadata'][Builder_Item::FILE_INGEST_OPTIONS]['ignore_invalid_files'])) {
                $harvestedRecord['fileMetadata'][Builder_Item::FILE_INGEST_OPTIONS]['ignore_invalid_files'] = true;
            }
        }

        // Cache the record for later use.
        $this->_record = $record;

        // Check if the record has already been harvested.
        $existingRecord = $this->_recordExists($record);
        if ($existingRecord) {
            // If datestamp has changed, update the record.
            if ($existingRecord->datestamp != $record->header->datestamp) {
                $item = $this->_updateItem(
                    $existingRecord,
                    $harvestedRecord['elementTexts'],
                    $harvestedRecord['fileMetadata']);
                $performed = 'updated';
            }
            // Ignore the update.
            else {
                $item = $existingRecord;
                $performed = 'skipped';
            }
        }
        // This is a new item.
        else {
            $item = $this->_insertItem(
                $harvestedRecord['itemMetadata'],
                $harvestedRecord['elementTexts'],
                $harvestedRecord['fileMetadata']
            );
            $performed = 'inserted';
        }

        $this->_harvestRecordSpecific($item, $harvestedRecord, $performed);

        // Release the Item object from memory.
        release_object($item);
    }

    /**
     * Ingest specific data, specialy for plugins that don't use elements.
     *
     * @internal A method is used, not a hook, because it depends of mapping.
     *
     * @param Record $record
     * @param array $harvestedRecord
     * @param string $performed Can be "inserted", "updated" or "skipped".
     * @return void
     */
    protected function _harvestRecordSpecific($record, $harvestedRecord, $performed)
    {
    }

    /**
     * Return whether the record is deleted
     * 
     * @param SimpleXMLIterator The record object
     * @return bool
     */
    public function isDeletedRecord($record)
    {
        if (isset($record->header->attributes()->status) 
            && $record->header->attributes()->status == 'deleted') {
            return true;
        }
        return false;
    }
    
    /**
     * Insert a record into the database.
     * 
     * @param Item $item The item object corresponding to the record.
     * @return void
     */
    private function _insertRecord($item)
    {
        $record = new OaipmhHarvester_Record;
        
        $record->harvest_id = $this->_harvest->id;
        $record->item_id    = $item->id;
        $record->identifier = (string) $this->_record->header->identifier;
        $record->datestamp  = (string) $this->_record->header->datestamp;
        $record->save();
        
        release_object($record);
    }
    
    /**
     * Update a record in the database with information from this harvest.
     * 
     * @param OaipmhHarvester_Record The model object corresponding to the record.
     */
    private function _updateRecord(OaipmhHarvester_Record $record)
    {   
        $record->datestamp  = (string) $this->_record->header->datestamp;
        $record->save();
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
    
    /**
     * Template method.
     * 
     * May be overwritten by classes that extend of this one. This method runs 
     * once, prior to record iteration.
     * 
     * @see self::__construct()
     */
    protected function _beforeHarvest()
    {
    }
    
    /**
     * Template method.
     * 
     * May be overwritten by classes that extend of this one. This method runs 
     * once, after record iteration.
     * 
     * @see self::__construct()
     */
    protected function _afterHarvest()
    {
    }
    
    /**
     * Insert a collection.
     * 
     * @see insert_collection()
     * @param array $metadata
     * @return Collection
     */
    final protected function _insertCollection($metadata = array())
    {
        $collection = null;

        // If collection_id is not empty, use the existing collection and don't
        // create a new one.
        $collectionId = $this->_harvest->collection_id;
        if ($collectionId) {
            $collection = get_db()->getTable('Collection')->find($collectionId);
        }

        // The collection may not be created or may be removed.
        if (empty($collection)) {
            // There must be a collection name, so if there is none, like when the
            // harvest is repository-wide, set it to the base URL.
            if (!isset($metadata['elementTexts']['Dublin Core']['Title']) ||
                    trim($metadata['elementTexts']['Dublin Core']['Title'][0]['text']) == '') {
                $metadata['elementTexts']['Dublin Core']['Title'][] =
                    array('text' => $this->_harvest->base_url, 'html' => false);
            }

            $collection = insert_collection($metadata['metadata'],$metadata['elementTexts']);

            // Remember to set the harvest's collection ID once it has been saved.
            $this->_harvest->collection_id = $collection->id;
            $this->_harvest->save();
        }

        return $collection;
    }
    
    /**
     * Convenience method for inserting an item and its files.
     * 
     * Method used by map writers that encapsulates item and file insertion. 
     * Items are inserted first, then files are inserted individually. This is 
     * done so Item and File objects can be released from memory, avoiding 
     * memory allocation issues.
     * 
     * @see insert_item()
     * @see insert_files_for_item()
     * @param mixed $metadata Item metadata
     * @param mixed $elementTexts The item's element texts
     * @param mixed $fileMetadata The item's file metadata
     * @return Item The inserted item.
     */
    final protected function _insertItem(
        $metadata = array(), 
        $elementTexts = array(), 
        $fileMetadata = array()
    ) {
        // Insert the item.
        $item = insert_item($metadata, $elementTexts);
        
        // Insert the record after the item is saved. The idea here is that the 
        // OaipmhHarvester_Records table should only contain records that have 
        // corresponding items.
        $this->_insertRecord($item);

        $this->_insertFiles($item, $fileMetadata);

        return $item;
    }
    
    /**
     * Convenience method for inserting an item and its files.
     * 
     * Method used by map writers that encapsulates item and file insertion. 
     * Items are inserted first, then files are inserted individually. This is 
     * done so Item and File objects can be released from memory, avoiding 
     * memory allocation issues.
     * 
     * @see insert_item()
     * @see insert_files_for_item()
     * @param OaipmhHarvester_Record $record Contains the ID of item to update
     * @param mixed $elementTexts The item's element texts
     * @param mixed $fileMetadata The item's file metadata
     * @return Item The updated item.
     */
    final protected function _updateItem(
        $record, 
        $elementTexts = array(), 
        $fileMetadata = array()
    ) {
        // Update the item
        $item = update_item(
            $record->item_id,
            array('overwriteElementTexts' => true),
            $elementTexts);

        // With default functions, old elements may not be removed. This process
        // allows to delete all of them, for the item and each attached file.
        if ($this->_harvest->update_metadata != OaipmhHarvester_Harvest::UPDATE_METADATA_KEEP) {
            $this->_updateMetadata($item, $elementTexts);
        }

        $this->_insertFiles($item, $fileMetadata);

        // Warning: The core function "update_item" above adds new files even
        // when they have been already ingested. So duplicates should be checked
        // somewhere. Furthermore, old files are not removed. Three possible
        // positions:
        // - add a new ingest option in Builder_Item::addFiles(), but it implies
        // to change Omeka core;
        // - add a hook "before_save_item", but it will be used even outside of
        // this plugin;
        // - add the check just here, which is the simplest, even if this is not
        // optimal, and it's compliant with the logical of OAI-PMH (cf. the
        // option overwriteElementsTexts). Nevertheless, the choice is let to
        // the user.
        if (in_array($this->_harvest->update_files, array(
                OaipmhHarvester_Harvest::UPDATE_FILES_FULL,
                OaipmhHarvester_Harvest::UPDATE_FILES_DEDUPLICATE,
            ))) {
            $this->_deduplicateFiles($item);
            // A reload is needed because the deduplication uses a direct query.
            release_object($item);
            $item = get_db()->getTable('Item')->find($record->item_id);
        }

        if (in_array($this->_harvest->update_files, array(
                OaipmhHarvester_Harvest::UPDATE_FILES_FULL,
                OaipmhHarvester_Harvest::UPDATE_FILES_REMOVE,
            ))) {
            $this->_deleteRemovedFiles($item, $fileMetadata);
            // A reload is needed because the deduplication uses a direct query.
            release_object($item);
            $item = get_db()->getTable('Item')->find($record->item_id);
        }

        // Reorder can be done only if files have been updated. Anyway, the
        // order is generally already right.
        // TODO Is it needed for other updates? The builder adds files one by
        // one and the older ones are removed.
        if ($this->_harvest->update_files == OaipmhHarvester_Harvest::UPDATE_FILES_FULL) {
            $this->_orderFiles($item, $fileMetadata);
        }

        if ($this->_harvest->update_metadata != OaipmhHarvester_Harvest::UPDATE_METADATA_KEEP) {
            $this->_updateFilesMetadata($item, $fileMetadata);
        }

        // Update the datestamp stored in the database for this record.
        $this->_updateRecord($record);

        return $item;
    }

    /**
     * Insert files one by one to preserve memory.
     *
     * If there are files, insert one file at a time so the file objects can be
     * released individually.
     *
     * @param Item $item
     * @param mixed $fileMetadata The item's file metadata
     */
    protected function _insertFiles($item, $fileMetadata)
    {
        if (empty($fileMetadata['files'])) {
            return;
        }

        // The default file transfer type is URL.
        $fileTransferType = empty($fileMetadata[Builder_Item::FILE_TRANSFER_TYPE])
            ? 'Url'
            : $fileMetadata[Builder_Item::FILE_TRANSFER_TYPE];
        // The default option is ignore invalid files.
        $fileOptions = empty($fileMetadata[Builder_Item::FILE_INGEST_OPTIONS])
            ? array('ignore_invalid_files' => true)
            : $fileMetadata[Builder_Item::FILE_INGEST_OPTIONS];

        // Prepare the files value for one-file-at-a-time iteration.
        $files = array($fileMetadata['files']);

        foreach ($files as $file) {
            if (empty($file)) {
                continue;
            }
            $fileOb = insert_files_for_item(
                $item,
                $fileTransferType,
                $file,
                $fileOptions);
            $fileObject= $fileOb;
            if (!empty($file['metadata'])) {
                $fileObject->addElementTextsByArray($file['metadata']);
                $fileObject->save();
            }

            // Release the File object from memory.
            release_object($fileObject);
        }
    }

    /**
     * Adds a status message to the harvest.
     * 
     * @param string $message The error message
     * @param int|null $messageCode The message code
     * @param string $delimiter The string dilimiting each status message
     */
    final protected function _addStatusMessage(
        $message, 
        $messageCode = null, 
        $delimiter = "\n\n"
    ) {
        $this->_harvest->addStatusMessage($message, $messageCode, $delimiter);
    }
    
    /**
     * Return this instance's OaipmhHarvester_Harvest object.
     * 
     * @return OaipmhHarvester_Harvest
     */
    final protected function _getHarvest()
    {
        return $this->_harvest;
    }
    
    /**
     * Convenience method that facilitates the building of a correctly formatted 
     * elementTexts array.
     * 
     * @see insert_item()
     * @param array $elementTexts The previously build elementTexts array
     * @param string $elementSet This element's element set
     * @param string $element This element text's element
     * @param mixed $text The text
     * @param bool $html Flag whether this element text is HTML
     * @return array
     */
    protected function _buildElementTexts(
        array $elementTexts = array(), 
        $elementSet, 
        $element, 
        $text, 
        $html = false
    ) {
        $elementTexts[$elementSet][$element][] 
            = array('text' => (string) $text, 'html' => (bool) $html);
        return $elementTexts;
    }

    /**
     * Check if a string is an Xml one.
     *
     * @param string $string
     * @return boolean
     */
    protected function _isXml($string)
    {
        return strpos($string, '<') !== false
            && strpos($string, '>') !== false
            // A main tag is added to allow inner ones.
            && (boolean) simplexml_load_string('<xml>' . $string . '</xml>', 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_NOWARNING);
    }

    /**
     * Error handler callback.
     * 
     * @see self::__construct()
     */
    public function errorHandler($errno, $errstr, $errfile, $errline)
    {
        if (!error_reporting()) {
            return false;
        }
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    /**
     * Harvest records from the OAI-PMH repository.
     */
    final public function harvest()
    {
        try {
            $this->_harvest->status = 
                OaipmhHarvester_Harvest::STATUS_IN_PROGRESS;
            $this->_harvest->save();
        
            $this->_beforeHarvest();
            // This method does most of the actual work.
            $resumptionToken = $this->_harvestRecords();

            // A return value of true just indicates success, all other values
            // must be valid resumption tokens.
            if ($resumptionToken === true) {
                $this->_afterHarvest();
                $this->_harvest->status = 
                    OaipmhHarvester_Harvest::STATUS_COMPLETED;
                $this->_harvest->completed = $this->_getCurrentDateTime();
                $this->_harvest->resumption_token = null;
            } else {
                $this->_harvest->resumption_token = $resumptionToken;
                $this->_harvest->status =
                    OaipmhHarvester_Harvest::STATUS_QUEUED;
            }
        
            $this->_harvest->save();
        
        } catch (Zend_Http_Client_Exception $e) {
            $this->_stopWithError($e);
        } catch (Exception $e) {
            $this->_stopWithError($e);
            // For real errors need to be logged and debugged.
            _log($e, Zend_Log::ERR);
        }
    
        $peakUsage = memory_get_peak_usage();
        _log("[OaipmhHarvester] Peak memory usage: $peakUsage", Zend_Log::INFO);
    }

    private function _stopWithError($e)
    {
        $this->_addStatusMessage($e->getMessage(), self::MESSAGE_CODE_ERROR);
        $this->_harvest->status = OaipmhHarvester_Harvest::STATUS_ERROR;
        // Reset the harvest start_from time if an error occurs during 
        // processing. Since there's no way to know exactly when the 
        // error occured, re-harvests need to start from the beginning.
        $this->_harvest->start_from = null;
        $this->_harvest->save();
    }

    private function _continueWithWarning($e)
    {
        $this->_addStatusMessage($e->getMessage(), self::MESSAGE_CODE_WARNING);
    }

    /**
     * Deduplicate files (same original name) of an item.
     * In case of a duplicate, the newest file (greater id) is kept.
     *
     * The authentication is not checked in order to ingest updated files.
     *
     * @param Item $item
     */
    protected function _deduplicateFiles($item)
    {
        $db = get_db();

        $sql = "
            SELECT files.id
            FROM `{$db->files}` AS files, `{$db->files}` AS files_2
            WHERE files.item_id = ? AND files_2.item_id = ?
                AND files.original_filename = files_2.original_filename
                AND files.id < files_2.id;
        ";
        $fileIds = $db->fetchCol($sql, array($item->id, $item->id));
        $files = $db->getTable('File')->findByItem($item->id, $fileIds, 'id');
        foreach ($files as $file) {
            $file->delete();
        }
    }

    /**
     * Remove old files not set in new metadata files, using original filename.
     *
     * @param Item $item
     * @param array $filesMetadata
     */
    protected function _deleteRemovedFiles($item, $filesMetadata)
    {
        $list = $this->_listFiles($filesMetadata);

        // Delete all attached files.
        if (empty($list)) {
            foreach ($item->Files as $file) {
                $file->delete();
            }
            return;
        }

        // Selective deletion.
        $db = get_db();
        $table = $db->getTable('File');
        $tableAlias = $table->getTableAlias();
        $select = $table->getSelect()
            ->where("$tableAlias.item_id = ?", $item->id)
            ->where("$tableAlias.original_filename NOT IN (?)", $list);
        $files = $table->fetchObjects($select);
        foreach ($files as $file) {
            $file->delete();
        }
    }

    /**
     * Reorder files according to the metadata file.
     *
     * @todo Check if this is really needed (find a test for it).
     *
     * @param Item $item
     * @param array $filesMetadata
     */
    protected function _orderFiles($item, $filesMetadata)
    {
        $list = $this->_listFiles($filesMetadata);

        if (empty($list)) {
            return array();
        }

        $db = get_db();
        $sql = "
            UPDATE `{$db->files}`
            SET `order` = ?
            WHERE `item_id` = ?
                AND `original_filename` = ?
        ";
        foreach ($list as $key => $filename) {
            $db->query($sql, array($key + 1, $item->id, $filename));
        }
    }

    /**
     * List the original filename of files.
     *
     * @param array $filesMetadata
     * @return array List of cleaned original names.
     */
    protected function _listFiles($filesMetadata)
    {
        if (empty($filesMetadata['files'])) {
            return array();
        }

        // TODO Use Omeka_File_Ingest_AbstractIngest::_getOriginalFilename()?
        $list = array();
        foreach ($filesMetadata['files'] as $file) {
            // Manage other cases? No, since this is update from OAI-PMH.
            if (!empty($file['Url'])) {
                $list[] = $file['Url'];
            }
            elseif (!empty($file['Filesystem'])) {
                $list[] = basename($file['Filesystem']);
            }
        }

        return $list;
    }

    /**
     * Remove old elements of a record (not set in an updated repository).
     *
     * @todo Optimize.
     * @internal Mixin_ElementText::getAllElementTextsByElement() is available
     * from Omeka 2.3 only.
     *
     * @param Record $record
     * @param array $metadata
     */
    protected function _updateMetadata($record, $metadata)
    {
        switch ($this->_harvest->update_metadata) {
            case OaipmhHarvester_Harvest::UPDATE_METADATA_KEEP:
                return;

            case OaipmhHarvester_Harvest::UPDATE_METADATA_ELEMENT:
                foreach ($metadata as $elementSetName => $element) {
                    foreach ($element as $elementName => $dataElement) {
                        $elementTexts = $record->getElementTexts($elementSetName, $elementName);
                        $this->_deleteRemovedMetadata($record, $elementTexts, $metadata);
                    }
                }
                break;

            case OaipmhHarvester_Harvest::UPDATE_METADATA_STRICT:
                $elementTexts = $record->getAllElementTexts();
                $this->_deleteRemovedMetadata($record, $elementTexts, $metadata);
                break;
        }
    }

    /**
     * Remove old elements of files of an item.
     *
     * @uses _updateMetadata()
     *
     * @param Item $item
     * @param array $filesMetadata
     */
    protected function _updateFilesMetadata($item, $metadata)
    {
        if (empty($metadata['files'])) {
            return;
        }

        foreach ($item->getFiles() as $key => $file) {
            foreach ($metadata['files'] as $metadataFile) {
                if (!empty($metadataFile['Url'])) {
                    if ($file->original_filename == $metadataFile['Url']) {
                        $this->_updateMetadata($file, $metadataFile['metadata']);
                        break;
                    }
                }
                elseif (!empty($metadataFile['Filesystem'])) {
                    if ($file->original_filename == basename($metadataFile['Filesystem'])) {
                        $this->_updateMetadata($file, $metadataFile['metadata']);
                        break;
                    }
                }
            }
            release_object($file);
        }
    }

    /**
     * Helper for _updateMetadata().
     */
    private function _deleteRemovedMetadata($record, $elementTexts, $metadata)
    {
        if (empty($elementTexts)) {
            return;
        }
        foreach ($elementTexts as $elementText) {
            $exists = false;
            // Internal: elements are already static.
            $element = $record->getElementById($elementText->element_id);
            // Check if the element exists in new metadata.
            // Normally, there should not be duplicates, except if there
            // are ones inside the repertory.
            if (isset($metadata[$element->set_name][$element->name])) {
                foreach ($metadata[$element->set_name][$element->name] as $data) {
                    if ($elementText->text == $data['text'] && $elementText->html == $data['html']) {
                        $exists = true;
                        break;
                    }
                }
            }
            // Delete it if not exists.
            if (!$exists) {
                $elementText->delete();
            }
        }
    }

    public static function factory($harvest)
    {
        $maps = oaipmh_harvester_get_maps();
        // The class is autoloaded.
        $class = $maps[$harvest->metadata_prefix]['class'];

        // Set the harvest object.
        $harvester = new $class($harvest);
        return $harvester;
    }
}
