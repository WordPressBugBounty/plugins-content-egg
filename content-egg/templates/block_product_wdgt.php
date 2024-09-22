<?php
/*
 * Name: Product widget
 * Modules:
 * Module Types: PRODUCT
 *
 */

use ContentEgg\application\helpers\TemplateHelper;

use function ContentEgg\prn;

?>

<div class="egg-container egg-product-wdgt">

    <?php if ($title) : ?>
        <h3><?php echo \esc_html($title); ?></h3>
    <?php endif; ?>

    <div class="container-fluid" style="padding: 0px;">
        <div class="row" style="margin-bottom: 15px;">
            <?php
            $i = 0;
            foreach ($data as $module_id => $items)
            {
                foreach ($items as $item)
                {
                    if (TemplateHelper::isModuleDataExist($items, array('Amazon', 'AmazonNoApi')))
                        \wp_enqueue_script('cegg-frontend', \ContentEgg\PLUGIN_RES . '/js/frontend.js', array('jquery'));

            ?>
                    <div class="col-xs-12 text-center ">
                        <div class="cegg-product-wdgt-title" style="height: 50px;"><?php echo \esc_html(TemplateHelper::truncate($item['title'], 80)); ?></div>

                        <?php if ($item['img']) : ?>
                            <?php
                            $img = $item['img'];
                            $img = preg_replace('/\._AC_SL\d+_\./', '._SS520_.', $img);
                            $img = preg_replace('/\._SL\d+_\./', '._SS520_.', $img);

                            ?>
                            <a<?php TemplateHelper::printRel(); ?> target="_blank" href="<?php echo esc_url_raw($item['url']); ?>">
                                <img style="max-height: 230px;" src="<?php echo esc_url($img); ?>" alt="<?php echo \esc_attr($item['title']); ?>" />
                                </a>
                            <?php endif; ?>
                            <div style="margin-top: 15px; height: 50px;">
                                <?php if ($item['price']) : ?>
                                    <span class="cegg-price cegg-price-color cegg-price-<?php echo \esc_attr(TemplateHelper::getStockStatusClass($item)); ?>"><?php echo esc_html(TemplateHelper::formatPriceCurrency($item['price'], $item['currencyCode'])); ?></span>
                                    <?php if ($item['priceOld']) : ?><strike class="text-muted"><small><?php echo esc_html(TemplateHelper::formatPriceCurrency($item['priceOld'], $item['currencyCode'])); ?></small></strike>&nbsp;<?php endif; ?>
                                        <div class="cegg-font60 cegg-lineheight15"><?php echo esc_html(sprintf(TemplateHelper::__('as of %s'), TemplateHelper::dateFormatFromGmtAmazon($item['module_id'], $item['last_update']))); ?></div>
                                    <?php endif; ?>
                            </div>

                            <div class="cegg-btn-grid" style="margin: 0px; margin-top: 15px;padding: 0px;">
                                <a<?php TemplateHelper::printRel(); ?> target="_blank" href="<?php echo esc_url_raw($item['url']); ?>" class="btn btn-danger btn-big"><?php TemplateHelper::buyNowBtnText(true, $item, $btn_text); ?></a>

                                    <?php if ($merchant = TemplateHelper::getMerchantName($item)) : ?>
                                        <div class="text-center">
                                            <small class="text-muted title-case">
                                                <?php echo \esc_html($merchant); ?>
                                                <?php if ($module_id == 'Amazon' || $module_id == 'AmazonNoApi') : ?>
                                                    <?php TemplateHelper::printAmazonDisclaimer(); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                            </div>

                    </div>

            <?php
                }
            }
            ?>
        </div>

    </div>

</div>