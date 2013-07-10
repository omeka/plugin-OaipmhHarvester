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
                <th>ID</th>
                <th>Base URL</th>
                <th>Metadata Prefix</th>
                <th>Set</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($this->harvests as $harvest): ?>
            <tr>
                <td><?php echo html_escape($harvest->id); ?></td>
                <td><?php echo snippet($harvest->base_url, 0, 40); ?></td>
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
                <td><a href="<?php echo url("oaipmh-harvester/index/status?harvest_id={$harvest->id}"); ?>"><?php echo html_escape(ucwords($harvest->status)); ?></a></td>
                <td style="white-space: nowrap">
                <?php if ($harvest->status == OaipmhHarvester_Harvest::STATUS_COMPLETED): ?>
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
