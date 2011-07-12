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
        $root = dirname(__FILE__);
        $suite->addTestFiles(
            array(
                //$root . '/models/OaipmhHarvester/Harvest/OaiDcTest.php',
                $root . '/HooksTest.php',
            )
        );
        return $suite;
    }
}
