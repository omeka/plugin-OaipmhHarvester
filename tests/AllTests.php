<?php
/**
 * @version $Id$
 * @copyright Center for History and New Media, 2007-2010
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 * @package Omeka
 */

/**
 * Test suite for Coins.
 *
 * @package Omeka
 * @copyright Center for History and New Media, 2007-2010
 */
class OaipmhHarvester_AllTests extends PHPUnit_Framework_TestSuite
{
    public static function suite()
    {
        $suite = new OaipmhHarvester_AllTests('OAI-PMH Harvester Tests');
        $testRoot = dirname(__FILE__);
        $suite->addTestFiles(
            array(
                //$testRoot . '/models/OaipmhHarvester/Harvest/OaiDcTest.php',
                $testRoot . '/models/OaipmhHarvester/Harvest/AbstractTest.php',
                $testRoot . '/models/OaipmhHarvester/RequestTest.php',
                $testRoot . '/HooksTest.php',
            )
        );

        $pluginRoot = dirname($testRoot);

        set_include_path(
            $pluginRoot . PATH_SEPARATOR 
            . "$pluginRoot/models" .  PATH_SEPARATOR 
            . get_include_path()
        );
        return $suite;
    }
}

function check_shutdown() {
    $lasterror = error_get_last();
    if (in_array($lasterror['type'], array(E_NOTICE, E_WARNING))) {
        return;
    }
    echo "Error \"{$lasterror['message']}\" on line {$lasterror['line']} in file "
       . "{$lasterror['file']}\n";exit;
}
 
register_shutdown_function('check_shutdown');
