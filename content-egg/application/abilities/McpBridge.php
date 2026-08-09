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

    public static function initAction(): void
    {
        \add_action('mcp_adapter_init', array(self::class, 'registerServer'));
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
