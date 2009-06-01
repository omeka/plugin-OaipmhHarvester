<?php
/**
 * Admin sets view.
 * 
 * @package OaipmhHarvester
 * @subpackage Views
 * @copyright Copyright (c) 2009 Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */
$head = array('body_class' => 'oaipmh-harvester primary', 
              'title'      => 'OAI-PMH Harvester | Harvest');
head($head);
?>

<h1><?php echo $head['title']; ?></h1>

<div id="primary">

    <?php echo flash(); ?>

    <?php if (empty($this->availableMaps)): ?>
    <div class="error">There are no available data maps that are compatable with 
    this repository. You will not be able to harvest from this repository.</div>
    <?php endif; ?>
    
    <h2>Data provider: <?php echo $this->baseUrl; ?></h2>
    <h3>Harvest the entire repository:</h3>
    <p>
    <form method="post" action="<?php echo uri('oaipmh-harvester/index/harvest'); ?>">
        <?php echo $this->formSelect('metadata_spec', null, null, $this->availableMaps); ?>
        <?php echo $this->formHidden('base_url', $this->baseUrl); ?>
        <?php echo $this->formSubmit('submit_harvest', 'Go'); ?>
    </form>
    <br />
    </p>
    
    <h3>Harvest a set:</h3>
    <table>
        <thead>
            <tr>
                <th>Set Spec</th>
                <th>Set Name</th>
                <th>Set Description</th>
                <th>Harvest</th>
            </tr>
        </thead>
        <tbody>
    <?php foreach ($this->sets as $set): ?>
    <?php $setDc = @ $set->setDescription->children('oai_dc', true)->children('dc', true); ?>
            <tr>
                <td><strong><?php echo wordwrap($set->setSpec, 20, '<br />', true); ?></strong></td>
                <td><?php echo $set->setName; ?></td>
                <td><?php echo @ $setDc->description; ?></td>
                <td><form method="post" action="<?php echo uri('oaipmh-harvester/index/harvest'); ?>">
                <?php echo $this->formSelect('metadata_spec', null, null, $this->availableMaps); ?>
                <?php echo $this->formHidden('base_url', $this->baseUrl); ?>
                <?php echo $this->formHidden('set_spec', $set->setSpec); ?>
                <?php echo $this->formHidden('set_name', $set->setName); ?>
                <?php echo $this->formHidden('set_description', @ $setDc->description); ?>
                <?php echo $this->formSubmit('submit_harvest', 'Go'); ?>
                </form></td>
            </tr>
    <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($this->resumptionToken): ?>
    <div>
        <form method="post">
            <?php echo $this->formHidden('base_url', $this->baseUrl); ?>
            <?php echo $this->formHidden('resumption_token', $this->resumptionToken); ?>
            <?php echo $this->formSubmit('submit_next_page', 'Next Page'); ?>
        </form>
    </div>
    <?php endif; ?>
    <?php /*endif;*/?>
</div>

<?php foot(); ?>
