<?php
/**
 * Admin status view.
 * 
 * @package OaipmhHarvester
 * @subpackage Views
 * @copyright Copyright (c) 2009-2011 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

$head = array('body_class' => 'oaipmh-harvester content', 
              'title'      => 'OAI-PMH Harvester | Status');
echo head($head);
?>

<?php echo flash(); ?>
<table>
    <tr>
        <td>ID</td>
        <td><?php echo html_escape($this->harvest->id); ?></td>
    </tr>
    <tr>
        <td>Set Spec</td>
        <td><?php echo html_escape($this->harvest->set_spec); ?></td>
    </tr>
    <tr>
        <td>Set Name</td>
        <td><?php echo html_escape($this->harvest->set_name); ?></td>
    </tr>
    <tr>
        <td>Metadata Prefix</td>
        <td><?php echo html_escape($this->harvest->metadata_prefix); ?></td>
    </tr>
    <tr>
        <td>Base URL</td>
        <td><?php echo html_escape($this->harvest->base_url); ?></td>
    </tr>
    <tr>
        <td>Status</td>
        <td><?php echo html_escape(ucwords($this->harvest->status)); ?></td>
    </tr>
    <tr>
        <td>Initiated</td>
        <td><?php echo html_escape($this->harvest->initiated); ?></td>
    </tr>
    <tr>
        <td>Completed</td>
        <td><?php echo $this->harvest->completed ? html_escape($this->harvest->completed) : html_escape('[not completed]'); ?></td>
    </tr>
    <tr>
        <td>Status Messages</td>
        <td><?php echo html_escape($this->harvest->status_messages); ?></td>
    </tr>
</table>

<?php if ($this->harvest->status != OaipmhHarvester_Harvest::STATUS_DELETED): ?>
<p><strong>Warning:</strong> Clicking the following link will delete all items created for this harvest. 
<?php //echo link_to($this->harvest, 'delete-confirm', 'Delete Items', array('class' => 'delete-button')); ?>
<a href="<?php echo url(array('id' => $this->harvest->id, 'action' => 'delete'), 'default'); ?>" class="delete-button">Delete Items</a>

<?php endif; ?>

<?php echo foot(); ?>