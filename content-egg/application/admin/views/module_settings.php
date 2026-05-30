<?php

use ContentEgg\application\components\ModuleCloneManager;

defined('\ABSPATH') || exit; ?>
<?php if (\ContentEgg\application\Plugin::isInactiveEnvato()) : ?>
    <div class="cegg-maincol">
    <?php endif; ?>
    <div class="wrap">
        <div class="cegg5-container">
            <h2 class="h4 d-flex align-items-center justify-content-between mb-2 mt-4" style="height: 30px;">

                <span><?php esc_html_e('Module Settings', 'content-egg'); ?></span>
                <div class="d-flex align-items-center">
                    <?php include __DIR__ . '/_version_badge.php'; ?>
                </div>

            </h2>
        </div>
        <h2 class="nav-tab-wrapper">
            <a href="?page=content-egg-modules" class="nav-tab<?php if (!empty($_GET['page']) && $_GET['page'] == 'content-egg-modules') echo ' nav-tab-active'; ?>">
                <span class="dashicons dashicons-menu-alt3"></span>
            </a>
            <?php foreach (ContentEgg\application\components\ModuleManager::getInstance()->getConfigurableModules(true) as $m) : ?>
                <?php if ($m->isDeprecated() && !$m->isActive()) continue; ?>
                <?php $c = $m->getConfigInstance(); ?>
                <a href="?page=<?php echo \esc_attr($c->page_slug()); ?>" class="nav-tab<?php if (!empty($_GET['page']) && $_GET['page'] == $c->page_slug()) echo ' nav-tab-active'; ?>">
                    <span<?php if ($m->isDeprecated()) : ?> style="color: darkgray;" <?php endif; ?>>
                        <?php echo \esc_html($m->getName()); ?>
                        </span>
                </a>
            <?php endforeach; ?>
        </h2>

        <div class="cegg-wrap">
            <div class="cegg-maincol">
                <h3>
                    <?php if ($module->isFeedParser() && !$module->isActive()) : ?>
                        <?php esc_html_e('Add new feed module', 'content-egg'); ?>
                    <?php else : ?>
                        <?php echo \esc_html(sprintf(__('%s Settings', 'content-egg'), $module->getName())); ?>
                    <?php endif; ?>

                    <?php if ($docs_uri = $module->getDocsUri()) echo sprintf('<a target="_blank" class="page-title-action" href="%s">' . esc_html(__('Documentation', 'content-egg')) . '</a>', esc_url_raw($docs_uri)); ?>

                </h3>

                <?php if ($module->isDeprecated()) : ?>
                    <div class="cegg-warning">

                        <?php if ($module->getId() != 'Amazon' && $module->getId() != 'AmazonNoApi') : ?>
                            <strong>
                                <?php esc_html_e('WARNING:', 'content-egg'); ?>
                                <?php esc_html_e('This module is deprecated', 'content-egg'); ?>
                                (<a target="_blank" href="<?php echo esc_url_raw(\ContentEgg\application\Plugin::pluginDocsUrl()); ?>/modules/deprecatedmodules"><?php esc_html_e('what does this mean', 'content-egg'); ?></a>).
                            </strong>
                        <?php endif; ?>

                    </div>
                <?php endif; ?>

                <?php if (!empty($module) && $requirements = $module->requirements()) : ?>
                    <div class="cegg-warning">
                        <strong>
                            <?php echo esc_html_e('WARNING:', 'content-egg'); ?>
                            <?php esc_html_e('This module cannot be activated!', 'content-egg') ?>
                            <?php esc_html_e('Please fix the following error(s):', 'content-egg') ?>
                            <ul>
                                <li><?php echo wp_kses_post(join('</li><li>', $requirements)); ?></li>
                            </ul>

                        </strong>
                    </div>
                <?php endif; ?>

                <?php \settings_errors(); ?>
                <form action="options.php" method="POST">
                    <?php \settings_fields($config->page_slug()); ?>
                    <table class="form-table">
                        <?php \do_settings_sections($config->page_slug()); ?>
                    </table>
                    <?php \submit_button(); ?>
                </form>

            </div>

            <div class="cegg-rightcol">

                <?php if (! empty($module) && $module->isFeedModule()) :
                    $last_import    = $module->getLastImportDateReadable();
                    $product_count  = (int) $module->getProductCount();
                    $last_error     = $module->getLastImportError();
                    $last_notice    = $module->getLastImportNotice();
                    $is_import_in_progress = $module->isImportInProgress();
                    $is_import_scheduled = $module->isImportScheduled();
                    $tools_page_url = admin_url('admin.php?page=content-egg-tools');

                    $status_label = '';
                    $status_class = '';
                    if ($is_import_in_progress) {
                        $status_label = __('In progress', 'content-egg');
                        $status_class = 'cegg-badge--progress';
                    } elseif ($is_import_scheduled) {
                        $status_label = __('Scheduled', 'content-egg');
                        $status_class = 'cegg-badge--scheduled';
                    } elseif ($product_count > 0) {
                        if ($module->isImportTime()) {
                            $status_label = __('Sync due', 'content-egg');
                            $status_class = 'cegg-badge--stale';
                        } else {
                            $status_label = __('Ready', 'content-egg');
                            $status_class = 'cegg-badge--idle';
                        }
                    }

                    $has_body_content = $last_import || $is_import_in_progress || $is_import_scheduled || $last_error || $last_notice;
                ?>

                    <!-- Feed Status card -->
                    <div class="cegg-card">
                        <div class="cegg-card__header"><?php esc_html_e('Feed Status', 'content-egg'); ?></div>
                        <div class="cegg-card__body">
                            <?php if ($has_body_content) : ?>
                                <ul class="cegg-stats">
                                    <?php if ($last_import) : ?>
                                        <li>
                                            <span><?php esc_html_e('Products', 'content-egg'); ?></span>
                                            <span class="cegg-stats__value"><?php echo esc_html(number_format_i18n($product_count)); ?></span>
                                        </li>
                                        <li>
                                            <span><?php esc_html_e('Last sync', 'content-egg'); ?></span>
                                            <span class="cegg-stats__value"><?php echo esc_html($last_import); ?></span>
                                        </li>
                                    <?php endif; ?>
                                    <?php if ($status_label) : ?>
                                        <li>
                                            <span><?php esc_html_e('Status', 'content-egg'); ?></span>
                                            <span class="cegg-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span>
                                        </li>
                                    <?php endif; ?>
                                </ul>

                                <?php if ($is_import_in_progress) : ?>
                                    <div class="notice notice-warning inline">
                                        <p><?php esc_html_e('Feed sync in progress. Please wait until it completes.', 'content-egg'); ?></p>
                                    </div>
                                <?php elseif ($is_import_scheduled) : ?>
                                    <div class="notice notice-info inline">
                                        <p><?php esc_html_e('Feed sync scheduled. It will run automatically soon.', 'content-egg'); ?></p>
                                    </div>
                                <?php endif; ?>

                                <?php if ($last_error) : ?>
                                    <div class="notice notice-error inline">
                                        <p>
                                            <strong><?php esc_html_e('Last error:', 'content-egg'); ?></strong>
                                            <?php echo esc_html($last_error); ?>
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <?php if ($last_notice) : ?>
                                    <div class="notice notice-info inline">
                                        <p><?php echo esc_html($last_notice); ?></p>
                                    </div>
                                <?php endif; ?>
                            <?php else : ?>
                                <p class="cegg-empty-state"><?php esc_html_e('No imports yet. The feed will be imported automatically by cron.', 'content-egg'); ?></p>
                            <?php endif; ?>
                        </div>

                        <?php if ($is_import_in_progress || $is_import_scheduled) : ?>
                            <div class="cegg-card__footer">
                                <button type="button" class="button button-secondary" onclick="window.location.reload();">
                                    <?php esc_html_e('Refresh Status', 'content-egg'); ?>
                                </button>
                            </div>
                        <?php elseif ($module->isActive()) : ?>
                            <div class="cegg-card__footer">
                                <?php
                                $reset_raw = add_query_arg([
                                    'action' => 'feed-reset',
                                    'module' => rawurlencode($module->getId()),
                                ], $tools_page_url);
                                $reset_url = wp_nonce_url($reset_raw, 'cegg_feed-reset');
                                ?>
                                <a href="<?php echo esc_url($reset_url); ?>" class="button button-primary" rel="noopener">
                                    <?php echo $last_import
                                        ? esc_html__('Reload Feed Data Now', 'content-egg')
                                        : esc_html__('Import Feed Now', 'content-egg'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Export Tools card -->
                    <?php if ($last_import && $product_count) : ?>
                        <div class="cegg-card">
                            <div class="cegg-card__header"><?php esc_html_e('Export Tools', 'content-egg'); ?></div>
                            <div class="cegg-card__body">
                                <div class="cegg-export-row">
                                    <?php
                                    $exports = [
                                        'url'           => __('URLs', 'content-egg'),
                                        'ean'           => __('EANs', 'content-egg'),
                                        'ean_duplicate' => __('Duplicate EANs', 'content-egg'),
                                    ];
                                    foreach ($exports as $field => $label) :
                                        $raw_url   = add_query_arg([
                                            'action' => 'feed-export',
                                            'field'  => $field,
                                            'module' => rawurlencode($module->getId()),
                                        ], $tools_page_url);
                                        $nonce_url = wp_nonce_url($raw_url, 'cegg_feed-export');
                                    ?>
                                        <a href="<?php echo esc_url($nonce_url); ?>" class="button" target="_blank" rel="noopener">
                                            <?php echo esc_html($label); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

                <!-- Module Actions card -->
                <div class="cegg-card">
                    <div class="cegg-card__header"><?php esc_html_e('Module', 'content-egg'); ?></div>
                    <div class="cegg-card__body">
                        <p class="cegg-module-id">
                            <strong><?php esc_html_e('ID:', 'content-egg'); ?></strong>
                            <?php echo esc_html($module->getId()); ?>
                        </p>

                        <div class="cegg-actions">
                            <?php if (ModuleCloneManager::isCloningAllowed($module->getId())): ?>
                                <a class="button button-secondary" href="<?php echo esc_url_raw(
                                                                                wp_nonce_url(
                                                                                    get_admin_url(
                                                                                        get_current_blog_id(),
                                                                                        'admin.php?page=content-egg-modules&action=clone&module=' . urlencode($module->getId())
                                                                                    ),
                                                                                    'ce_clone_module_action'
                                                                                )
                                                                            ); ?>">
                                    <?php esc_html_e('Clone This Module', 'content-egg'); ?>
                                </a>
                            <?php endif; ?>

                            <?php if ($module->isFeedParser() || $module->isClone()): ?>
                                <a class="button-link-delete"
                                    href="<?php echo esc_url_raw(
                                                wp_nonce_url(
                                                    get_admin_url(
                                                        get_current_blog_id(),
                                                        'admin.php?page=content-egg-modules&action=delete_clone&module=' . urlencode($module->getId())
                                                    ),
                                                    'ce_remove_module_action'
                                                )
                                            ); ?>"
                                    onclick="return confirm('Are you sure you want to delete this module? This action will PERMANENTLY REMOVE all module settings and associated products!');">
                                    <?php esc_html_e('Delete This Module', 'content-egg'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <?php if (\ContentEgg\application\Plugin::isInactiveEnvato()) : ?>
    </div>
    <?php include('_promo_box.php'); ?>
<?php endif; ?>