<?php
$head = array('body_class' => 'oaipmh-harvester primary', 
              'title'      => 'OAI-PMH Harvester | Status');
head($head);
?>

<h1><?php echo $head['title']; ?></h1>

<div id="primary">

    <?php echo flash(); ?>
    
    <table>
        <tr>
            <td>Set Spec</td>
            <td><?php echo $this->set->set_spec; ?></td>
        </tr>
        <tr>
            <td>Set Name</td>
            <td><?php echo $this->set->set_name; ?></td>
        </tr>
        <tr>
            <td>Metadata Prefix</td>
            <td><?php echo $this->set->metadata_prefix; ?></td>
        </tr>
        <tr>
            <td>Base URL</td>
            <td><?php echo $this->set->base_url; ?></td>
        </tr>
        <tr>
            <td>Status</td>
            <td><?php echo ucwords($this->set->status); ?></td>
        </tr>
        <tr>
            <td>Initiated</td>
            <td><?php echo $this->set->initiated; ?></td>
        </tr>
        <tr>
            <td>Completed</td>
            <td><?php echo $this->set->completed ? $this->set->completed : '[not completed]'; ?></td>
        </tr>
        <tr>
            <td>Status Messages</td>
            <td><?php echo nl2br($this->set->status_messages); ?></td>
        </tr>
    </table>

</div>

<?php foot(); ?>
