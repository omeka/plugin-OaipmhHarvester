<?php
/**
 * Admin status view.
 *
 * @package OaipmhHarvester
 * @subpackage Views
 * @copyright Copyright (c) 2009-2011 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

$head = array(
    'body_class' => 'oaipmh-harvester content',
    'title' => __('OAI-PMH Harvester | Status'),
);
echo head($head);
?>
<?php echo flash(); ?>
<?php if (empty($this->harvest)): ?>
<p><?php echo __('This harvest does not exist.'); ?></p>
<p><?php echo __('Go back to the %slist of harvests%s.',
    '<a href="' . url('/oaipmh-harvester') . '">',
    '</a>'); ?></p>
<?php else: ?>
<table>
    <tr>
        <td><?php echo __('ID'); ?></td>
        <td><?php echo html_escape($this->harvest->id); ?></td>
    </tr>
    <tr>
        <td><?php echo __('Set Spec'); ?></td>
        <td><?php echo html_escape($this->harvest->set_spec); ?></td>
    </tr>
    <tr>
        <td><?php echo __('Set Name'); ?></td>
        <td><?php echo html_escape($this->harvest->set_name); ?></td>
    </tr>
    <tr>
        <td><?php echo __('Set Description'); ?></td>
        <td><?php echo html_escape($this->harvest->set_description); ?></td>
    </tr>
    <tr>
        <td><?php echo __('Update files'); ?></td>
        <td><?php
            $optionsUpdateFiles = array(
                OaipmhHarvester_Harvest::UPDATE_FILES_KEEP => __('Keep existing'),
                OaipmhHarvester_Harvest::UPDATE_FILES_DEDUPLICATE => __('Deduplicate'),
                OaipmhHarvester_Harvest::UPDATE_FILES_REMOVE => __('Remove deleted'),
                OaipmhHarvester_Harvest::UPDATE_FILES_FULL => __('Full update'),
            );
            echo $optionsUpdateFiles[$this->harvest->update_files]; ?></td>
    </tr>
    <tr>
        <td><?php echo __('Metadata Prefix'); ?></td>
        <td><?php echo html_escape($this->harvest->metadata_prefix); ?></td>
    </tr>
    <tr>
        <td><?php echo __('Base URL'); ?></td>
        <td><?php echo html_escape($this->harvest->base_url); ?></td>
    </tr>
    <tr>
        <td><?php echo __('Status'); ?></td>
        <td><?php echo html_escape(ucwords($this->harvest->status)); ?></td>
    </tr>
    <tr>
        <td><?php echo __('Initiated'); ?></td>
        <td><?php echo html_escape($this->harvest->initiated); ?></td>
    </tr>
    <tr>
        <td><?php echo __('Completed'); ?></td>
        <td><?php echo $this->harvest->completed ? html_escape($this->harvest->completed) : html_escape('[not completed]'); ?></td>
    </tr>
    <tr>
        <td><?php echo __('Status Messages'); ?></td>
        <td><?php echo html_escape($this->harvest->status_messages); ?></td>
    </tr>
</table>

<?php if ($this->harvest->status != OaipmhHarvester_Harvest::STATUS_DELETED): ?>
<p><strong><?php echo __('Warning:'); ?></strong> <?php echo __('Clicking the following link will delete all items created for this harvest.'); ?>
    <?php //echo link_to($this->harvest, 'delete-confirm', __('Delete Items'), array('class' => 'delete-button')); ?>
    <a href="<?php echo url(array('id' => $this->harvest->id, 'action' => 'delete'), 'default'); ?>" class="delete-button"><?php echo __('Delete Items'); ?></a>
</p>
<?php endif; ?>
<?php endif; ?>
<?php echo foot(); ?>