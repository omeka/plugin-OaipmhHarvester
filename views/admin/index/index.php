<?php
/**
 * Admin index view.
 * 
 * @package OaipmhHarvester
 * @subpackage Views
 * @copyright Copyright (c) 2009-2011 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

$head = array('body_class' => 'oaipmh-harvester primary', 
              'title'      => 'OAI-PMH Harvester');
head($head);
?>

<h1><?php echo $head['title']; ?></h1>

<div id="primary">

<?php echo flash(); ?>
    
    <?php echo $this->harvestForm; ?> 
    <h2>Harvests</h2>
    
    <?php if (empty($this->harvests)): ?>
    
    <p>There are no harvests.</p>
    
    <?php else: ?>
    
    <table>
       <thead>
            <tr>
                <th>ID</th>
                <th>Base URL</th>
                <th>Metadata Prefix</th>
                <th>Set Spec</th>
                <th>Set Name</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($this->harvests as $harvest): ?>
            <tr>
                <td><?php echo $harvest->id; ?></td>
                <td><?php echo oh_snippet($harvest->base_url, 30); ?></td>
                <td><?php echo $harvest->metadata_prefix; ?></td>
                <td><?php echo mb_chunk_split($harvest->set_spec, 20, "<br />"); ?></td>
                <td><?php echo $harvest->set_name; ?></td>
                <td><a href="<?php echo uri("oaipmh-harvester/index/status?harvest_id={$harvest->id}"); ?>"><?php echo ucwords($harvest->status); ?></a></td>
                <?php if ($harvest->status == OaipmhHarvester_Harvest::STATUS_COMPLETED): ?>
                <td><form method="post" action="<?php echo uri('oaipmh-harvester/index/harvest');?>">
                    <?php echo $this->formHidden('harvest_id', $harvest->id); ?>
                    <?php echo $this->formSubmit('submit_reharvest', 'Re-Harvest'); ?>
                    </form>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php endif; ?>

</div>

<?php foot(); ?>
