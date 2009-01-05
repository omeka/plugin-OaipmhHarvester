<?php
// Set a high memory limit to avoid memory allocation issues.
ini_set('memory_limit', '500M');

// Require the mapping class.
require_once 'OaiDc.php';

try {
    
    $harvest = new OaipmhHarvester_Harvest_OaiDc;
    $harvest->harvest();

} catch (Exception $e) {
    echo $e->getMessage();
}