<?php

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ShopStore;
use ContentEgg\application\deals\DealNav;
use ContentEgg\application\deals\DealPostType;
use ContentEgg\application\deals\DealTemplates;
use ContentEgg\application\deals\DealView;
use ContentEgg\application\helpers\TemplateHelper;

/** @var \WP_Term $term */
/** @var int $found */
$is_store = $term->taxonomy === DealPostType::TAX_STORE;
$domain = $is_store ? (string) \get_term_meta($term->term_id, DealPostType::TERM_META_DOMAIN, true) : '';
$shop = $domain !== '' ? ShopStore::get($domain) : null;
$logo = '';
if ($is_store)
{
    $logo = ($shop && !empty($shop['logo'])) ? (string) $shop['logo'] : (string) \get_term_meta($term->term_id, DealPostType::TERM_META_LOGO, true);
    if ($logo === '' && $domain !== '')
        $logo = (string) TemplateHelper::getMerchantLogoUrl(array('domain' => $domain));
}
// A CE Shops "info" text is a full store write-up; _listing prints it under the deals.
$description = \term_description($term);
$cashback = $is_store ? DealNav::cashbackShopUrl($domain) : null;
?>
<header class="cegg-deal-termhead<?php echo $is_store ? ' cegg-deal-termhead--store' : ''; ?>">
    <?php if ($logo) : ?><div class="cegg-deal-termhead__logo"><img src="<?php echo \esc_url($logo); ?>" alt=""></div><?php endif; ?>
    <div class="cegg-deal-termhead__body">
        <h1 class="cegg-deal-termhead__title">
            <?php
            /* translators: %s: store name */
            echo \esc_html($is_store ? sprintf(__('%s deals', 'content-egg-tpl'), $term->name) : $term->name);
            ?>
        </h1>
        <?php if ($found > 0) : ?><div class="cegg-deal-termhead__count"><?php /* translators: %d: number of deals */ echo \esc_html(sprintf(\_n('%d deal', '%d deals', $found, 'content-egg-tpl'), $found)); ?></div><?php endif; ?>
        <?php if (trim(\wp_strip_all_tags((string) $description)) !== '') : ?><div class="cegg-deal-termhead__desc"><?php echo \wp_kses_post($description); ?></div><?php endif; ?>
        <?php if ($domain !== '' || $cashback) : ?>
            <div class="cegg-deal-termhead__links">
                <?php if ($domain !== '') : ?><a class="cegg-deal-termhead__visit" href="<?php echo \esc_url('https://' . $domain); ?>" target="_blank"<?php TemplateHelper::printRel(); ?>><?php /* translators: %s: store domain */ echo \esc_html(sprintf(__('Visit %s', 'content-egg-tpl'), $domain)); ?></a><?php endif; ?>
                <?php if ($cashback) : ?><a class="cegg-deal-termhead__cashback" href="<?php echo \esc_url($cashback); ?>"><?php /* translators: %s: store name */ echo \esc_html(sprintf(__('Cashback & coupons at %s', 'content-egg-tpl'), $term->name)); ?> &rarr;</a><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</header>
<?php
// Page 1 only: the same block on every page of a store is duplicate content.
if ($domain !== '' && !\is_paged() && ($coupons = DealView::couponsHtml(array('domain' => $domain))))
    /* translators: %s: store name */
    echo '<section class="cegg-deal-section cegg-deal-section--coupons"><h2 class="cegg-deal-section__title">' . \esc_html(sprintf(__('%s coupons', 'content-egg-tpl'), $term->name)) . '</h2>' . $coupons . '</section>'; // phpcs:ignore
if (!$is_store)
    echo DealTemplates::part('_chips', array('parent' => (int) $term->term_id, 'current' => 0)); // phpcs:ignore
