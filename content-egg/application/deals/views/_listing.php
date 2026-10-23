<?php

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ShopStore;
use ContentEgg\application\deals\DealPostType;
use ContentEgg\application\deals\DealTemplates;

/** @var string $title */
/** @var string $description */
/** @var string $context archive|store|category */
/** @var \WP_Term|null $term */
$context = isset($context) ? (string) $context : 'archive';
$term = isset($term) && $term instanceof \WP_Term ? $term : null;

$ids = array();
while (\have_posts())
{
    \the_post();
    $ids[] = (int) \get_the_ID();
}
$found = (int) ($GLOBALS['wp_query']->found_posts ?? count($ids));
?>
<main class="cegg-deal-main cegg-deal-archive">
    <?php echo DealTemplates::part('_breadcrumbs', array('context' => $context, 'object_id' => $term ? (int) $term->term_id : 0)); // phpcs:ignore ?>
    <?php if ($term) : ?>
        <?php echo DealTemplates::part('_term_header', array('term' => $term, 'found' => $found)); // phpcs:ignore ?>
    <?php else : ?>
        <header class="cegg-deal-archive__head">
            <h1 class="cegg-deal-archive__title"><?php echo \esc_html($title); ?></h1>
            <?php if ($description) : ?><div class="cegg-deal-archive__desc"><?php echo \wp_kses_post($description); ?></div><?php endif; ?>
        </header>
        <?php echo DealTemplates::part('_chips', array('parent' => 0, 'current' => 0)); // phpcs:ignore ?>
    <?php endif; ?>
    <?php echo DealTemplates::part('_toolbar'); // phpcs:ignore ?>
    <?php if ($ids) : ?>
        <?php echo DealTemplates::part('_grid', array('post_ids' => $ids, 'columns' => 4)); // phpcs:ignore ?>
        <div class="cegg-deal-pagination"><?php \the_posts_pagination(); ?></div>
    <?php else : ?>
        <div class="cegg-deal-empty"><?php \esc_html_e('No deals right now. Check back soon.', 'content-egg-tpl'); ?></div>
    <?php endif; ?>
    <?php
    if ($term && $term->taxonomy === DealPostType::TAX_STORE && !\is_paged())
    {
        $domain = (string) \get_term_meta($term->term_id, DealPostType::TERM_META_DOMAIN, true);
        $shop = $domain !== '' ? ShopStore::get($domain) : null;
        if ($shop && trim(\wp_strip_all_tags((string) ($shop['info'] ?? ''))) !== '')
            /* translators: %s: store name */
            echo '<section class="cegg-deal-section cegg-deal-section--about"><h2 class="cegg-deal-section__title">' . \esc_html(sprintf(__('About %s', 'content-egg-tpl'), $term->name)) . '</h2>' . \wp_kses_post($shop['info']) . '</section>'; // phpcs:ignore
    }
    ?>
</main>
