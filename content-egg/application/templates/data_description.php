<?php
defined('\ABSPATH') || exit;

use ContentEgg\application\helpers\TemplateHelper;

?>
<div class="egg-container">
    <?php foreach ($items as $item) : ?>
        <div class="egg-description"><?php echo \wp_kses_post($item['description']); ?></div>
    <?php endforeach; ?>
</div>