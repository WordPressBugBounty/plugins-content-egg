<?php

namespace ContentEgg\application;

defined('\ABSPATH') || exit;

use ContentEgg\application\abilities\AbilitiesRegistrar;

/**
 * AgentAccessRestController class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Tiny REST endpoint backing the Agent Access screen's on/off switch, so the
 * toggle saves instantly without a form submit. Admin-only.
 */
class AgentAccessRestController extends \WP_REST_Controller
{
    protected $namespace = 'cegg/v1';
    protected $rest_base = 'agent-access';

    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new self;

        return self::$instance;
    }

    private function __construct()
    {
    }

    public function init()
    {
        \add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes()
    {
        \register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            array(
                array(
                    'methods' => \WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'update_enabled'),
                    'permission_callback' => array($this, 'permission_check'),
                    'args' => array(
                        'enabled' => array(
                            'description' => __('Whether Agent Access is enabled.', 'content-egg'),
                            'type' => 'boolean',
                            'required' => true,
                        ),
                    ),
                ),
            )
        );
    }

    public function permission_check()
    {
        return \current_user_can('manage_options');
    }

    public function update_enabled($request)
    {
        $enabled = \rest_sanitize_boolean($request['enabled']);
        \update_option(AbilitiesRegistrar::OPT_ENABLED, $enabled ? '1' : '0');

        $count = 0;
        if ($enabled && AbilitiesRegistrar::isSupported())
        {
            $count = count(AbilitiesRegistrar::enabledAbilityNames());
        }

        return \rest_ensure_response(array(
            'enabled' => $enabled,
            'ability_count' => $count,
        ));
    }
}
