<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\Plugin;

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

    /**
     * The guide's topics, in index order: name => when to load it. Each name
     * is a file in guide/topics/; the core guide lists them through {topics}.
     */
    const TOPICS = array(
        'page-building' => 'Before writing, building or editing any post or page: Egg Blocks vs live blocks, the block palette, node shapes, create-post and insert-blocks.',
        'products-and-modules' => 'Finding and attaching products: auditing modules, search-products, add-products-to-post, the manual Offer module, editing attached products.',
        'price-comparison' => 'Comparing one product across shops: finding it in several modules, deciding which offers are the same product, keeping comparison pages current, and linking variant pages (128 GB, 256 GB...) as a family with a variant switcher.',
        'woocommerce' => 'Working with WooCommerce products: product pages, the price sync setup and the recommended comparison product.',
        'deals' => 'Creating and maintaining deal posts: the deal model and statuses, create-post with a deal object, find-deals, update-deal, and the deal signals on search results.',
        'media-and-coupons' => 'Adding images, videos or coupons to a post.',
        'feeds-and-shops' => 'Adding a product source: a new affiliate shop (Affiliate Egg) or a product feed.',
    );

    /** get-guide / ?topic= value that returns core plus every topic. */
    const TOPIC_ALL = 'all';

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
            'args' => array(
                'topic' => array(
                    'type' => 'string',
                    'description' => 'One topic of the guide (' . implode(', ', self::topicNames()) . '), or "all". Omit for the core guide and the topic index.',
                ),
            ),
        ));
    }

    public static function handle($request = null)
    {
        $topic = $request instanceof \WP_REST_Request ? (string) $request->get_param('topic') : '';

        try
        {
            $markdown = self::markdown(null, $topic);
        }
        catch (\InvalidArgumentException $e)
        {
            return new \WP_Error('cegg_validation_failed', $e->getMessage(), array('status' => 400));
        }

        return \rest_ensure_response(array('markdown' => $markdown));
    }

    /** @return string[] */
    public static function topicNames(): array
    {
        return array_keys(self::TOPICS);
    }

    /**
     * Changes whenever any guide file changes, so an agent can tell whether
     * the topics it already loaded are still current. The plugin version and
     * the build flag are part of the input: the served text depends on what
     * this build can do, and a release can change that without touching a
     * guide file.
     */
    public static function guideVersion(): string
    {
        $hash = Plugin::version() . '|' . (Plugin::isPaidBuild() ? 'paid' : 'free') . '|';
        foreach (self::files() as $file)
        {
            $hash .= is_file($file) ? (string) file_get_contents($file) : '';
        }

        return substr(md5($hash), 0, 12);
    }

    /**
     * The agent guide markdown with this install's real URLs filled in.
     *
     * $topic '' = the core guide with the topic index; a topic name = that
     * topic alone; 'all' = core followed by every topic (the whole guide, as
     * it was served before the split).
     *
     * The shipped files use a {site} placeholder so they stay install-agnostic;
     * we fill in the real base so the documented URIs are copy-paste-ready.
     * Replacing the "{site}/wp-json/" unit with the true REST root (get_rest_url)
     * is correct for both pretty and plain permalinks (plain returns the
     * ...?rest_route= form, which the route paths append to). Shared by the
     * public REST endpoint and the content-egg/get-guide ability.
     *
     * @throws \InvalidArgumentException for an unknown topic
     */
    public static function markdown(?bool $paid = null, string $topic = ''): string
    {
        if ($paid === null)
        {
            $paid = \ContentEgg\application\Plugin::isPaidBuild();
        }

        $topic = trim($topic);
        if ($topic === '')
        {
            $markdown = self::read(self::coreFile());
        }
        elseif ($topic === self::TOPIC_ALL)
        {
            $parts = array();
            foreach (self::files() as $file)
            {
                $parts[] = rtrim(self::read($file), "\n");
            }
            $markdown = implode("\n\n", $parts) . "\n";
        }
        elseif (isset(self::TOPICS[$topic]))
        {
            $markdown = self::read(self::topicFile($topic));
        }
        else
        {
            throw new \InvalidArgumentException(sprintf(
                'Unknown guide topic "%s". Topics: %s; or "%s" for the whole guide, or no topic for the core guide.',
                $topic,
                implode(', ', self::topicNames()),
                self::TOPIC_ALL
            ));
        }

        // {topics} first: the index carries no other placeholder, but keep the
        // order safe if it ever does.
        if (strpos($markdown, '{topics}') !== false)
        {
            $markdown = str_replace('{topics}', self::topicIndex(), $markdown);
        }

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
        if (strpos($markdown, '{capabilities}') !== false)
        {
            $capabilities = BuildCapabilities::markdown($paid);
            if ($capabilities === '')
            {
                $markdown = str_replace("\n{capabilities}\n", '', $markdown);
            }
            else
            {
                $markdown = str_replace('{capabilities}', $capabilities, $markdown);
            }
        }

        return $markdown;
    }

    private static function topicIndex(): string
    {
        $lines = array();
        foreach (self::TOPICS as $name => $when)
        {
            $lines[] = '- `' . $name . '` — ' . $when;
        }

        return implode("\n", $lines);
    }

    /** core.md followed by every topic file, in index order. */
    private static function files(): array
    {
        $files = array(self::coreFile());
        foreach (self::topicNames() as $name)
        {
            $files[] = self::topicFile($name);
        }

        return $files;
    }

    private static function coreFile(): string
    {
        return __DIR__ . '/guide/core.md';
    }

    private static function topicFile(string $name): string
    {
        return __DIR__ . '/guide/topics/' . $name . '.md';
    }

    private static function read(string $file): string
    {
        return is_file($file) ? (string) file_get_contents($file) : '';
    }
}
