<?php
/*
 * Name: Product card (no features)
 * Modules:
 * Module Types: PRODUCT
 *
 */

defined('\ABSPATH') || exit;

use ContentEgg\application\helpers\TemplateHelper;

use function ContentEgg\prnx;

?>
<?php foreach ($data as $module_id => $items) : ?>

  <?php foreach ($items as $item) : ?>
    <?php
    if (TemplateHelper::isModuleDataExist($items, array('Amazon', 'AmazonNoApi')))
      \wp_enqueue_script('cegg-frontend', \ContentEgg\PLUGIN_RES . '/js/frontend.js', array('jquery'));
    ?>
    <div class="egg-container egg-item">
      <div class="products">

        <?php $this->renderBlock('item_row', array('item' => $item, 'module_id' => $item['module_id'])); ?>

      </div>
    </div>
  <?php endforeach; ?>
<?php endforeach; ?>