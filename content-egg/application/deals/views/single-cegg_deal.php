<?php

defined('\ABSPATH') || exit;

use ContentEgg\application\deals\DealService;
use ContentEgg\application\deals\DealTemplates;
use ContentEgg\application\deals\DealView;

/**
 * The single deal page. Override by copying to yourtheme/single-cegg_deal.php,
 * or override one part from yourtheme/content-egg/deals/.
 */

DealTemplates::header();

echo '<main class="cegg-deal-main">';

while (\have_posts())
{
    \the_post();
    $post_id = (int) \get_the_ID();
    $deal = DealView::deal($post_id);
    $item = DealView::item($post_id);

    \do_action('cegg_deal_before', $post_id);
    echo DealTemplates::part('_breadcrumbs', array('context' => 'single', 'object_id' => $post_id)); // phpcs:ignore

    // Price, coupon and link are the protected content here, not just the text.
    if (\post_password_required($post_id))
    {
        echo '<article class="cegg-deal cegg-deal-section"><h1 class="cegg-deal-hero__title">' . \esc_html(\get_the_title($post_id)) . '</h1>' . \get_the_password_form($post_id) . '</article>'; // phpcs:ignore
        \do_action('cegg_deal_after', $post_id);
        continue;
    }
    ?>
    <article id="post-<?php echo (int) $post_id; ?>" <?php \post_class('cegg-deal'); ?>>
        <?php echo DealTemplates::part('_hero', array('post_id' => $post_id, 'deal' => $deal, 'item' => $item)); // phpcs:ignore ?>
        <?php if (trim((string) \get_the_content()) !== '') : ?>
            <section class="cegg-deal-section cegg-deal-section--content"><h2 class="cegg-deal-section__title"><?php \esc_html_e('Deal details', 'content-egg-tpl'); ?></h2><div class="cegg-deal__content"><?php \the_content(); ?></div></section>
        <?php endif; ?>
        <?php echo DealTemplates::part('_sections', array('post_id' => $post_id, 'deal' => $deal, 'item' => $item, 'pin' => DealService::getPin($post_id))); // phpcs:ignore ?>
    </article>
    <?php
    if (\comments_open() || \get_comments_number())
    {
        echo '<section id="cegg-deal-comments" class="cegg-deal-section cegg-deal-section--comments">';
        // Block themes ship no comments.php, so comments_template() would fall
        // back to the deprecated theme-compat markup. Render the core blocks.
        if (\wp_is_block_theme())
            echo \do_blocks('<!-- wp:comments --><div class="wp-block-comments"><!-- wp:comments-title /--><!-- wp:comment-template --><!-- wp:comment-author-name /--><!-- wp:comment-date /--><!-- wp:comment-content /--><!-- wp:comment-reply-link /--><!-- /wp:comment-template --><!-- wp:comments-pagination /--><!-- wp:post-comments-form /--></div><!-- /wp:comments -->'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        else
            \comments_template();
        echo '</section>';
    }

    \do_action('cegg_deal_after', $post_id);
}

echo '</main>';

DealTemplates::footer();
