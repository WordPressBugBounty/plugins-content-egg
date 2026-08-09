<?php

namespace ContentEgg\application\admin;

defined('\ABSPATH') || exit;

use ContentEgg\application\abilities\AbilitiesRegistrar;
use ContentEgg\application\abilities\AbilityLog;
use ContentEgg\application\abilities\AgentGuideRestController;
use ContentEgg\application\abilities\OpenApiRestController;
use ContentEgg\application\Plugin;

/**
 * AgentAccessController class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * "AI Agents" admin screen: the single place to turn Agent Access on, grab the
 * connection links, see what a connected assistant can do, and review its
 * activity. The on/off switch saves over REST (see AgentAccessRestController);
 * this controller only assembles data for the view.
 */
class AgentAccessController
{
    const slug = 'content-egg-agent-access';
    const DOCS_URL = 'https://ce-docs.keywordrush.com/ai-agents';

    /** Hook suffix of our submenu page, for asset scoping. */
    private $hook = '';

    public function __construct()
    {
        \add_action('admin_menu', array($this, 'addPage'), 20);
        \add_action('admin_menu', array($this, 'reorderMenu'), 999);
        \add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));
    }

    public function addPage(): void
    {
        $this->hook = (string) \add_submenu_page(
            Plugin::slug,
            __('AI Agents', 'content-egg'),
            __('AI Agents', 'content-egg') . self::menuBadge(),
            'manage_options',
            self::slug,
            array($this, 'renderPage')
        );
    }

    /**
     * Move the "AI Agents" item to sit right after "Import Tools" in the
     * submenu, independent of the order plugins happen to register in.
     */
    public function reorderMenu(): void
    {
        global $submenu;

        $parent = Plugin::slug;
        if (empty($submenu[$parent]))
        {
            return;
        }

        $self_key = null;
        foreach ($submenu[$parent] as $key => $item)
        {
            if (isset($item[2]) && $item[2] === self::slug)
            {
                $self_key = $key;
                break;
            }
        }

        if ($self_key === null)
        {
            return;
        }

        $self_item = $submenu[$parent][$self_key];
        unset($submenu[$parent][$self_key]);

        $rebuilt = array();
        $placed = false;
        foreach ($submenu[$parent] as $key => $item)
        {
            $rebuilt[$key] = $item;
            if (!$placed && isset($item[2]) && $item[2] === ProductImportController::SLUG)
            {
                $rebuilt[$self_key] = $self_item;
                $placed = true;
            }
        }

        if (!$placed)
        {
            $rebuilt[$self_key] = $self_item;
        }

        $submenu[$parent] = $rebuilt;
    }

    /** Small inline-styled "New" pill for the menu label (works site-wide). */
    private static function menuBadge(): string
    {
        return ' <span class="cegg-menu-badge" style="display:inline-block;background:#d63638;color:#fff;'
            . 'font-size:9px;font-weight:600;line-height:1;padding:2px 5px;border-radius:8px;'
            . 'vertical-align:middle;margin-inline-start:4px;letter-spacing:.02em;">'
            . esc_html__('New', 'content-egg') . '</span>';
    }

    public function enqueueAssets($hook): void
    {
        if ($hook !== $this->hook)
        {
            return;
        }

        \wp_enqueue_style(
            'cegg-agent-access',
            \ContentEgg\PLUGIN_RES . '/css/agent-access.css',
            array(),
            Plugin::version()
        );

        \wp_enqueue_script(
            'cegg-agent-access',
            \ContentEgg\PLUGIN_RES . '/js/agent-access.js',
            array(),
            Plugin::version(),
            true
        );

        \wp_localize_script('cegg-agent-access', 'ceggAgentAccess', array(
            'restUrl' => \rest_url('cegg/v1/agent-access'),
            'nonce' => \wp_create_nonce('wp_rest'),
            'i18n' => array(
                'saved' => __('Saved', 'content-egg'),
                'saveError' => __('Could not save — please try again.', 'content-egg'),
            ),
        ));
    }

    public function renderPage(): void
    {
        // Derive the ability list from our own catalog/registrar, NOT
        // wp_get_abilities(): this screen renders before rest_api_init, and
        // touching the core abilities registry here initializes it too early —
        // before other plugins (e.g. the WordPress MCP Adapter) register their
        // abilities on wp_abilities_api_init, which then log "ability not found".
        $mcp_present = class_exists('\WP\MCP\Core\McpAdapter');

        PluginAdmin::render('agent_access', array(
            'supported' => AbilitiesRegistrar::isSupported(),
            'enabled' => AbilitiesRegistrar::isEnabled(),
            'ability_groups' => AgentAbilityCatalog::groups(),
            'ability_count' => AgentAbilityCatalog::availableCount(),
            'is_paid' => Plugin::isPaidBuild(),
            'mcp_present' => $mcp_present,
            'mcp_active' => $mcp_present && self::isMcpAdapterPluginActive(),
            'mcp_endpoint' => \rest_url('content-egg/mcp'),
            'openapi_url' => \rest_url(OpenApiRestController::REST_NAMESPACE . OpenApiRestController::ROUTE),
            'guide_url' => \rest_url(AgentGuideRestController::REST_NAMESPACE . AgentGuideRestController::ROUTE),
            'profile_url' => \admin_url('profile.php#application-passwords-section'),
            'docs_url' => self::DOCS_URL,
            'log_rows' => AbilityLog::recent(30),
        ));
    }

    /**
     * Whether the standalone MCP Adapter plugin is active. The adapter class
     * alone is not enough: other plugins (e.g. WooCommerce) bundle it as a
     * library without booting an MCP server.
     */
    private static function isMcpAdapterPluginActive(): bool
    {
        if (!function_exists('is_plugin_active'))
        {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return \is_plugin_active('mcp-adapter/mcp-adapter.php');
    }
}
