<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\Plugin;

/**
 * OpenApiRestController class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Serves the generated OpenAPI document publicly (design spec §8: the spec
 * documents only discoverable surface; execution still requires auth).
 */
final class OpenApiRestController
{
    const REST_NAMESPACE = 'content-egg/v1';
    const ROUTE = '/openapi';

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

    public static function handle($request = null)
    {
        // ?profile=post serves every operation as a POST against the Content Egg
        // proxy (content-egg/v1/abilities/{name}/run, arguments at the top level of
        // the JSON body), for HTTP tools that can't send deepObject query params or
        // whose parameterized GETs an edge/WAF blocks. No/other profile serves the
        // canonical spec against the core Abilities API (wp-abilities/v1), readonly = GET.
        $profile = ($request && method_exists($request, 'get_param'))
            ? (string) $request->get_param('profile')
            : '';

        $post_all = OpenApiGenerator::isPostProfile($profile);
        $server_url = $post_all ? \rest_url('content-egg/v1') : \rest_url('wp-abilities/v1');

        $spec = OpenApiGenerator::buildSpec(
            OpenApiGenerator::collect(),
            $server_url,
            Plugin::version() . '+abilities.' . AbilitiesRegistrar::VERSION,
            $post_all
        );

        return \rest_ensure_response($spec);
    }
}
