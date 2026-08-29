<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

/**
 * CacheGuard class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Forces the agent REST surface to be uncacheable.
 *
 * The agent endpoints authenticate with a WordPress application password (HTTP
 * Basic) — there is no login cookie. Page caches treat cookieless requests as
 * anonymous and can store the response, then serve one requester's
 * authenticated data to another (or to an unauthenticated request). The app's
 * own `Cache-Control: no-store, private` is not honored by every cache layer
 * (LiteSpeed's REST-cache mode in particular), so this guard emits the
 * belt-and-braces no-cache signals that the common caches actually obey, on
 * every agent request, regardless of the site's cache plugin/config.
 *
 * Booted with the agent layer (only when Agent Access is enabled, which is
 * exactly when these endpoints exist). Cookie/nonce admin REST is out of scope
 * — caches already skip logged-in/cookie requests, so it is not exposed.
 */
final class CacheGuard
{
    public static function initAction(): void
    {
        // rest_pre_dispatch fires during the request (before caches finalize on
        // shutdown): mark the request uncacheable. rest_post_dispatch reinforces
        // the header on the WP_REST_Response so it rides on the response object too.
        \add_filter('rest_pre_dispatch', array(self::class, 'preDispatch'), 10, 3);
        \add_filter('rest_post_dispatch', array(self::class, 'postDispatch'), 10, 3);
        // rest_pre_serve_request lets us take over the transport for agent routes
        // and emit the body with a fixed Content-Length (+ our own gzip), avoiding
        // the chunked+gzip framing that breaks aiohttp/ChatGPT (see serveEncoded).
        \add_filter('rest_pre_serve_request', array(self::class, 'serveEncoded'), 10, 4);
    }

    /**
     * True when $route is one of the agent REST surfaces:
     *   - /wp-abilities/...  (WP Abilities API discovery + .../run execution)
     *   - /content-egg/...   (OpenAPI, agent guide, agent-access toggle, MCP)
     *
     * Pure — no WordPress calls — so the standalone test harness can exercise it.
     */
    public static function isAgentRoute(string $route): bool
    {
        return strpos($route, '/wp-abilities/') === 0
            || strpos($route, '/content-egg/') === 0;
    }

    /**
     * Never short-circuits: returns $result unchanged. Its only job is the
     * side effect of emitting no-cache signals for agent routes.
     *
     * @param mixed            $result  Short-circuit value (null to continue).
     * @param mixed            $server  WP_REST_Server (unused).
     * @param \WP_REST_Request $request Current request.
     * @return mixed The unchanged $result.
     */
    public static function preDispatch($result, $server = null, $request = null)
    {
        if (!$request || !method_exists($request, 'get_route') || !self::isAgentRoute((string) $request->get_route()))
        {
            return $result;
        }

        // Tolerate GPT-Actions' serialization of the ability `input`: ChatGPT sends
        // the object query param as a JSON string (?input={...}) instead of the
        // OpenAPI deepObject form (?input[k]=v) the abilities core requires, so the
        // core would reject it with a 400 before dispatch. Decode it back to an
        // object here (rest_pre_dispatch runs before param validation).
        self::normalizeJsonInput($request);

        // Different cache plugins honor different signals — send all of them.

        // 1. WordPress core.
        if (function_exists('\nocache_headers'))
        {
            \nocache_headers();
        }

        // 2. LiteSpeed Cache.
        \do_action('litespeed_control_set_nocache', 'Content Egg agent API is authenticated and dynamic');
        if (!headers_sent())
        {
            header('X-LiteSpeed-Cache-Control: no-cache');
        }

        // 3. WP Rocket / W3TC / WP Super Cache / others.
        if (!defined('DONOTCACHEPAGE'))
        {
            define('DONOTCACHEPAGE', true);
        }

        // 4. Explicit header (belt-and-braces; also set on the response object below).
        //    no-transform additionally asks intermediaries not to recompress the body.
        if (!headers_sent())
        {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private, no-transform');
        }

        self::disableCompression();

        return $result;
    }

    /**
     * If the request's `input` param arrived as a JSON string, decode it to an
     * array so the abilities core validates it against the ability schema. No-op
     * for the deepObject form (already an array), empty input, or non-JSON.
     */
    private static function normalizeJsonInput($request): void
    {
        if (!is_object($request) || !method_exists($request, 'get_param') || !method_exists($request, 'set_param'))
        {
            return;
        }

        $input = $request->get_param('input');
        $decoded = self::decodeJsonInput($input);
        if ($decoded !== $input)
        {
            $request->set_param('input', $decoded);
        }
    }

    /**
     * Decode a JSON-object/array string into a PHP array; return the value
     * unchanged for anything else (already-array deepObject input, empty string,
     * scalars, or a string that isn't a JSON object/array). Pure — unit-testable.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function decodeJsonInput($value)
    {
        if (!is_string($value))
        {
            return $value;
        }

        $trimmed = ltrim($value);
        if ($trimmed === '' || ($trimmed[0] !== '{' && $trimmed[0] !== '['))
        {
            return $value;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $value;
    }

    /**
     * Decide whether a response is served with no body at all, mirroring the
     * check core does just before it echoes (class-wp-rest-server.php):
     *
     *   // The 204 response shouldn't have a body.
     *   if ( 204 === $code || null === $result ) { return null; }
     *
     * serveEncoded() short-circuits core at 'rest_pre_serve_request', which is
     * upstream of that check, so it has to make the same decision itself.
     *
     * The MCP transport returns WP_REST_Response(null, 202) to acknowledge a
     * JSON-RPC notification; without this, wp_json_encode(null) would put the
     * 4-byte string `null` in a body the Streamable HTTP spec says MUST be
     * empty. Only `null` data counts — an empty array/string, false and 0 are
     * all legitimate JSON bodies.
     *
     * Pure — no WordPress calls — so the standalone test harness can exercise it.
     *
     * @param mixed $data   Response data from response_to_data().
     * @param int   $status HTTP status of the response.
     * @return array|null Headers to emit for the empty body, or null when the
     *                    response has a body and should be served normally.
     */
    public static function bodylessHeaders($data, int $status): ?array
    {
        if ($data !== null && $status !== 204)
        {
            return null;
        }

        // RFC 9110 8.6: a server MUST NOT send Content-Length on a 204. Any
        // other bodyless status (202 notification acks) states the 0 explicitly
        // so nothing downstream falls back to chunked framing.
        return $status === 204 ? array() : array('Content-Length' => '0');
    }

