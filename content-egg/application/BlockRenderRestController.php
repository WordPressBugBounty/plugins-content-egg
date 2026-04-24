<?php

namespace ContentEgg\application;

use function ContentEgg\prnx;

defined('\ABSPATH') || exit;

/**
 * BlockRenderRestController class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

class BlockRenderRestController
{
    const REST_NAMESPACE = 'content-egg/v1';
    const ROUTE = '/render-blocks';

    const MAX_BLOCKS_PER_REQUEST = 20;
    const RATE_LIMIT_PER_MINUTE = 60;

    private static $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null)
        {
            self::$instance = new self;
        }
        return self::$instance;
    }

    private function __construct()
    {
    }

    public static function init()
    {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    public static function register_routes()
    {
        register_rest_route(self::REST_NAMESPACE, self::ROUTE, array(
            'methods'             => \WP_REST_Server::CREATABLE, // POST
            'callback'            => array(__CLASS__, 'handle_render'),
            'permission_callback' => array(__CLASS__, 'permission_check'),
            'args'                => array(
                'blocks' => array(
                    'required' => true,
                    'type'     => 'array',
                ),
            ),
        ));
    }

    public static function permission_check(\WP_REST_Request $request)
    {
        // If nonce is provided, require it to be valid.
        $nonce = $request->get_header('X-WP-Nonce');
        if ($nonce)
        {
            if (!wp_verify_nonce($nonce, 'wp_rest'))
            {
                return new WP_Error(
                    'cegg_invalid_nonce',
                    __('Invalid security token.', 'content-egg'),
                    array('status' => 403)
                );
            }
        }

        // Otherwise allow public rendering (content is public anyway),
        // with rate limiting in handle_render().
        return true;
    }

    protected static function get_client_ip()
    {
        // Keep simple; we can improve behind proxies/CDNs later
        return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }

    protected static function check_rate_limit()
    {
        $limit = (int) apply_filters('cegg_render_blocks_rate_limit_per_minute', self::RATE_LIMIT_PER_MINUTE);
        if ($limit <= 0)
        {
            return true; // disabled
        }

        $ip  = self::get_client_ip();
        $key = 'cegg_rb_' . md5($ip);
        $n   = (int) get_transient($key);

        if ($n >= $limit)
        {
            return new WP_Error(
                'cegg_rate_limited',
                __('Too many requests. Please try again shortly.', 'content-egg'),
                array('status' => 429)
            );
        }

        set_transient($key, $n + 1, MINUTE_IN_SECONDS);
        return true;
    }

    protected static function normalize_atts_for_shortcode($atts)
    {
        $out = array();

        if (!is_array($atts))
        {
            return $out;
        }

        foreach ($atts as $key => $value)
        {
            $key = sanitize_key($key);

            if (is_array($value))
            {
                $value = array_map('sanitize_text_field', $value);
                $out[$key] = implode(',', $value);
            }
            else
            {
                $out[$key] = sanitize_text_field((string) $value);
            }
        }

        return $out;
    }

    public static function handle_render(\WP_REST_Request $request)
    {
        $rate_ok = self::check_rate_limit();
        if (is_wp_error($rate_ok))
        {
            return $rate_ok;
        }

        $params = $request->get_json_params();
        $blocks = isset($params['blocks']) ? $params['blocks'] : null;

        if (!is_array($blocks) || empty($blocks))
        {
            return new \WP_Error(
                'cegg_bad_request',
                __('Invalid request payload.', 'content-egg'),
                array('status' => 400)
            );
        }

        $max_blocks = (int) apply_filters('cegg_render_blocks_max_blocks_per_request', self::MAX_BLOCKS_PER_REQUEST);
        if ($max_blocks > 0 && count($blocks) > $max_blocks)
        {
            return new WP_Error(
                'cegg_too_many_blocks',
                sprintf(__('Too many blocks in one request (max %d).', 'content-egg'), $max_blocks),
                array('status' => 400)
            );
        }

        $results = array();

        foreach ($blocks as $item)
        {
            // Expected shape per item:
            // { id: "cegg-block-123", post_id: 123, atts: {...}, content: "..." }
            $id      = isset($item['id']) ? sanitize_text_field((string) $item['id']) : '';
            $post_id = isset($item['post_id']) ? absint($item['post_id']) : 0;
            $atts    = isset($item['atts']) ? $item['atts'] : array();
            $content = isset($item['content']) ? (string) $item['content'] : '';
            $type = isset($item['type']) ? sanitize_key($item['type']) : '';

            if (!$id)
            {
                // Skip items without IDs; caller can't map the response anyway.
                continue;
            }

            if (!$post_id)
            {
                $results[$id] = array(
                    'html'   => '',
                    'error'  => __('Missing post_id.', 'content-egg'),
                );
                continue;
            }

            try
            {
                $atts_norm = self::normalize_atts_for_shortcode($atts);

                // Force the correct post and avoid async recursion
                $atts_norm['post_id'] = $post_id;
                $atts_norm['async']   = 0;

                // Render
                if ($type === 'module' || !empty($atts_norm['module']))
                {
                    $html = EggShortcode::getInstance()->viewData($atts_norm, $content);
                }
                else
                {
                    $html = BlockShortcode::getInstance()->viewData($atts_norm, $content);
                }

                $results[$id] = array(
                    'html' => (string) $html,
                );
            }
            catch (\Throwable $e)
            {
                $results[$id] = array(
                    'html'  => '',
                    'error' => __('Render error.', 'content-egg'),
                );
            }
        }

        return rest_ensure_response(array(
            'success' => true,
            'data'    => $results,
        ));
    }
}
