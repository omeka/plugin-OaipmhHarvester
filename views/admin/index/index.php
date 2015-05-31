<?php
/**
 * Admin index view.
 *
 * @package OaipmhHarvester
 * @subpackage Views
 * @copyright Copyright (c) 2009-2011 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

queue_css_file('oai-pmh-harvester');
$head = array(
    'title' => __('OAI-PMH Harvester'),
    'body_class' => 'primary oaipmh-harvester',
);
echo head($head);
?>
<div id="primary">
    <?php echo flash(); ?>
    <h2><?php echo __('Data Provider'); ?></h2>
    <?php echo $this->harvestForm; ?>
    <br/>
    <div id="harvests">
    <h2><?php __('Harvests'); ?></h2>
    <?php if (empty($this->harvests)): ?>
    <p><?php echo __('There are no harvests.'); ?></p>
    <?php else: ?>
    <table>
       <thead>
            <tr>
                <th><?php echo __('Base URL'); ?></th>
                <th><?php echo __('Metadata Prefix'); ?></th>
                <th><?php echo __('Set'); ?></th>
                <th><?php echo __('Status') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($this->harvests as $harvest): ?>
            <tr>
                <td title="<?php echo html_escape($harvest->base_url); ?>" class="base-url">
                    <div><?php echo html_escape($harvest->base_url); ?></div>
                </td>
                <td><?php echo html_escape($harvest->metadata_prefix); ?></td>
                <td>
                    <?php
                    if ($harvest->set_spec):
                        echo html_escape($harvest->set_name)
                            . ' (' . html_escape($harvest->set_spec) . ')';
                    else:
                        echo __('[Entire Repository]');
                    endif;
                    ?>
                </td>
                <td class="harvest-status">
                    <a href="<?php echo url("oaipmh-harvester/index/status?harvest_id={$harvest->id}"); ?>"><?php echo html_escape(ucwords($harvest->status)); ?></a>
                    <?php if ($harvest->status == OaipmhHarvester_Harvest::STATUS_COMPLETED): ?>
                        <br />
                        <form method="post" action="<?php echo url('oaipmh-harvester/index/harvest');?>">
                        <?php echo $this->formHidden('harvest_id', $harvest->id); ?>
                        <?php echo $this->formSubmit('submit_reharvest', 'Re-Harvest'); ?>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    </div>
</div>
<?php echo foot(); ?>
