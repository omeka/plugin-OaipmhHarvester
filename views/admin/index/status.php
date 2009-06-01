<?php
/**
 * Admin status view.
 * 
 * @package OaipmhHarvester
 * @subpackage Views
 * @copyright Copyright (c) 2009 Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

$head = array('body_class' => 'oaipmh-harvester primary', 
              'title'      => 'OAI-PMH Harvester | Status');
head($head);
?>

<h1><?php echo $head['title']; ?></h1>

<div id="primary">

    <?php echo flash(); ?>
    
    <table>
        <tr>
            <td>ID</td>
            <td><?php echo $this->harvest->id; ?></td>
        </tr>
        <tr>
            <td>Set Spec</td>
            <td><?php echo $this->harvest->set_spec; ?></td>
        </tr>
        <tr>
            <td>Set Name</td>
            <td><?php echo $this->harvest->set_name; ?></td>
        </tr>
        <tr>
            <td>Metadata Prefix</td>
            <td><?php echo $this->harvest->metadata_prefix; ?></td>
        </tr>
        <tr>
            <td>Base URL</td>
            <td><?php echo $this->harvest->base_url; ?></td>
        </tr>
        <tr>
            <td>Status</td>
            <td><?php echo ucwords($this->harvest->status); ?></td>
        </tr>
        <tr>
            <td>Initiated</td>
            <td><?php echo $this->harvest->initiated; ?></td>
        </tr>
        <tr>
            <td>Completed</td>
            <td><?php echo $this->harvest->completed ? $this->harvest->completed : '[not completed]'; ?></td>
        </tr>
        <tr>
            <td>Status Messages</td>
            <td><?php echo html_escape($this->harvest->status_messages); ?></td>
        </tr>
    </table>
    <?php if ($this->harvest->status != OaipmhHarvesterHarvest::STATUS_DELETED): ?>
    <p><strong>Warning:</strong> clicking the following link will delete all items created for this harvest. 
    <a href="<?php echo uri("oaipmh-harvester/index/delete?harvest_id={$this->harvest->id}"); ?>" class="delete">Delete Items</a></p>
    <?php endif; ?>
</div>

<?php foot(); ?>
