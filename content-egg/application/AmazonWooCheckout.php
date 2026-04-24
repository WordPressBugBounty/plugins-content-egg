<?php

namespace ContentEgg\application;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\helpers\TemplateHelper;
use ContentEgg\application\libs\amazon\AmazonLocales;

defined('\ABSPATH') || exit;

/**
 * AmazonWooCheckout class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class AmazonWooCheckout
{
    const QUERY_VAR = 'cegg_amazon_checkout';

    /**
     * Bootstrap feature (Blocks only).
     */
    public static function initAction()
    {
        if (!class_exists('\WooCommerce'))
        {
            return;
        }

        if (GeneralConfig::getInstance()->option('amazon_checkout_button') !== 'enabled')
        {
            return;
        }

        // Declare Cart/Checkout Blocks compatibility.
        add_action('before_woocommerce_init', array(__CLASS__, 'declareBlocksCompatibility'));

        // Enqueue our JS that hooks Proceed to Checkout in the Cart Block.
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueueBlocksScript'));

        // Handle the custom Checkout on Amazon endpoint.
        add_action('template_redirect', array(__CLASS__, 'maybeHandleAmazonCheckout'));
    }

    /**
     * Tell WooCommerce that our plugin supports cart & checkout blocks.
     */
    public static function declareBlocksCompatibility()
    {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil'))
        {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'cart_checkout_blocks',
                \ContentEgg\PLUGIN_PATH,
                true
            );
        }
    }

    /**
     * Enqueue JS that overrides the Proceed to Checkout button behavior for the Cart Block.
     */
    public static function enqueueBlocksScript()
    {
        // Only bother on the cart page on the frontend.
        if (!function_exists('is_cart') || !is_cart())
        {
            return;
        }

        if (!function_exists('WC') || !WC()->cart)
        {
            return;
        }

        // Do we have any Amazon-backed items in the cart?
        $has_amazon_items = self::cartHasAmazonItems();

        // If there are no Amazon items, we still enqueue the script,
        // but JS will leave the button as default WooCommerce.
        wp_register_script(
            'cegg-amazon-checkout-blocks',
            plugins_url('res/js/cegg-amazon-checkout-blocks-min.js', \ContentEgg\PLUGIN_FILE),
            array('wc-blocks-checkout', 'wp-i18n', 'wp-hooks'),
            Plugin::version(),
            true
        );

        $amazon_checkout_url = add_query_arg(
            array(self::QUERY_VAR => 1),
            \wc_get_cart_url()
        );

        wp_localize_script(
            'cegg-amazon-checkout-blocks',
            'ceggAmazonCheckout',
            array(
                'enabled'        => true,
                'hasAmazonItems' => $has_amazon_items,
                'checkoutUrl'    => $amazon_checkout_url,
                'buttonLabel'    => Translator::translate('Checkout on Amazon'),
            )
        );

        wp_enqueue_script('cegg-amazon-checkout-blocks');
    }

    protected static function cartHasAmazonItems()
    {
        if (!function_exists('WC') || !WC()->cart)
        {
            return false;
        }

        $cart_items = WC()->cart->get_cart();
        if (!$cart_items)
        {
            return false;
        }

        foreach ($cart_items as $cart_item)
        {
            $item_data = self::getAmazonItemFromCartItem($cart_item);
            if ($item_data && !empty($item_data['asin']))
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Intercept our custom query var and build the Amazon cart from Woo cart items.
     */
    public static function maybeHandleAmazonCheckout()
    {
        if (empty($_GET[self::QUERY_VAR]))
        {
            return;
        }

        // Make sure WooCommerce cart is available.
        if (!function_exists('WC') || !WC()->cart)
        {
            wp_safe_redirect(home_url('/'));
            exit;
        }

        WC()->cart->calculate_totals(); // ensure cart is loaded

        if (WC()->cart->is_empty())
        {
            // No items – send back to cart.
            wp_safe_redirect(wc_get_cart_url());
            exit;
        }

        $cart_items = WC()->cart->get_cart();
        $amazon_items = array();
        $locale = null;

        $module_id = null;
        foreach ($cart_items as $cart_item)
        {
            $item_data = self::getAmazonItemFromCartItem($cart_item);

            if (!$item_data)
            {
                continue; // skip
            }

            if (!$module_id)
            {
                $module_id = $item_data['module_id'];
            }

            if (!in_array($module_id, array('Amazon', 'AmazonNoApi')))
            {
                continue;
            }

            // First item's locale becomes the form's locale.
            if ($locale === null && !empty($item_data['locale']))
            {
                $locale = $item_data['locale'];
            }

            $amazon_items[] = array(
                'asin' => $item_data['asin'],
                'qty'  => max(1, (int) $item_data['qty']),
            );
        }

        if (empty($amazon_items))
        {
            // No Amazon items – fall back to normal checkout.
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }

        // Fallback locale.
        if (!$locale)
        {
            $locale = 'us';
        }

        self::outputAmazonFormAndExit($amazon_items, $module_id, $locale);
    }

    /**
     * Extract ASIN + qty + marketplace from a cart item.
     *
     * @param array $cart_item
     * @return array|null
     *   Example: array(
     *     'asin'        => 'B000123456',
     *     'qty'         => 2,
     *     'locale'      => 'us',
     *   )
     */
    protected static function getAmazonItemFromCartItem(array $cart_item)
    {
        $item = WooIntegrator::getSyncItem($cart_item['product_id']);

        if (!$item || !in_array($item['module_id'], array('Amazon', 'AmazonNoApi')))
        {
            return null;
        }

        $asin = $item['extra']['ASIN'] ?? '';

        if (!$asin)
        {
            return null;
        }

        $locale = $item['extra']['locale'] ?? '';
        if (!$locale)
        {
            $locale = 'us';
        }

        $quantity = isset($cart_item['quantity']) ? (int) $cart_item['quantity'] : 1;

        return array(
            'asin'        => $asin,
            'qty'         => $quantity,
            'locale'      => $locale,
            'module_id'   => $item['module_id'],
        );
    }

    /**
     * Output an auto-submitting Amazon Add-to-Cart form and exit.
     *
     * @param array  $amazon_items array( array( 'asin' => '...', 'qty' => 1 ), ... )
     * @param string $locale  e.g. 'us', 'de', 'uk'
     */
    protected static function outputAmazonFormAndExit(array $amazon_items, $module_id, $locale = 'us')
    {
        $associate_tag = TemplateHelper::getAssociateTagForAmazonLocale($locale, $module_id);
        $associate_tag = apply_filters('cegg_amazon_checkout_associate_tag', $associate_tag);

        // Determine correct Amazon endpoint for the locale.
        $domain = AmazonLocales::getDomain($locale);
        $endpoint = 'https://www.' . $domain . '/gp/aws/cart/add.html';

        if (!$endpoint)
        {
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }

        nocache_headers();

?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>

        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <title><?php esc_html_e('Proceeding to Amazon…', 'content-egg'); ?></title>
        </head>

        <body>
            <p><?php esc_html_e('Proceeding to Amazon to complete your purchase…', 'content-egg'); ?></p>

            <form id="cegg-amazon-cart"
                method="GET"
                action="<?php echo esc_url($endpoint); ?>">

                <?php if ($associate_tag): ?>
                    <input type="hidden" name="AssociateTag" value="<?php echo esc_attr($associate_tag); ?>">
                <?php endif; ?>

                <?php
                $index = 1;
                foreach ($amazon_items as $item)
                {
                    if (empty($item['asin']))
                    {
                        continue;
                    }

                    $qty = !empty($item['qty']) ? (int) $item['qty'] : 1;
                    if ($qty < 1)
                    {
                        $qty = 1;
                    }
                ?>
                    <input type="hidden" name="ASIN.<?php echo (int) $index; ?>"
                        value="<?php echo esc_attr($item['asin']); ?>">
                    <input type="hidden" name="Quantity.<?php echo (int) $index; ?>"
                        value="<?php echo (int) $qty; ?>">
                <?php
                    $index++;
                }
                ?>

                <noscript>
                    <button type="submit">
                        <?php esc_html_e('Continue to Amazon', 'content-egg'); ?>
                    </button>
                </noscript>
            </form>

            <script>
                (function() {
                    var form = document.getElementById('cegg-amazon-cart');
                    if (form) {
                        form.submit();
                    }
                })();
            </script>
        </body>

        </html>
<?php

        exit;
    }
}
