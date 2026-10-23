<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;


/**
 * McpBridge class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Optional bridge to the WordPress MCP Adapter (design spec §8): registers a
 * dedicated 'content-egg' MCP server when the adapter plugin is installed.
 * Written against MCP Adapter v0.5 (create_server signature); every external
 * symbol is guarded because the adapter may be absent, older, or newer —
 * on any mismatch we stay inert and abilities remain reachable through the
 * adapter's default server (meta.mcp.public) and plain REST.
 */
final class McpBridge
{
    /** True only once create_server() has actually mounted our MCP route. */
    private static $serving = false;

    const ROUTE = '/content-egg/mcp';

    public static function initAction(): void
    {
        \add_action('mcp_adapter_init', array(self::class, 'registerServer'));
        \add_filter('rest_post_dispatch', array(self::class, 'patchBundledAdapterReply'), 10, 3);
    }

    /**
     * Patch our MCP route's replies where the adapter WooCommerce bundles falls
     * short of strict clients such as Codex.
     *
     * WooCommerce bundles MCP Adapter 0.3.0, and its classes win the autoload race
     * over the adapter plugin's own. 0.3.0 answers a notification
     * (notifications/initialized, sent right after the handshake) with 200 and an
     * empty body instead of 202; Codex parses that body as JSON, fails, and drops
     * the connection, so it saw no tools on WooCommerce sites. It also publishes
     * tool annotations without their MCP names (see addToolHints()).
     *
     * @param mixed $response
     * @param mixed $server
     * @param mixed $request
     * @return mixed
     */
    public static function patchBundledAdapterReply($response, $server, $request)
    {
        if (!($response instanceof \WP_REST_Response) || !($request instanceof \WP_REST_Request))
        {
            return $response;
        }

        if (self::isEmptyNotificationReply($request->get_method(), $request->get_route(), $response->get_status(), $response->get_data()))
        {
            $response->set_status(202);
        }
        elseif ($request->get_route() === self::ROUTE && is_array($response->get_data()))
        {
            $response->set_data(self::addToolHints($response->get_data()));
        }

        return $response;
    }

    /**
     * Pure: give tools/list annotations their MCP names. The adapter WooCommerce
     * bundles passes WordPress's annotations through as-is (readonly, destructive,
     * idempotent); clients look for readOnlyHint / destructiveHint /
     * idempotentHint, so without them Codex asks to approve every call, reads
     * included. An existing hint is never overwritten.
     */
    public static function addToolHints(array $data): array
    {
        // The adapter builds result (and often the tools in it) as objects, so
        // read and write every level whichever shape it has.
        $result = $data['result'] ?? null;
        $tools = self::field($result, 'tools');
        if (!is_array($tools))
        {
            return $data;
        }

        foreach ($tools as $i => $tool)
        {
            $annotations = self::field($tool, 'annotations');
            if (!is_array($annotations) && !is_object($annotations))
            {
                continue;
            }
            $tools[$i] = self::withField($tool, 'annotations', self::hinted($annotations));
        }

        $data['result'] = self::withField($result, 'tools', $tools);

        return $data;
    }

    /**
     * @param array|object $annotations
     * @return array|object
     */
    private static function hinted($annotations)
    {
        $map = array('readonly' => 'readOnlyHint', 'destructive' => 'destructiveHint', 'idempotent' => 'idempotentHint');
        foreach ($map as $wp => $mcp)
        {
            $value = self::field($annotations, $wp);
            if ($value !== null && self::field($annotations, $mcp) === null)
            {
                $annotations = self::withField($annotations, $mcp, (bool) $value);
            }
        }
        return $annotations;
    }

    /**
     * @param mixed $node
     * @return mixed
     */
    private static function field($node, string $key)
    {
        if (is_array($node))
        {
            return $node[$key] ?? null;
        }
        return is_object($node) && isset($node->$key) ? $node->$key : null;
    }

    /**
     * A copy of $node with $key set; objects are cloned, never changed in place.
     *
     * @param array|object $node
     * @param mixed $value
     * @return array|object
     */
    private static function withField($node, string $key, $value)
    {
        if (is_object($node))
        {
            $node = clone $node;
            $node->$key = $value;
            return $node;
        }
        $node[$key] = $value;
        return $node;
    }

    /**
     * Pure: a 200 with no data on our MCP route can only be a notification reply;
     * every JSON-RPC request carries an id and gets a body.
     *
     * @param mixed $data
     */
    public static function isEmptyNotificationReply(string $method, string $route, int $status, $data): bool
    {
        return $method === 'POST' && $route === self::ROUTE && $status === 200 && $data === null;
    }

    /**
     * Is MCP really being served for Content Egg on this request?
     *
     * Deliberately NOT class_exists('\WP\MCP\Core\McpAdapter'): the adapter ships
     * as a library inside other plugins (WooCommerce bundles it), so that class
     * exists on sites where the adapter plugin is not active. registerServer()
     * runs on 'mcp_adapter_init', which only the active plugin fires, so this
     * flag is the only signal that /wp-json/content-egg/mcp exists.
     */
    public static function isServing(): bool
    {
        return self::$serving;
    }

    /**
     * @param mixed $adapter
     */
    public static function registerServer($adapter): void
    {
        if (!is_object($adapter) || !method_exists($adapter, 'create_server'))
        {
            return;
        }

        $transport = '\WP\MCP\Transport\HttpTransport';
        $error_handler = '\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler';
        $observability = '\WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler';

        if (!class_exists($transport) || !class_exists($error_handler))
        {
            return;
        }

        $names = AbilitiesRegistrar::enabledAbilityNames();

        try
        {
            $adapter->create_server(
                'content-egg',                       // server id
                'content-egg',                       // REST namespace
                'mcp',                               // REST route -> /wp-json/content-egg/mcp
                'Content Egg',
                'Affiliate product management and page building abilities for Content Egg.',
                AbilitiesRegistrar::VERSION,
                array($transport),
                $error_handler,
                class_exists($observability) ? $observability : null,
                $names,
                array(),
                array()
            );

            self::$serving = true;
        }
        catch (\Throwable $e)
        {
            error_log('[content-egg abilities] MCP server registration failed: ' . $e->getMessage());
        }
    }
}
