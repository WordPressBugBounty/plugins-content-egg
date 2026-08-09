<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

/**
 * AgentGuideRestController class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Serves the agent guide markdown publicly (design spec §13.5: same public
 * posture as the OpenAPI endpoint — documents the discoverable surface;
 * execution still requires auth).
 */
final class AgentGuideRestController
{
    const REST_NAMESPACE = 'content-egg/v1';
    const ROUTE = '/agent-guide';

    public static function initAction(): void
    {
        \add_action('rest_api_init', array(self::class, 'registerRoutes'));
    }

    public static function registerRoutes(): void
    {
        \register_rest_route(self::REST_NAMESPACE, self::ROUTE, array(
            'methods' => \WP_REST_Server::READABLE,
            'callback' => array(self::class, 'handle'),
            'permission_callback' => '__return_true',
        ));
    }

    public static function handle()
    {
        return \rest_ensure_response(array('markdown' => self::markdown()));
    }

    /**
     * The agent guide markdown with this install's real URLs filled in.
     *
     * The shipped file uses a {site} placeholder so it stays install-agnostic;
     * we fill in the real base so the documented URIs are copy-paste-ready.
     * Replacing the "{site}/wp-json/" unit with the true REST root (get_rest_url)
     * is correct for both pretty and plain permalinks (plain returns the
     * ...?rest_route= form, which the route paths append to). Shared by the
     * public REST endpoint and the content-egg/get-guide ability.
     */
    public static function markdown(?bool $paid = null): string
    {
        if ($paid === null)
        {
            $paid = \ContentEgg\application\Plugin::isPaidBuild();
        }

        $file = __DIR__ . '/guide/agent-guide.md';
        $markdown = is_file($file) ? (string) file_get_contents($file) : '';

        $markdown = str_replace('{site}/wp-json/', \get_rest_url(), $markdown);
        $markdown = str_replace('{site}', \untrailingslashit(\home_url()), $markdown);

        // {palette} — the live block menu, generated from the catalog so it can
        // never drift from the registry (new blocks appear automatically).
        if (strpos($markdown, '{palette}') !== false)
        {
            $markdown = str_replace('{palette}', \ContentEgg\application\BlockKit\Catalog::paletteMarkdown(), $markdown);
        }

        // {capabilities} — what this build cannot do. Empty on a paid build, in
        // which case the placeholder's whole line is removed (the newline on both
        // sides is consumed) so the served guide stays byte-identical to one
        // without this feature.
        $capabilities = BuildCapabilities::markdown($paid);
        if ($capabilities === '')
        {
            $markdown = str_replace("\n{capabilities}\n", '', $markdown);
        }
        else
        {
            $markdown = str_replace('{capabilities}', $capabilities, $markdown);
        }

        return $markdown;
    }
}
