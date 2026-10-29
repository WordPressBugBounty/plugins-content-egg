<?php

namespace ContentEgg\application\blocks\dealsblock;

defined('\ABSPATH') || exit;

use ContentEgg\application\deals\DealQuery;
use ContentEgg\application\deals\DealsSettings;
use ContentEgg\application\deals\DealTemplates;

/**
 * DealsBlock class file
 *
 * "Today's deals", "Top this week", "Ending soon": one server-rendered grid,
 * as a block and as [content-egg-deals] for the classic editor.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DealsBlock
{
    const EDITOR_HANDLE = 'content-egg-deals-editor';

    public static function initAction(): void
    {
        // The deal styles as the block's editor style: WordPress prints a block's
        // editor_style into the iframed editor canvas, and on no other admin page.
        // (enqueue_block_assets could not do it: while it collects the iframe's
        // assets, WordPress forces should_load_block_editor_scripts_and_styles to
        // false.) On the front end the block enqueues them when it renders.
        DealTemplates::register();
        \register_block_type('content-egg/deals', array(
            'editor_script' => self::EDITOR_HANDLE,
            'editor_style' => 'cegg-deals',
            'render_callback' => array(__CLASS__, 'render'),
            'attributes' => self::attributes(),
        ));
        \add_action('enqueue_block_editor_assets', array(__CLASS__, 'enqueueEditor'));
    }

    /**
     * Registered whether or not deals are on, so switching deals off leaves
     * no raw [content-egg-deals] text in posts - render() prints nothing then.
     */
    public static function registerShortcode(): void
    {
        \add_shortcode('content-egg-deals', array(__CLASS__, 'render'));
    }

    public static function attributes(): array
    {
        return array(
            'limit' => array('type' => 'integer', 'default' => 8),
            'columns' => array('type' => 'integer', 'default' => 4),
            'sort' => array('type' => 'string', 'default' => 'new'),
            'store' => array('type' => 'string', 'default' => ''),
            'category' => array('type' => 'string', 'default' => ''),
            'days' => array('type' => 'integer', 'default' => 0),
            'hide_expired' => array('type' => 'boolean', 'default' => true),
            'coupon_only' => array('type' => 'boolean', 'default' => false),
        );
    }

    /**
     * $hideExpiredDefault applies when hide_expired is not supplied (a
     * shortcode without the attribute); render() passes the site setting.
     */
    public static function normalize(array $a, bool $hideExpiredDefault = true): array
    {
        $sort = (string) ($a['sort'] ?? 'new');

        return array(
            'limit' => max(1, min(48, (int) ($a['limit'] ?? 8))),
            'columns' => max(1, min(6, (int) ($a['columns'] ?? 4))),
            'sort' => in_array($sort, DealQuery::SORTS, true) ? $sort : 'new',
            'store' => self::slugs($a['store'] ?? ''),
            'category' => self::slugs($a['category'] ?? ''),
            'days' => max(0, min(3650, (int) ($a['days'] ?? 0))),
            'hide_expired' => self::flag($a['hide_expired'] ?? $hideExpiredDefault),
            'coupon' => self::flag($a['coupon_only'] ?? false),
        );
    }

    /**
     * A block sends real booleans; a shortcode sends strings.
     */
    private static function flag($value): bool
    {
        if (is_bool($value))
            return $value;

        $value = strtolower(trim((string) $value));

        return $value !== '' && !in_array($value, array('0', 'false', 'no', 'off'), true);
    }

    public static function render($attributes = array()): string
    {
        if (!DealsSettings::enabled())
            return '';

        $a = self::normalize(is_array($attributes) ? $attributes : array(), DealsSettings::hideExpired());

        $args = DealQuery::args($a, time());
        $args['no_found_rows'] = true;

        // Full posts, not fields=>ids: WP_Query then primes the post, meta and
        // term caches, and the thumbnails are primed below - each card would
        // otherwise pay its own uncached queries.
        $query = new \WP_Query($args);
        if (!$query->posts)
            return '';

        \update_post_thumbnail_cache($query);

        return DealTemplates::part('_grid', array('post_ids' => array_map('intval', \wp_list_pluck($query->posts, 'ID')), 'columns' => $a['columns']));
    }

    public static function enqueueEditor(): void
    {
        $asset_file = __DIR__ . '/block.asset.php';
        $asset = file_exists($asset_file) ? require $asset_file : array('dependencies' => array(), 'version' => false);

        \wp_register_script(self::EDITOR_HANDLE, \plugins_url('block.js', __FILE__), (array) $asset['dependencies'], $asset['version']);
    }

    private static function slugs($value): array
    {
        $out = array();
        foreach (explode(',', (string) $value) as $slug)
        {
            $slug = preg_replace('/[^a-z0-9\-_%]/', '', strtolower(trim($slug)));
            if ($slug !== '')
                $out[] = $slug;
        }

        return $out;
    }
}
