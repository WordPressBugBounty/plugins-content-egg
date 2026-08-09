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
        // ?profile=chatgpt trims the spec to fit ChatGPT's 30-operation cap on
        // Custom GPT Actions; no/other profile serves the full spec.
        $profile = ($request && method_exists($request, 'get_param'))
            ? (string) $request->get_param('profile')
            : '';

        $abilities = OpenApiGenerator::applyProfile(OpenApiGenerator::collect(), $profile);

        $is_chatgpt = ($profile === 'chatgpt');

        // ChatGPT can't call the core GET /run endpoint (no deepObject support,
        // and edge/WAF layers block parameterized GETs), so its profile targets
        // the Content Egg POST proxy (content-egg/v1/abilities/{name}/run) with
        // every operation as POST + JSON body. Other clients get the full spec
        // against the core Abilities API (wp-abilities/v1), readonly = GET.
        $server_url = $is_chatgpt ? \rest_url('content-egg/v1') : \rest_url('wp-abilities/v1');

        $spec = OpenApiGenerator::buildSpec(
            $abilities,
            $server_url,
            Plugin::version() . '+abilities.' . AbilitiesRegistrar::VERSION,
            $is_chatgpt,
            $is_chatgpt
        );

        return \rest_ensure_response($spec);
    }
}
