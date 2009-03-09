<div class="field">
    <label for="oaipmh_harvester_php_path">Path to PHP-CLI</label>
    <?php echo __v()->formText('oaipmh_harvester_php_path', $path, null);?>
    <p class="explanation">Path to your server's PHP-CLI command. The PHP 
    version must correspond to normal Omeka requirements. Some web hosts use PHP 
    4.x for their default PHP-CLI, but many provide an alternative path to a 
    PHP-CLI 5 binary. Check with your web host for more information.</p>
</div>
<div class="field">
    <label for="oaipmh_harvester_memory_limit">Memory Limit</label>
    <?php echo __v()->formText('oaipmh_harvester_memory_limit', $memoryLimit, null);?>
    <p class="explanation">Set memory limit to avoid memory allocation errors 
    during harvesting. <strong>We recommend that you choose a high memory limit.</strong> 
    Examples include 128M, 1G, and -1. The available options are K (for 
    Kilobytes), M (for Megabytes) and G (for Gigabytes). Anything else assumes 
    bytes. Set to -1 for an infinite limit. Be advised that many web hosts set a 
    maximum memory limit, so this setting may be ignored if it exceeds the 
    maximum allowable limit. Check with your web host for more information.</p>
</div>
<div class="field">
    <label for="oaipmh_harvester_release_objects">Release Objects</label>
    <?php echo __v()->formCheckbox('oaipmh_harvester_release_objects', 
                                   $releaseObjects, 
                                   null, 
                                   array('yes', 'no')); ?>
    <p class="explanation">Checking this will greatly reduce the chances of 
    memory allocation errors.</p>
</div>