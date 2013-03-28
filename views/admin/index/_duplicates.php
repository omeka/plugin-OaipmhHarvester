<div id="harvester-duplicates" class="panel">
<h4>Duplicate Harvested Items</h4>
<ul>
<?php foreach($items as $item): ?>
    <li>
    <?php echo link_to_item(
            'Item #' . $item->id,
            array(),
            'show',
            $item
        ); ?>
    </li>
    <?php release_object($item); ?>
<?php endforeach; ?>
</ul>
</div>