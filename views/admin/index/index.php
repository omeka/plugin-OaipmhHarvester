<?php
$head = array('body_class' => 'oaipmh-harvester primary', 
              'title'      => 'OAI-PMH Harvester');
head($head);
?>

<h1><?php echo $head['title']; ?></h1>

<div id="primary">

<?php echo flash(); ?>
    
    <form method="post" action="<?php echo uri('oaipmh-harvester/index/sets'); ?>">
        
        <div class="field">
            <?php echo $this->formLabel('base_url', 'Base Url'); ?>
            <div class="inputs">
            <?php echo $this->formText('base_url', null, array('size' => 60)); ?>
            <p class="explanation">The base URL of the OAI-PMH data provider.</p>
            </div>
        </div>
        
        <?php echo $this->formSubmit('submit_view_sets', 'View Sets', array('class' => 'submit submit-medium')); ?>
    </form>
    
    <h2>Harvested Sets</h2>
    
    <?php if (empty($this->harvestedSets)): ?>
    
    <p>There are no harvested sets.</p>
    
    <?php else: ?>
    
    <table>
       <thead>
            <tr>
                <th>Set Spec</th>
                <th>Set Name</th>
                <th>Metadata Prefix</th>
                <th>Base URL</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($this->harvestedSets as $harvestedSet): ?>
            <tr>
                <td><?php echo $harvestedSet->set_spec; ?></td>
                <td><?php echo $harvestedSet->set_name; ?></td>
                <td><?php echo $harvestedSet->metadata_prefix; ?></td>
                <td><?php echo $harvestedSet->base_url; ?></td>
                <td><a href="<?php echo uri("oaipmh-harvester/index/status?set_id={$harvestedSet->id}"); ?>"><?php echo ucwords($harvestedSet->status); ?></a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php endif; ?>

</div>

<?php foot(); ?>
