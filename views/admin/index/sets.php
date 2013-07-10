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
    <div class="error">There are no available data maps that are compatable with 
    this repository. You will not be able to harvest from this repository.</div>
    <?php endif; ?>
    <h2>Data provider: <?php echo html_escape($this->baseUrl); ?></h2>
    <h3>Harvest the entire repository:</h3>
    <p>
    <form method="post" action="<?php echo url('oaipmh-harvester/index/harvest'); ?>">
        <?php echo $this->formSelect('metadata_spec', null, null, $this->availableMaps); ?>
        <?php echo $this->formHidden('base_url', $this->baseUrl); ?>
        <?php echo $this->formSubmit('submit_harvest', 'Go'); ?>
    </form>
    <br />
    </p>
    <h3>Harvest a set:</h3>
    <?php if ($this->sets): ?>
    <table>
        <thead>
            <tr>
                <th>Set</th>
                <th>Set Spec</th>
                <th>Harvest</th>
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
                <?php echo $this->formSelect('metadata_spec', null, null, $this->availableMaps); ?>
                <?php echo $this->formHidden('base_url', $this->baseUrl); ?>
                <?php echo $this->formHidden('set_spec', $set->setSpec); ?>
                <?php echo $this->formHidden('set_name', $set->setName); ?>
                <?php echo $this->formHidden('set_description', @ $setDescription); ?>
                <?php echo $this->formSubmit('submit_harvest', 'Go'); ?>
                </form></td>
            </tr>
    <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p>This repository does not allow you to harvest individual sets.</p>
    <?php endif; ?>
    <?php if ($this->resumptionToken): ?>
    <div>
        <form method="post">
            <?php echo $this->formHidden('base_url', $this->baseUrl); ?>
            <?php echo $this->formHidden('resumption_token', $this->resumptionToken); ?>
            <?php echo $this->formSubmit('submit_next_page', 'Next Page'); ?>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php echo foot(); ?>
