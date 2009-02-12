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
    <p class="explanation">Set a high memory limit to avoid memory allocation 
    issues during harvesting. Examples include 128M, 1G, and -1. The available 
    options are K (for Kilobytes), M (for Megabytes) and G (for Gigabytes). 
    Anything else assumes bytes. Set to -1 for an infinite limit. Be advised 
    that many web hosts set a maximum memory limit, so this setting may be 
    ignored if it exceeds the maximum allowable limit. Check with your web host 
    for more information.</p>
</div>