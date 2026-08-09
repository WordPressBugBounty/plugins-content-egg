<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

/**
 * AbilityProxyRestController class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * POST transport for abilities.
 *
 * The WordPress Abilities API core exposes readonly abilities as GET /run with a
 * deepObject `input` query param. Some agent clients can't use that: ChatGPT's
 * Custom GPT Actions doesn't serialize deepObject (it sends ?input={json}, which
 * the core rejects), and edge/WAF layers can block parameterized GETs before they
 * reach WordPress. This proxy accepts the identical call as a POST with a JSON
 * body — no query string at all — and dispatches to the same registered ability,
 * reusing its permission check, audit logging, and error mapping.
 *
 * The ChatGPT OpenAPI profile targets this endpoint, and so does any assistant
 * driving the API over plain HTTP — notably Claude calling curl from its own
 * sandbox, which needs no MCP adapter at all. MCP clients keep using the core
 * API through the bridge. Unlike the core router, this route accepts POST for
 * read-only abilities too, so a client has one uniform call shape for
 * everything; the agent guide documents both transports.
 */
final class AbilityProxyRestController
{
    const REST_NAMESPACE = 'content-egg/v1';
    // Ability name is the full `content-egg/<slug>` (contains a slash).
    const ROUTE = '/abilities/(?P<name>[a-zA-Z0-9._/-]+)/run';

    public static function initAction(): void
    {
        \add_action('rest_api_init', array(self::class, 'registerRoutes'));
    }

    public static function registerRoutes(): void
    {
        \register_rest_route(self::REST_NAMESPACE, self::ROUTE, array(
            'methods' => \WP_REST_Server::CREATABLE, // POST
            'callback' => array(self::class, 'run'),
            // Per-ability permission is enforced in run() (it depends on the
            // resolved ability and its input), mirroring the core /run endpoint.
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public static function run($request)
    {
        $name = (string) $request['name'];

        $ability = AbilitiesRegistrar::findEnabledAbility($name);
        if ($ability === null)
        {
            // Distinguish "this build does not include it" from "no such ability":
            // both resolve to null, but only one is actionable for the user.
            $gated = BuildCapabilities::gatedAbility($name, \ContentEgg\application\Plugin::isPaidBuild());
            if ($gated !== null)
            {
                return new \WP_Error(
                    'cegg_requires_pro',
                    $gated->label() . ' requires Content Egg Pro.',
                    array(
                        'status' => 402,
                        'ability' => $name,
                        'upgrade_url' => BuildCapabilities::upgradeUrl(),
                    )
                );
            }

            return new \WP_Error(
                'cegg_ability_not_found',
                'Unknown or unavailable ability: ' . $name,
                array('status' => 404)
            );
        }

        $body = $request->get_json_params();
        $input = (is_array($body) && isset($body['input']) && is_array($body['input']))
            ? $body['input']
            : array();

        if (!$ability->checkPermission($input))
        {
            // 401 when nobody is authenticated, 403 when they are but lack the
            // capability. Answering 403 to an anonymous caller told an
            // app-password client "authenticated but forbidden", leaving it no
            // signal to retry with credentials. Matches the canonical route.
            $status = \rest_authorization_required_code();

            return new \WP_Error(
                'cegg_forbidden',
                $status === 401
                    ? 'Authentication required. Send a WordPress application password over HTTP Basic.'
                    : 'You are not permitted to run this ability.',
                array('status' => $status)
            );
        }

        // Transport parity: the canonical wp-abilities route validates `input`
        // against the ability's declared input_schema before dispatch, and this
        // proxy did not -- so it accepted out-of-enum values, undeclared
        // properties and out-of-range numbers that the canonical route rejects,
        // hiding agent mistakes and spending affiliate API quota on calls that
        // should never have run.
        //
        // Runs AFTER the permission check, deliberately: authorization must not
        // depend on input validity, and an unauthenticated caller should keep
        // getting 403 rather than a 400 that confirms the schema.
        // Validate empty input too. Guarding on `$input !== array()` meant a bare
        // {} skipped validation entirely, so required-property errors never fired
        // and write abilities answered "Post 0 not found." where the canonical
        // route said "post_id is a required property of input." Abilities that
        // legitimately take no input declare type ['object','null'] with no
        // required properties, so {} still validates for them.
        $schema = $ability->inputSchema();
        if (is_array($schema))
        {
            $valid = \rest_validate_value_from_schema($input, $schema, 'input');
            if (\is_wp_error($valid))
            {
                return new \WP_Error(
                    'cegg_validation_failed',
                    $valid->get_error_message(),
                    array('status' => 400)
                );
            }

            $sanitized = \rest_sanitize_value_from_schema($input, $schema, 'input');
            if (!\is_wp_error($sanitized) && is_array($sanitized))
            {
                $input = $sanitized;
            }
        }

        return \rest_ensure_response(AbilitiesRegistrar::executeLogged($ability, $input));
    }
}
