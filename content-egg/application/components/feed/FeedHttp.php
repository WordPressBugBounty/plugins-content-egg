<?php

namespace ContentEgg\application\components\feed;

defined('\ABSPATH') || exit;

/**
 * FeedHttp class file
 *
 * Header-only HTTP probes for feed URLs. WP_Http rewrites HEAD to GET when
 * following a 302 (Requests does the same on a 303), and that GET is buffered
 * in memory: a feed behind a redirect would be pulled whole into RAM by a
 * check that only needs headers. Redirects are therefore followed here with
 * the method kept as HEAD, and the response size is capped for servers that
 * send a body to HEAD anyway.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class FeedHttp
{
    const MAX_REDIRECTS = 3;
    const MAX_BODY_BYTES = 1024;

    /**
     * @return array|\WP_Error The final (non-redirect) response, as wp_remote_head().
     */
    public static function head(string $url, array $args = array())
    {
        $args = array_merge(array('timeout' => 15), $args, array(
            'redirection' => 0,
            'limit_response_size' => self::MAX_BODY_BYTES,
        ));

        for ($i = 0; $i <= self::MAX_REDIRECTS; $i++)
        {
            $response = \wp_remote_head($url, $args);
            if (\is_wp_error($response))
            {
                return $response;
            }

            $code = (int) \wp_remote_retrieve_response_code($response);
            $location = \wp_remote_retrieve_header($response, 'location');
            if (is_array($location))
            {
                $location = end($location);
            }

            if (!in_array($code, array(301, 302, 303, 307, 308), true) || !$location)
            {
                return $response;
            }

            $url = \WP_Http::make_absolute_url((string) $location, $url);
        }

        return new \WP_Error('http_request_failed', 'Too many redirects.');
    }
}
