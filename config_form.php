<div class="field">
    <div class="two columns alpha">
        <label for="oaipmhharvester_http_client_timeout"><?php echo __('Timeout'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation">
            <?php echo __("Timeout of the HTTP client, in seconds. Defaults to 10."); ?>
        </p>
        <?php echo get_view()->formText(
            'oaipmhharvester_http_client_timeout',
            get_option('oaipmhharvester_http_client_timeout')
        ); ?>
    </div>
</div>
