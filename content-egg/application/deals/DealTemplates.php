<?php

namespace ContentEgg\application\deals;

defined('\ABSPATH') || exit;

use ContentEgg\application\Plugin;

/**
 * DealTemplates class file
 *
 * Serves the deal page templates the way Cashback Tracker serves shop pages: a
 * copy in the theme wins, otherwise the plugin's own file. Partials are
 * overridable one by one from yourtheme/content-egg/deals/.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DealTemplates
{
    const HEADER_PART = 'deals';

    private static $localized = false;

    public static function initAction(): void
    {
        \add_filter('single_template', array(__CLASS__, 'single'));
        \add_filter('archive_template', array(__CLASS__, 'archive'));
        \add_filter('taxonomy_template', array(__CLASS__, 'taxonomy'));
        \add_action('wp_enqueue_scripts', array(__CLASS__, 'registerAssets'));
        \add_filter('wp_robots', array(__CLASS__, 'robots'));
        // Rank Math drops every wp_robots filter and prints its own tag;
        // SEOPress prints a second tag next to core's.
        \add_filter('rank_math/frontend/robots', array(__CLASS__, 'rankMathRobots'));
        \add_filter('seopress_titles_noindex_bypass', array(__CLASS__, 'seopressNoindex'));
    }

    public static function single($template)
    {
        return \is_singular(DealPostType::POST_TYPE) ? self::resolve('single-cegg_deal.php', $template) : $template;
    }

    public static function archive($template)
    {
        return \is_post_type_archive(DealPostType::POST_TYPE) ? self::resolve('archive-cegg_deal.php', $template) : $template;
    }

    public static function taxonomy($template)
    {
        if (\is_tax(DealPostType::TAX_STORE))
            return self::resolve('taxonomy-deal_store.php', $template);

        if (\is_tax(DealPostType::TAX_CATEGORY))
            return self::resolve('taxonomy-deal_category.php', $template);

        return $template;
    }

    public static function viewDir(): string
    {
        return __DIR__ . '/views/';
    }

    public static function part(string $name, array $data = array()): string
    {
        $name = preg_replace('/[^a-z0-9_\-]/', '', $name);
        $file = \locate_template('content-egg/deals/' . $name . '.php') ?: self::viewDir() . $name . '.php';

        if (!is_readable($file))
            return '';

        self::enqueue();

        extract($data, EXTR_SKIP);
        ob_start();
        include $file;

        return (string) ob_get_clean();
    }

    public static function register(): void
    {
        if (\wp_style_is('cegg-deals', 'registered'))
            return;

        \wp_register_style('cegg-deals', \ContentEgg\PLUGIN_RES . '/deals/deals.css', array(), Plugin::version());
        \wp_register_script('cegg-deals', \ContentEgg\PLUGIN_RES . '/deals/deals.js', array(), Plugin::version(), true);
    }

    public static function registerAssets(): void
    {
        self::register();

        // Deal pages enqueue up front so styles print in <head>; the block and
        // shortcode enqueue when they render.
        if (\is_singular(DealPostType::POST_TYPE) || \is_post_type_archive(DealPostType::POST_TYPE) || \is_tax(array(DealPostType::TAX_STORE, DealPostType::TAX_CATEGORY)))
            self::enqueue();
    }

    public static function enqueue(): void
    {
        self::register();

        \wp_enqueue_style('cegg-deals');
        \wp_enqueue_script('cegg-deals');

        if (self::$localized)
            return;

        self::$localized = true;
        \wp_localize_script('cegg-deals', 'ceggDeals', array(
            'rest' => \esc_url_raw(\rest_url('cegg/v1/deals/')),
            // Only a page rendered for a logged-in user carries a nonce; the
            // anonymous copies a page cache serves never do.
            'nonce' => \is_user_logged_in() ? \wp_create_nonce('wp_rest') : '',
            'i18n' => array(
                'endsIn' => __('Ends in', 'content-egg-tpl'),
                'ended' => __('Ended', 'content-egg-tpl'),
                'error' => __('Your vote could not be saved.', 'content-egg-tpl'),
                'copied' => __('Copied', 'content-egg-tpl'),
            ),
        ));
    }

    public static function robots(array $robots): array
    {
        if (self::isNoindexed())
        {
            $robots['noindex'] = true;
            if (empty($robots['nofollow']))
                $robots['follow'] = true;
        }

        return $robots;
    }

    public static function rankMathRobots($robots)
    {
        if (!self::isNoindexed())
            return $robots;

        $robots = is_array($robots) ? $robots : array();
        $robots['index'] = 'noindex';
        if (empty($robots['follow']))
            $robots['follow'] = 'follow';

        return $robots;
    }

    public static function seopressNoindex($noindex)
    {
        return self::isNoindexed() ? 'noindex' : $noindex;
    }

    /**
     * An expired deal (opt-in), or a deal archive narrowed or reordered by the
     * toolbar's query args: those are copies of the plain archive. Pagination
     * lives in the path and is left alone.
     */
    private static function isNoindexed(): bool
    {
        if (\is_singular(DealPostType::POST_TYPE))
        {
            return DealsSettings::noindexExpired()
                && \get_post_meta((int) \get_queried_object_id(), DealPostType::META_STATUS, true) === DealStatus::EXPIRED;
        }

        if (!\is_post_type_archive(DealPostType::POST_TYPE) && !\is_tax(array(DealPostType::TAX_STORE, DealPostType::TAX_CATEGORY)))
            return false;

        $params = (array) \apply_filters('cegg_deal_noindex_params', array('sort', 'coupon', DealPostType::TAX_STORE, DealPostType::TAX_CATEGORY));
        foreach ($params as $param)
        {
            if (isset($_GET[$param])) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                return true;
        }

        return false;
    }

    /**
     * Ported from Cashback Tracker's TemplateManager::header(): a block theme
     * has no header.php, and get_header() would fall through to theme-compat's
     * 2010 chrome, so the document is built here instead.
     */
    public static function header(): void
    {
        if (!self::isBlockTheme())
        {
            \get_header(self::HEADER_PART);
            return;
        }

        echo '<!DOCTYPE html>' . "\n";
        echo '<html ' . \get_language_attributes() . '>' . "\n";
        echo '<head>' . "\n";
        echo '<meta charset="' . \esc_attr(\get_bloginfo('charset')) . '" />' . "\n";
        \wp_head();
        echo '</head>' . "\n";
        echo '<body class="' . \esc_attr(implode(' ', \get_body_class())) . '">' . "\n";
        \wp_body_open();
        echo '<div class="wp-site-blocks">' . "\n";
        \block_header_area();
    }

    public static function footer(): void
    {
        if (!self::isBlockTheme())
        {
            \get_footer(self::HEADER_PART);
            return;
        }

        \block_footer_area();
        echo '</div>' . "\n";
        \wp_footer();
        echo '</body>' . "\n" . '</html>';
    }

    private static function isBlockTheme(): bool
    {
        return function_exists('wp_is_block_theme') && \wp_is_block_theme();
    }

    private static function resolve(string $file, $fallback)
    {
        if ($theme = \locate_template($file))
            return $theme;

        $own = self::viewDir() . $file;

        return is_readable($own) ? $own : $fallback;
    }
}
