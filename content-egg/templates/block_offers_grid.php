<?php
/*
 * Name: Grid with prices (3 column)
 * Modules:
 * Module Types: PRODUCT
 *
 */

use ContentEgg\application\helpers\TemplateHelper;

__('Grid with prices (3 column)', 'content-egg-tpl');

if (empty($cols) || $cols > 12)
    $cols = 3;
$col_size = ceil(12 / $cols);
$amazon_last_updated = TemplateHelper::getLastUpdateFormattedAmazon($data);

?>

<div class="egg-container egg-grid">

    <?php if ($title) : ?>
        <h3><?php echo \esc_html($title); ?></h3>
    <?php endif; ?>

    <div class="container-fluid">
        <div class="row">
            <?php
            $i = 0;
            foreach ($data as $module_id => $items)
            {
                foreach ($items as $item)
                {
                    $this->renderBlock('grid_row', array('item' => $item, 'col_size' => $col_size, 'i' => $i,));
                    $i++;
                    if ($i % $cols == 0)
                        echo '<div class="clearfix hidden-xs"></div>';
                    if ($i % 2 == 0)
                        echo '<div class="clearfix visible-xs-block"></div>';
                }
            }
            ?>
        </div>

        <?php if ($amazon_last_updated) : ?>
            <div class="row cegg-no-top-margin" style="padding: 0px;">
                <div class="col-md-12 text-right">
                    <small class="text-muted"><?php echo esc_html(sprintf(TemplateHelper::__('Last Amazon price update was: %s'), $amazon_last_updated)); ?>
                        <?php TemplateHelper::printAmazonDisclaimer(); ?>
                    </small>
                </div>
            </div>

        <?php endif; ?>
    </div>

</div>