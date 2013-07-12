<?php
/**
 * Admin index view.
 * 
 * @package OaipmhHarvester
 * @subpackage Views
 * @copyright Copyright (c) 2009-2011 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

$head = array('title'      => 'OAI-PMH Harvester',
              'body_class' => 'primary oaipmh-harvester');
echo head($head);
?>
<style type="text/css">
.base-url, .harvest-status {
    white-space: nowrap;
}

.base-url div{
    max-width: 18em;
    overflow: hidden;
    text-overflow: ellipsis;
}

.harvest-status input[type="submit"] {
    margin: .25em 0 0 0;
}
</style>
<div id="primary">

<?php echo flash(); ?>
    <h2>Data Provider</h2>
    <?php echo $this->harvestForm; ?> 
    <br/>
    <div id="harvests">
    <h2>Harvests</h2>
    <?php if (empty($this->harvests)): ?>
    <p>There are no harvests.</p>
    <?php else: ?>
    <table>
       <thead>
            <tr>
                <th>Base URL</th>
                <th>Metadata Prefix</th>
                <th>Set</th>
                <th>Status</th>
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
                        echo '[Entire Repository]';
                    endif;
                    ?>
                </td>
                <td class="harvest-status">
                    <a href="<?php echo url("oaipmh-harvester/index/status?harvest_id={$harvest->id}"); ?>"><?php echo html_escape(ucwords($harvest->status)); ?></a>
                    <?php if ($harvest->status == OaipmhHarvester_Harvest::STATUS_COMPLETED): ?>
                        <br>
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
