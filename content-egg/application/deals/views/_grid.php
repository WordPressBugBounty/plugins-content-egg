<?php

defined('\ABSPATH') || exit;

use ContentEgg\application\deals\DealTemplates;
use ContentEgg\application\deals\DealView;

/** @var int[] $post_ids */
/** @var int $columns */
$columns = isset($columns) ? max(1, min(6, (int) $columns)) : 4;
?>
<div class="cegg-deal-grid" style="--cegg-deal-cols: <?php echo (int) $columns; ?>">
    <?php foreach ($post_ids as $id) : ?>
        <?php echo DealTemplates::part('_card', array('post_id' => (int) $id, 'deal' => DealView::deal((int) $id))); // phpcs:ignore ?>
    <?php endforeach; ?>
</div>
