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

    /** ChatGPT Custom GPT Actions refuse an imported schema above this many operations. */
    const CHATGPT_MAX_OPERATIONS = 30;

    /**
     * Abilities dropped from the 'chatgpt' profile. ChatGPT Custom GPT Actions
     * cap an imported OpenAPI at 30 operations; the full ability set is larger, so
     * this trims a few admin / rarely-needed-from-a-chat-GPT operations to fit.
     * They stay fully available over MCP, plain REST, and the agent guide.
     */
    const CHATGPT_EXCLUDE = array(
        'content-egg/get-feed-status',
        'content-egg/search-all-products',
        'content-egg/refresh-post-products',
        'content-egg/deactivate-module',
        // ChatGPT gets the guide via its Instructions (paste, or a "read this URL"
        // line its browser fetches), so it doesn't need get-guide as an operation —
        // dropping it keeps the profile under ChatGPT's 30-operation cap. get-guide
        // stays in the full spec for MCP/Claude/curl, where it's a first-class tool.
        'content-egg/get-guide',
    );

    /**
     * Abilities that keep ChatGPT's per-call confirmation (x-openai-isConsequential
     * = true): genuinely destructive (data loss) or public-facing. Everything else
     * gets a one-time "Always allow". Deliberately narrower than the `destructive`
     * annotation so reversible edits (insert-blocks, set-featured-image) don't
     * prompt on every call.
     */
    const CHATGPT_CONFIRM = array(
        'content-egg/remove-products',
        'content-egg/deactivate-module',
        'content-egg/set-post-status',
    );

    /**
     * Which abilities the ChatGPT profile actually drops for a build exposing
     * $ability_count of them — empty when the set already fits.
     *
     * The exclusion list exists to satisfy ChatGPT's limit, not because those
     * abilities are unwanted, so a build under the cap (free registers 17) keeps
     * everything. Single source of truth: applyProfile() trims by this, and the
     * Agent Access screen badges by it, so the spec and the UI can never disagree.
     */
    public static function chatGptExcludes(int $ability_count): array
    {
        return $ability_count > self::CHATGPT_MAX_OPERATIONS ? self::CHATGPT_EXCLUDE : array();
    }

    /**
     * Keep only the abilities allowed under a client profile. Unknown/empty
     * profile returns the list unchanged (full spec). Pure.
     */
    public static function applyProfile(array $abilities, string $profile): array
    {
        if ($profile !== 'chatgpt')
        {
            return $abilities;
        }

        $exclude = array_flip(self::chatGptExcludes(count($abilities)));
        if (!$exclude)
        {
            return $abilities;
        }

        return array_values(array_filter($abilities, static function ($a) use ($exclude)
        {
            return !isset($exclude[(string) ($a['name'] ?? '')]);
        }));
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
    public static function buildSpec(array $abilities, string $server_url, string $version, bool $compact = false, bool $post_all = false): array
    {
        $paths = array();

        foreach ($abilities as $a)
        {
            $description = (string) $a['description'];
            $warning = !empty($a['destructive'])
                ? ' WARNING: destructive operation — confirm with the user before calling.'
                : '';

            // ChatGPT Custom GPT Actions cap each operation description at 300 chars.
            // The full text is served to MCP/REST/Claude; the compact (chatgpt) spec
            // trims it — the rich workflow context lives in the agent guide the user
            // pastes into the GPT's Instructions.
            //
            // The warning is appended AFTER trimming, against a budget reserved for
            // it. Appending first meant the cap ate it off the end: the one line
            // that tells the agent an operation destroys content was the line most
            // reliably dropped from the profile that needed it most.
            if ($compact)
            {
                $description = self::truncate($description, 300 - mb_strlen($warning));
            }

            $description .= $warning;

            $op = array(
                'operationId' => str_replace(array('/', '-'), '_', (string) $a['name']),
                'summary' => (string) $a['label'],
                'description' => $description,
                'security' => array(array('basicAuth' => array())),
                // ChatGPT confirmation gate: POST operations are "consequential"
                // (confirm every call) by default. Mark only the narrow confirm
                // list consequential, so reads and ordinary edits (incl. insert-
                // blocks, set-featured-image) get a one-time "Always allow".
                'x-openai-isConsequential' => in_array((string) $a['name'], self::CHATGPT_CONFIRM, true),
                'responses' => self::responses($a),
            );

            // $post_all forces every operation to POST with a JSON body (used by
            // the ChatGPT profile, which targets the Content Egg POST proxy):
            // ChatGPT can't serialize deepObject query params and edge/WAF layers
            // block parameterized GETs, so readonly abilities go through POST too.
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
                // means a Custom GPT has no envelope to omit — the failure mode
                // that made whole-article writes unreliable.
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
                    . ' Full agent guide (workflows, block-choice rules): GET {server-site}/wp-json/content-egg/v1/agent-guide.',
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

    /**
     * Shorten to at most $max characters, preferring a clean boundary: end on the
     * last full sentence when one sits past ~60% of the budget, else cut on a word
     * boundary and append an ellipsis. Result is always <= $max.
     */
    private static function truncate(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max)
        {
            return $text;
        }

        $cut = mb_substr($text, 0, $max - 1);

        $period = self::lastSentenceEnd($cut, (int) ($max * 0.6));
        if ($period !== false)
        {
            return mb_substr($cut, 0, $period + 1); // ends on a sentence, no ellipsis
        }

        $space = mb_strrpos($cut, ' ');
        if ($space !== false)
        {
            $cut = mb_substr($cut, 0, $space);
        }
        return rtrim($cut) . '…';
    }

    /** Periods that end an abbreviation, not a sentence. */
    const NOT_SENTENCE_END = array('e.g.', 'i.e.', 'etc.', 'vs.');

    /**
     * Offset of the rightmost sentence-ending ". " sitting past $min, or false.
     *
     * Plain strrpos('. ') treats "e.g. " as a sentence end, so a description
     * truncated there came back reading as a COMPLETE sentence that stops right
     * where its example was about to start — and with no ellipsis, nothing told
     * the reader anything had been dropped. Skip those and keep looking left.
     *
     * @return int|false
     */
    private static function lastSentenceEnd(string $text, int $min)
    {
        $offset = mb_strlen($text);

        while ($offset > 0)
        {
            $pos = mb_strrpos(mb_substr($text, 0, $offset), '. ');
            if ($pos === false || $pos <= $min)
            {
                return false;
            }

            $start = max(0, $pos - 3);
            $window = mb_strtolower(mb_substr($text, $start, $pos - $start + 1));

            $is_abbr = false;
            foreach (self::NOT_SENTENCE_END as $abbr)
            {
                if (mb_substr($window, -mb_strlen($abbr)) === $abbr)
                {
                    $is_abbr = true;
                    break;
                }
            }

            if (!$is_abbr)
            {
                return $pos;
            }

            $offset = $pos; // keep searching to the left of this abbreviation
        }

        return false;
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
        // ChatGPT (and strict OpenAPI validators) reject an object schema with no
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
