<div id="harvester-duplicates" class="info-panel">
<h2>Duplicate Harvested Items</h2>
<ul>
<?php foreach($items as $item): ?>
    <li>
    <?php echo link_to_item(
            'Item #' . item('id', null, $item),
            array(),
            'show',
            $item
        ); ?>
    </li>
    <?php release_object($item); ?>
<?php endforeach; ?>
</ul>
</div> <?php
