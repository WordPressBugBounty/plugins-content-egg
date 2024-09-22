<?php defined('\ABSPATH') || exit; ?>
<?php if (\ContentEgg\application\Plugin::isFree() || \ContentEgg\application\Plugin::isInactiveEnvato()) : ?>
    <div class="cegg-maincol">
    <?php endif; ?>
    <div class="wrap">
        <h2><?php esc_html_e('Affiliate Egg Integration', 'content-egg') ?></h2>
        <?php settings_errors(); ?>

        <?php if (!ContentEgg\application\admin\AeIntegrationConfig::isAEIntegrationPosible()) : ?>
            <p>
                <a target="_blank" href="https://www.keywordrush.com/affiliateegg">Affiliate Egg</a> is another plugin offered by our team for adding affiliate products to your website. The key advantages of Affiliate Egg include:
            </p>
            <ul>
                <li>No API access required. The plugin extracts data directly from store websites.</li>
                <li>Custom parsers can be created for nearly any store.</li>
                <li>Affiliate Egg parsers integrate seamlessly as separate modules within the Content Egg plugin, enabling product price updates, price comparisons, templates, and other advanced Content Egg features.</li>
            </ul>
            <p>
                Activate Affiliate Egg parsers as independent modules in Content Egg for enhanced functionality.
            </p>
            <a target="_blank" href="https://ce-docs.keywordrush.com/modules/affiliate-egg-integration"><?php esc_html_e('Read more...', 'content-egg'); ?></a>
        <?php endif; ?>

        <?php if (!ContentEgg\application\admin\AeIntegrationConfig::isAEIntegrationPosible()) : ?>
            <div>
                <b><?php esc_html_e('Follow these steps to get started', 'content-egg'); ?>:</b>
                <ol>
                    <li><?php echo sprintf(__('Install and activate <a target="_blank" href="%s">Affiliate Egg Pro</a>', 'content-egg'), 'https://www.keywordrush.com/affiliateegg'); ?></li>
                </ol>
            </div>
        <?php else : ?>
            <form action="options.php" method="POST">
                <?php settings_fields($page_slug); ?>
                <table class="form-table">
                    <?php \do_settings_fields($page_slug, 'default'); ?>
                </table>
                <?php submit_button(); ?>
            </form>
        <?php endif; ?>
    </div>
    <?php if (\ContentEgg\application\Plugin::isFree() || \ContentEgg\application\Plugin::isInactiveEnvato()) : ?>
    </div>
    <?php include('_promo_box.php'); ?>
<?php endif; ?>