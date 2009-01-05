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
			<?php echo $this->formText('base_url'); ?>
			<p class="explanation">The base URL of the OAI-PMH data provider.</p>
			</div>
		</div>
		
		<?php echo $this->formSubmit('submit_view_sets', 'View Sets'); ?>
	</form>
	
	<h2>Harvested Sets</h2>
	
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
        		<td><?php echo $harvestedSet->getStatus()->name; ?></td>
        	</tr>
        <?php endforeach; ?>
        </tbody>
	</table>

</div>

<?php foot(); ?>