    /**
     * Turn PHP's own output compression off for the agent surface, so it can't
     * add a second encoding layer on top of the body serveEncoded() emits.
     *
     * This does NOT stop a server-level compressor (LiteSpeed/mod_deflate) — those
     * ignore PHP ini/env and are handled instead by serveEncoded(), which declares
     * the encoding itself so the server passes the body through. Must run before
     * the first byte is sent (rest_pre_dispatch is). No-op once headers are out.
     */
    private static function disableCompression(): void
    {
        if (headers_sent())
        {
            return;
        }

        if (function_exists('\ini_set'))
        {
            @\ini_set('zlib.output_compression', 'Off');
        }
    }

    /**
     * Serve agent-route responses with an explicit Content-Length instead of
     * Transfer-Encoding: chunked.
     *
     * Observed on the LiteSpeed/Hostinger connector: get-status (small —
     * `Content-Length` + gzip) succeeds through ChatGPT's aiohttp-based Actions
     * client, while list-modules (large — `chunked` + gzip) fails with an opaque
     * ClientResponseError. The failing body is a valid, complete, single gzip
     * (byte-identical to the identity response), so the only structural
     * difference from the working case is the framing — chunked vs a fixed
     * length. Chunked is also more fragile across intermediaries than
     * Content-Length. We take over serving for agent routes and send the body
     * with a Content-Length, matching the known-good get-status shape.
     *
     * When the client accepts gzip (and PHP isn't itself compressing), we gzip
     * the body here and declare `Content-Encoding: gzip`: a server won't
     * re-compress a response that already declares an encoding, so the body
     * survives LiteSpeed/mod_deflate untouched and stays Content-Length, not
     * chunked. Only the plain JSON path is taken over; JSONP/HEAD fall through
     * to core.
     *
     * @param bool             $served  Whether the request was already served.
     * @param mixed            $result  WP_REST_Response for this request.
     * @param \WP_REST_Request $request Current request.
     * @param mixed            $server  WP_REST_Server.
     * @return bool True when we served it (short-circuits core), else $served.
     */
    public static function serveEncoded($served, $result, $request = null, $server = null)
    {
        if ($served || headers_sent())
        {
            return $served;
        }
        if (!$request || !method_exists($request, 'get_route') || !self::isAgentRoute((string) $request->get_route()))
        {
            return $served;
        }
        if (!is_object($result) || !is_object($server) || !method_exists($server, 'response_to_data'))
        {
            return $served;
        }
        if (strtoupper((string) $request->get_method()) === 'HEAD' || isset($_GET['_jsonp']))
        {
            // Let core handle HEAD (no body) and the JSONP wrapper path.
            return $served;
        }

        $data = $server->response_to_data($result, false);

        // Bodyless responses (MCP notification acks, 204s) must not be encoded:
        // taking over from core means taking over its no-body check too. Runs
        // before the gzip branch so an empty body never claims an encoding.
        $status = method_exists($result, 'get_status') ? (int) $result->get_status() : 200;
        $bodyless = self::bodylessHeaders($data, $status);
        if ($bodyless !== null)
        {
            foreach ($bodyless as $name => $value)
            {
                header($name . ': ' . $value);
            }

            return true;
        }

        $json = \wp_json_encode($data);
        if (!is_string($json))
        {
            // Encoding failed — let core run its json-error path.
            return $served;
        }

        // If PHP itself is still compressing (non-LiteSpeed hosts), let it own the
        // framing rather than fight it; just avoid our own second layer.
        $php_zlib = strtolower(trim((string) \ini_get('zlib.output_compression')));
        if (!in_array($php_zlib, array('', '0', 'off'), true))
        {
            echo $json;
            return true;
        }

        $body = $json;
        $accepts_gzip = stripos((string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''), 'gzip') !== false;
        if ($accepts_gzip && function_exists('\gzencode'))
        {
            $gz = \gzencode($json, 5);
            if (is_string($gz))
            {
                $body = $gz;
                header('Content-Encoding: gzip');
                header('Vary: Accept-Encoding');
            }
        }

        header('Content-Length: ' . strlen($body));
        echo $body;

        return true;
    }

    /**
     * Reinforce the no-cache headers on the response object so they survive to
     * the client (and to caches inspecting the WP_REST_Response headers).
     *
     * @param mixed            $response WP_REST_Response (or other).
     * @param mixed            $server   WP_REST_Server (unused).
     * @param \WP_REST_Request $request  Current request.
     * @return mixed The unchanged $response.
     */
    public static function postDispatch($response, $server = null, $request = null)
    {
        if (!$request || !method_exists($request, 'get_route') || !self::isAgentRoute((string) $request->get_route()))
        {
            return $response;
        }

        if (is_object($response) && method_exists($response, 'header'))
        {
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private, no-transform');
            $response->header('X-LiteSpeed-Cache-Control', 'no-cache');
        }

        return $response;
    }
}
