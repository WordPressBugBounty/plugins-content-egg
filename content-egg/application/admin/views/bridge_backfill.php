<?php

defined('\ABSPATH') || exit;

/** @var array $plan */
/** @var array|null $receipt */
/** @var bool $can_apply */
/** @var bool $live */

$insert     = $plan['insert'];
$active_now = isset($plan['active_now']) ? (int) $plan['active_now'] : 0;
$scanned_at = isset($plan['scanned_at']) ? (int) $plan['scanned_at'] : 0;
$back_url   = admin_url('admin.php?page=content-egg-product');
$self_url   = $back_url . '&action=bridge-backfill';
?>

<div class="wrap">

    <div class="cegg5-container">
        <h1 class="wp-heading-inline h3"><?php esc_html_e('Bridge Mappings', 'content-egg'); ?></h1>
        <a href="<?php echo esc_url($back_url); ?>" class="page-title-action">
            <?php esc_html_e('Back to Products', 'content-egg'); ?>
        </a>

        <p class="description" style="max-width:46em;">
            <?php esc_html_e(
                'A canonical Bridge Page mapping points a product at a page on your site. One mapping applies everywhere: every post containing that product links to the page, now and in future. Pages created by the Content Egg import tool record which product they came from, so the missing mappings can be created for them here — no re-importing.',
                'content-egg'
            ); ?>
        </p>
    </div>

    <?php if (isset($_GET['created'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php printf(
                    /* translators: %d: number of mappings created */
                    esc_html(_n('Created %d canonical mapping.', 'Created %d canonical mappings.', (int) $_GET['created'], 'content-egg')),
                    (int) $_GET['created']
                ); ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['undone'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php printf(
                    /* translators: %d: number of mappings removed */
                    esc_html(_n('Removed %d canonical mapping.', 'Removed %d canonical mappings.', (int) $_GET['undone'], 'content-egg')),
                    (int) $_GET['undone']
                ); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!$plan['stamped']) : ?>

        <div class="notice notice-info inline">
            <p><?php esc_html_e(
                    'No pages created by the Content Egg import tool were found. Only those pages record which product they came from, so only they can be matched automatically.',
                    'content-egg'
                ); ?></p>
        </div>

    <?php else : ?>

        <table class="widefat striped" style="max-width:46em; margin-top:1em;">
            <tbody>
                <tr>
                    <td><?php esc_html_e('Pages created by the import tool', 'content-egg'); ?></td>
                    <td><strong><?php echo (int) $plan['stamped']; ?></strong></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Mappings to create', 'content-egg'); ?></td>
                    <td><strong><?php echo count($insert); ?></strong></td>
                </tr>
                <tr>
                    <td style="padding-left:2em;">
                        <span class="dashicons dashicons-minus" style="color:#8c8f94;"></span>
                        <?php esc_html_e('change a link right away', 'content-egg'); ?>
                    </td>
                    <td><?php echo (int) $active_now; ?></td>
                </tr>
                <tr>
                    <td style="padding-left:2em;">
                        <span class="dashicons dashicons-minus" style="color:#8c8f94;"></span>
                        <?php esc_html_e('apply once the product is used on another post', 'content-egg'); ?>
                    </td>
                    <td><?php echo max(0, count($insert) - (int) $active_now); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Already mapped (left untouched)', 'content-egg'); ?></td>
                    <td><?php echo count($plan['already_mapped']); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Claimed by more than one page (skipped)', 'content-egg'); ?></td>
                    <td><?php echo count($plan['conflicts']); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Product not found on the page (skipped)', 'content-egg'); ?></td>
                    <td><?php echo count($plan['unresolved']); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e('Not published (skipped)', 'content-egg'); ?></td>
                    <td><?php echo count($plan['skipped_status']); ?></td>
                </tr>
            </tbody>
        </table>

        <?php if ($insert) : ?>
            <p class="description" style="max-width:46em;">
                <?php
                esc_html_e(
                    'A mapping only redirects a link where the product is used on another post — on its own page it is left alone. The rest are not wasted: they take effect as soon as you use that product somewhere else, which is exactly what a normal import with canonical Bridge Pages leaves behind.',
                    'content-egg'
                );
                ?>
                <?php if ($scanned_at) : ?>
                    <br>
                    <?php
                    printf(
                        /* translators: %s: date/time of the last product scan */
                        esc_html__('That split is an estimate from the last product scan (%s); the mappings themselves do not depend on it.', 'content-egg'),
                        esc_html(\ContentEgg\application\helpers\TemplateHelper::dateFormatFromGmt($scanned_at, true))
                    );
                    ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <?php if ($live) : ?>
            <div class="notice notice-warning inline" style="max-width:46em;">
                <p><?php esc_html_e(
                        'Your link destination is set to Bridge Page, so new mappings change where product links point as soon as they are created.',
                        'content-egg'
                    ); ?></p>
            </div>
        <?php else : ?>
            <p class="description" style="max-width:46em;">
                <?php esc_html_e(
                    'Your link destination is set to affiliate links, so these mappings stay inactive until you change that setting. Creating them now is safe.',
                    'content-egg'
                ); ?>
            </p>
        <?php endif; ?>

        <?php if ($can_apply && $insert) : ?>
            <form method="post" action="<?php echo esc_url($self_url); ?>" style="margin:1.5em 0;">
                <?php wp_nonce_field('cegg_bridge_backfill', 'cegg_bridge_backfill_nonce'); ?>
                <button type="submit" name="cegg_backfill_apply" value="1" class="button button-primary">
                    <?php printf(
                        /* translators: %d: number of mappings that will be created */
                        esc_html(_n('Create %d mapping', 'Create %d mappings', count($insert), 'content-egg')),
                        count($insert)
                    ); ?>
                </button>
            </form>
        <?php elseif (!$insert) : ?>
            <p style="margin:1.5em 0;"><strong><?php esc_html_e('Nothing to create — every page the import tool made is already mapped or skipped.', 'content-egg'); ?></strong></p>
        <?php endif; ?>

        <?php if ($can_apply && $receipt) : ?>
            <form method="post" action="<?php echo esc_url($self_url); ?>" style="margin:1em 0;">
                <?php wp_nonce_field('cegg_bridge_backfill', 'cegg_bridge_backfill_nonce'); ?>
                <button type="submit" name="cegg_backfill_undo" value="1" class="button">
                    <?php printf(
                        /* translators: %d: number of mappings the last run created */
                        esc_html(_n('Undo last back-fill (%d mapping)', 'Undo last back-fill (%d mappings)', count($receipt['rows']), 'content-egg')),
                        count($receipt['rows'])
                    ); ?>
                </button>
            </form>
        <?php endif; ?>

        <?php
        $lists = array(
            'conflicts' => array(
                'title' => __('Claimed by more than one page', 'content-egg'),
                'note'  => __('The same product was imported more than once, so there is no single correct page. Delete or merge the extra pages, then run this again.', 'content-egg'),
            ),
            'unresolved' => array(
                'title' => __('Product not found on the page', 'content-egg'),
                'note'  => __('The page records a product that is no longer stored on it, so the module cannot be identified.', 'content-egg'),
            ),
            'skipped_status' => array(
                'title' => __('Not published', 'content-egg'),
                'note'  => __('Drafts and trashed pages are skipped. Publish them and run this again.', 'content-egg'),
            ),
        );
        ?>

        <?php foreach ($lists as $bucket => $meta) : ?>
            <?php if (empty($plan[$bucket])) continue; ?>
            <h2 class="h5" style="margin-top:2em;"><?php echo esc_html($meta['title']); ?> (<?php echo count($plan[$bucket]); ?>)</h2>
            <p class="description" style="max-width:46em;"><?php echo esc_html($meta['note']); ?></p>
            <ul class="ul-disc">
                <?php foreach (array_slice($plan[$bucket], 0, 100) as $row) : ?>
                    <li>
                        <code><?php echo esc_html(($row['module_id'] ?? '') . ' ' . $row['unique_id']); ?></code>
                        <?php foreach ((array) ($row['post_ids'] ?? array($row['post_id'])) as $pid) : ?>
                            <a href="<?php echo esc_url(get_edit_post_link($pid)); ?>">#<?php echo (int) $pid; ?></a>
                        <?php endforeach; ?>
                        <?php if (!empty($row['post_status'])) : ?>
                            <em><?php echo esc_html($row['post_status']); ?></em>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($plan[$bucket]) > 100) : ?>
                <p class="description"><?php printf(
                        /* translators: %d: number of further entries not listed */
                        esc_html__('… and %d more.', 'content-egg'),
                        count($plan[$bucket]) - 100
                    ); ?></p>
            <?php endif; ?>
        <?php endforeach; ?>

    <?php endif; ?>

</div>
