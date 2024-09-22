<?php
/*
 * Name: Sorted offers list with product images
 * Modules:
 * Module Types: PRODUCT
 *
 */

__('Sorted offers list with product images', 'content-egg-tpl');

use ContentEgg\application\helpers\TemplateHelper;

if (isset($data['Amazon']) || isset($data['AmazonNoApi']))
    \wp_enqueue_script('cegg-frontend', \ContentEgg\PLUGIN_RES . '/js/frontend.js', array('jquery'));

$all_items = TemplateHelper::sortAllByPrice($data, $order, $sort);
$amazon_last_updated = TemplateHelper::getLastUpdateFormattedAmazon($data);
$is_price = TemplateHelper::isPriceAvailable($all_items);

if ($is_price)
{
    $col_title = 5;
    $col_price = 3;
}
else
{
    $col_title = 8;
    $col_price = 0;
}
?>

<div class="egg-container cegg-list-withlogos">
    <?php if ($title) : ?>
        <h3><?php echo \esc_html($title); ?></h3>
    <?php endif; ?>

    <div class="egg-listcontainer my-custom-class">

        <?php foreach ($all_items as $key => $item) : ?>
            <?php $this->renderBlock('list_row', array('item' => $item, 'amazon_last_updated' => $amazon_last_updated, 'col_title' => $col_title, 'col_price' => $col_price)); ?>
        <?php endforeach; ?>

    </div>
</div>