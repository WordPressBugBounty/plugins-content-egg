<?php

/*
 * Name: Customizable (use with "show" parameter)
 * Modules:
 * Module Types: PRODUCT
 *
 */

__('Customizable (use with "show" parameter)', 'content-egg-tpl');

use ContentEgg\application\helpers\TemplateHelper;

use function ContentEgg\prn;

?>

<?php foreach ($data as $module_id => $items) : ?>
    <?php foreach ($items as $item) : ?>
        <?php

        switch ($params['show'])
        {
            case 'title':
                echo \esc_html($item['title']);
                break;
            case 'img':
                $img = $item['img'];
                $img = preg_replace('/\._AC_SL\d+_\./', '._SS520_.', $img);
                $img = preg_replace('/\._SL\d+_\./', '._SS520_.', $img);
                echo '<img src="' . \esc_attr($img) . '" alt="' . \esc_attr($item['title']) . '" />';
                break;
            case 'price':
                if ($item['price'])
                    echo esc_html(TemplateHelper::formatPriceCurrency($item['price'], $item['currencyCode']));
                break;
            case 'priceold':
                echo esc_html(TemplateHelper::formatPriceCurrency($item['priceOld'], $item['currencyCode']));
                break;
            case 'currencycode':
                echo \esc_html($item['currencyCode']);
                break;
            case 'button':
                echo '<span class="egg-container"><a';
                TemplateHelper::printRel();
                echo ' target="_blank" href="' . esc_url_raw($item['url']) . '" class="btn btn-danger">';
                TemplateHelper::buyNowBtnText(true, $item);
                echo '</a></span>';
                break;
            case 'stock_status':
                echo esc_html(TemplateHelper::getStockStatusStr($item));
                break;
            case 'description':
                echo wp_kses_post($item['description']);
                break;
            case 'url':
                echo esc_url_raw($item['url']);
                break;
            case 'last_update':
                echo esc_html(TemplateHelper::getLastUpdateFormatted($item['module_id']));
                break;
            case 'img+url':
                echo '<a ' . TemplateHelper::printRel(false) . ' target="_blank" href=" ' . esc_url_raw($item['url']) . '">';
                echo '<img src="' . \esc_attr($item['img']) . '" alt="' . \esc_attr($item['title']) . '" class="cegg-cust-img" />';
                echo '</a>';
                break;
            case 'attribute':
                echo esc_html(TemplateHelper::getLastUpdateFormatted($item['module_id']));
                break;
            default:
                break;
        }
        ?>

    <?php endforeach; ?>
<?php endforeach; ?>
