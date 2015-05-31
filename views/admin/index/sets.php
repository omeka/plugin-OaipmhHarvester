<?php
/**
 * Admin sets view.
 * 
 * @package OaipmhHarvester
 * @subpackage Views
 * @copyright Copyright (c) 2009-2011 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */
$head = array('body_class' => 'oaipmh-harvester content', 
              'title'      => 'OAI-PMH Harvester | Harvest');
echo head($head);
?>
<div id="primary">
    <?php echo flash(); ?>
    <?php if (empty($this->availableMaps)): ?>
    <div class="error"><?php
        echo __('There are no available data maps that are compatable with this repository.');
        echo ' ' . __('You will not be able to harvest from this repository.');
    ?></div>
    <?php endif; ?>
    <h2><?php echo __('Data provider: %s', html_escape($this->baseUrl)); ?></h2>
<fieldset id="fieldset-oaipmh-repository-entire">
    <legend><?php echo __('Harvest the entire repository'); ?></legend>
    <form method="post" action="<?php echo url('oaipmh-harvester/index/harvest'); ?>">
        <section class="seven columns alpha">
            <div class="field">
                <div class="two columns alpha">
                    <?php echo $this->formLabel('metadata_spec', __('Format to harvest')); ?>
                </div>
                <div class="inputs five columns omega">
                    <?php echo $this->formSelect('metadata_spec', null, null, $this->availableMaps); ?>
                </div>
            </div>
            <div class="field">
                <div class="two columns alpha">
                    <?php echo $this->formLabel('update_files', __('Update files')); ?>
                </div>
                <div class="inputs five columns omega">
                    <?php
                    $optionsUpdateFiles = array(
                        OaipmhHarvester_Harvest::UPDATE_FILES_KEEP => __('Keep existing'),
                        OaipmhHarvester_Harvest::UPDATE_FILES_DEDUPLICATE => __('Deduplicate'),
                        OaipmhHarvester_Harvest::UPDATE_FILES_REMOVE => __('Remove deleted'),
                        OaipmhHarvester_Harvest::UPDATE_FILES_FULL => __('Full update'),
                    );
                    echo $this->formSelect('update_files', 'full', null, $optionsUpdateFiles); ?>
                </div>
            </div>
        </section>
        <section class="three columns omega">
            <div>
                <?php echo $this->formHidden('base_url', $this->baseUrl); ?>
                <?php echo $this->formSubmit('submit_harvest', __('Start')); ?>
            </div>
        </section>
    </form>
</fieldset>
<fieldset id="fieldset-oaipmh-repository-set">
    <legend><?php echo __('Harvest a specific set'); ?></legend>
    <?php if ($this->sets): ?>
    <table>
        <thead>
            <tr>
                <th><?php echo __('Set'); ?></th>
                <th><?php echo __('Set Spec'); ?></th>
                <th><?php echo __('Harvest'); ?></th>
            </tr>
        </thead>
        <tbody>
    <?php foreach ($this->sets as $set): ?>
    <?php 
    if ($set->setDescription 
        && ($dcWrapper = $set->setDescription->children('oai_dc', true))
        && ($descWrapper = $dcWrapper->children('dc', true))
    ):
        $setDescription = $descWrapper->description;
    else:
        $setDescription = null;
    endif; ?>
            <tr>
                <td>
                    <?php if (isset($set->setName)): ?>
                    <strong><?php echo html_escape($set->setName); ?></strong>
                    <?php endif; ?>
                    <?php if ($setDescription): ?>
                    <p><?php echo html_escape($setDescription); ?></p>
                    <?php endif; ?>
                </td>
                <td><?php echo html_escape($set->setSpec); ?></td>
                <td style="white-space: nowrap"><form method="post" action="<?php echo url('oaipmh-harvester/index/harvest'); ?>">
                    <style type="text/css">.field select {width: auto;}</style>
                    <div class="field">
                        <?php echo $this->formLabel('metadata_spec', __('Format to harvest')); ?>
                        <?php echo $this->formSelect('metadata_spec', null, null, $this->availableMaps); ?>
                    </div>
                    <div class="field">
                        <?php echo $this->formLabel('update_files', __('Update files')); ?>
                        <?php echo $this->formSelect('update_files', 'full', null, $optionsUpdateFiles); ?>
                    </div>
                    <div class="field">
                        <?php echo $this->formHidden('base_url', $this->baseUrl); ?>
                        <?php echo $this->formHidden('set_spec', $set->setSpec); ?>
                        <?php echo $this->formHidden('set_name', $set->setName); ?>
                        <?php echo $this->formHidden('set_description', @ $setDescription); ?>
                        <?php echo $this->formSubmit('submit_harvest', __('Start')); ?>
                    </div>
                </form></td>
            </tr>
    <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p><?php echo __('This repository does not allow you to harvest individual sets.'); ?></p>
    <?php endif; ?>
</fieldset>
    <?php if ($this->resumptionToken): ?>
    <div>
        <form method="post">
            <?php echo $this->formHidden('base_url', $this->baseUrl); ?>
            <?php echo $this->formHidden('resumption_token', $this->resumptionToken); ?>
            <?php echo $this->formSubmit('submit_next_page', __('Next Page')); ?>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php echo foot(); ?>
