<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

/**
 * OpenApiGenerator class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Generates the OpenAPI 3.1 document from the live ability registry (design
 * spec §8): derived from the same schemas, so it cannot drift. buildSpec()
 * is pure for testability; collect() touches the WordPress registry.
 */
final class OpenApiGenerator
{
    const SPEC_VERSION = '3.1.0';

    /**
     * Client profiles that get the all-POST document against the Content Egg
     * proxy instead of the canonical one. 'chatgpt' is the old Custom GPT link,
     * kept as an alias so it still returns a usable document.
     */
    const POST_PROFILES = array('post', 'chatgpt');

    public static function isPostProfile(string $profile): bool
    {
        return in_array($profile, self::POST_PROFILES, true);
    }

    /**
     * Live registry -> plain descriptors for buildSpec().
     */
    public static function collect(): array
    {
        $out = array();

        if (!function_exists('wp_get_abilities'))
        {
            return $out;
        }

        foreach (\wp_get_abilities() as $ability)
        {
            if (strpos($ability->get_name(), AbilitiesRegistrar::CATEGORY . '/') !== 0)
            {
                continue;
            }

            $annotations = (array) $ability->get_meta_item('annotations', array());

            $out[] = array(
                'name' => $ability->get_name(),
                'label' => $ability->get_label(),
                'description' => $ability->get_description(),
                'input_schema' => (array) $ability->get_input_schema(),
                'output_schema' => (array) $ability->get_output_schema(),
                'readonly' => !empty($annotations['readonly']),
                'destructive' => !empty($annotations['destructive']),
            );
        }

        return $out;
    }

    /**
     * Pure: descriptors -> OpenAPI 3.1 document (array; serialize with
     * wp_json_encode at the transport layer).
     */
    public static function buildSpec(array $abilities, string $server_url, string $version, bool $post_all = false): array
    {
        $paths = array();

        foreach ($abilities as $a)
        {
            $description = (string) $a['description'];
            if (!empty($a['destructive']))
            {
                $description .= ' WARNING: destructive operation — confirm with the user before calling.';
            }

            $op = array(
                'operationId' => str_replace(array('/', '-'), '_', (string) $a['name']),
                'summary' => (string) $a['label'],
                'description' => $description,
                'security' => array(array('basicAuth' => array())),
                'responses' => self::responses($a),
            );

            // $post_all forces every operation to POST with a JSON body (the post
            // profile, which targets the Content Egg POST proxy): many HTTP tools
            // can't serialize deepObject query params and edge/WAF layers block
            // parameterized GETs, so readonly abilities go through POST too.
            if (!empty($a['readonly']) && !$post_all)
            {
                $op['parameters'] = array(array(
                    'name' => 'input',
                    'in' => 'query',
                    'required' => false,
                    'style' => 'deepObject',
                    'explode' => true,
                    'schema' => self::objectBranch((array) $a['input_schema']),
                ));
                $method = 'get';
            }
            else
            {
                // $post_all targets the Content Egg proxy, which takes arguments
                // at the top level; the canonical wp-abilities route this spec
                // otherwise documents takes them under "input", and that is core's
                // contract, not ours to flatten. Publishing the flat shape here
                // means a client has no envelope to omit — the failure mode that
                // made whole-article writes unreliable.
                $body_schema = self::objectBranch((array) $a['input_schema']);
                if (!$post_all)
                {
                    $body_schema = array(
                        'type' => 'object',
                        'properties' => array('input' => $body_schema),
                        'required' => array('input'),
                    );
                }

                $op['requestBody'] = array(
                    'required' => true,
                    'content' => array('application/json' => array('schema' => $body_schema)),
                );
                $method = 'post';
            }

            $paths['/abilities/' . $a['name'] . '/run'][$method] = $op;
        }

        return array(
            'openapi' => self::SPEC_VERSION,
            'info' => array(
                'title' => 'Content Egg Abilities API',
                'version' => $version,
                'description' => 'Agent-facing API for the Content Egg WordPress plugin, built on the '
                    . 'WordPress Abilities API. Authenticate with a WordPress application password '
                    . '(HTTP Basic).'
                    . ($post_all
                        ? ' Each operation is a POST whose JSON body holds the arguments at the top level, e.g. {"post_id": 12, "mode": "append"}.'
                        : ' Discover all abilities at GET {server}/abilities.')
                    . ' Agent guide (workflows, block-choice rules): GET {server-site}/wp-json/content-egg/v1/agent-guide'
                    . ' returns the core guide and its topic index; add ?topic=<name> for one topic, ?topic=all for everything.',
            ),
            'servers' => array(array('url' => rtrim($server_url, '/'))),
            'paths' => $paths,
            'components' => array(
                'securitySchemes' => array(
                    'basicAuth' => array(
                        'type' => 'http',
                        'scheme' => 'basic',
                        'description' => 'WordPress username + application password.',
                    ),
                ),
                'schemas' => array(
                    'error' => array(
                        'type' => 'object',
                        'properties' => array(
                            'code' => array('type' => 'string'),
                            'message' => array('type' => 'string'),
                            'data' => array('type' => 'object'),
                        ),
                    ),
                ),
            ),
        );
    }

