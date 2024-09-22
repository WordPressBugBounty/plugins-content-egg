<?php defined('\ABSPATH') || exit; ?>
<div class="cegg-rightcol">

    <?php if (\ContentEgg\application\Plugin::isFree()) : ?>
        <div class="cegg-box" style="margin-top: 95px;">
            <div class="cegg-box-container">
                <a target="_blank" href="https://www.keywordrush.com/bundles?ref=BUNDLE25&utm_source=cegg&utm_medium=referral&utm_campaign=plugin">
                    <img title="Limited-Time Exclusive: Save Big on All Plugin Bundles!" alt="Limited-Time Exclusive: Save Big on All Plugin Bundles!" width="100%" src="<?php echo esc_attr(\ContentEgg\PLUGIN_RES); ?>/img/bundle25.webp">
                </a>
            </div>
        </div>

    <?php endif; ?>

    <?php if (\ContentEgg\application\Plugin::isEnvato()) : ?>
        <div class="cegg-box" style="margin-top: 95px;">
            <h2><?php esc_html_e('Activate plugin', 'content-egg'); ?></h2>
            <p><?php esc_html_e('To enjoy all the benefits of Content Egg Pro, please activate your copy of the plugin.', 'content-egg'); ?></p>
            <p><?php esc_html_e('By activating your Content Egg license, you will unlock premium options, including direct plugin updates, access to the user panel, and official support.', 'content-egg'); ?></p>
            <p>
                <a class="button-cegg-banner" href="<?php echo esc_url(\get_admin_url(\get_current_blog_id(), 'admin.php?page=content-egg-lic')); ?>"><?php esc_html_e('Activate', 'content-egg'); ?></a>
            </p>
        </div>
    <?php endif; ?>
</div>