    private static function objectBranch(array $schema): array
    {
        $schema['type'] = 'object';
        // The root "default" is a server-side device: it lets a call carrying no
        // input at all validate, because WP_Ability::execute() substitutes it for
        // null before validating. On the wire it is only harmful — PHP serializes
        // array() as [], so it would document an array default on an object
        // schema, which is exactly the kind of type-inconsistency a strict
        // OpenAPI/JSON-Schema consumer rejects. Callers omit the parameter
        // instead, so drop it.
        unset($schema['default']);
        // Strict OpenAPI validators (ChatGPT's among them) reject an object schema with no
        // "properties" key — no-input abilities have none, so emit an empty object
        // (stdClass so it serializes as {} rather than []).
        if (empty($schema['properties']))
        {
            $schema['properties'] = new \stdClass();
        }
        return $schema;
    }

    private static function responses(array $a): array
    {
        $r = array(
            '200' => array(
                'description' => 'Success',
                'content' => array('application/json' => array('schema' => (array) $a['output_schema'])),
            ),
            '400' => self::errorResponse('Validation failed (cegg_validation_failed)'),
            '401' => self::errorResponse('Not authenticated'),
            '403' => self::errorResponse('Not permitted'),
        );

        // Revision-conflict 409 only for writes that actually accept a revision
        // (RevisionGuard-backed post-product edits) — not every write. Detecting
        // it from the input schema keeps the doc from drifting.
        if (empty($a['readonly']) && !empty($a['input_schema']['properties']['revision']))
        {
            $r['409'] = self::errorResponse('Revision conflict (cegg_conflict; data.revision carries the current revision)');
        }
        if (strpos((string) $a['name'], 'content-egg/search-') === 0)
        {
            // All search abilities are rate-limited and can surface an upstream
            // module/API failure (e.g. missing or invalid API key).
            $r['429'] = self::errorResponse('Rate limited (cegg_rate_limited; data.retry_after in seconds)');
            $r['502'] = self::errorResponse('Module search failed (cegg_search_failed; the module API returned an error — often missing/invalid credentials or a network rate limit. The message carries the reason)');
        }
        if ($a['name'] === 'content-egg/search-products')
        {
            // Only product modules can be feed-backed.
            $r['409'] = self::errorResponse('Feed import still running (cegg_feed_pending; poll content-egg/get-feed-status)');
        }

        return $r;
    }

    private static function errorResponse(string $description): array
    {
        return array(
            'description' => $description,
            'content' => array('application/json' => array('schema' => array('$ref' => '#/components/schemas/error'))),
        );
    }
}
