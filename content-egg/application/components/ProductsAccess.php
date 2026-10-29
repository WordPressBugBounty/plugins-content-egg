<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

/**
 * Whose products a shortcode or block may show when it names a post by post_id.
 *
 * A public post's products are public. Any other post (a draft kept to hold
 * products shown elsewhere, a private or password-protected post) is shown only
 * if the author of the page that embeds it may read it: the embed acts on that
 * author's rights, not the visitor's. Without such a page (REST, agent preview)
 * the current user decides.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ProductsAccess
{
    private static $signed = 0;

    public static function canShow($post_id): bool
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0)
            return false;

        if (self::$signed)
            return true;

        if (\is_post_publicly_viewable($post_id) && !\post_password_required($post_id))
            return true;

        $host = \get_post();
        if ($host && (int) $host->ID === $post_id)
            return true;

        $user_id = $host ? (int) $host->post_author : \get_current_user_id();

        return $user_id > 0 && \user_can($user_id, 'read_post', $post_id);
    }

    /**
     * Signs an async placeholder payload. The page render that emits it has
     * already passed canShow(), which the async request cannot repeat: it
     * arrives without the page.
     */
    public static function sign(string $payload): string
    {
        return \hash_hmac('sha256', $payload, \wp_salt('auth'));
    }

    public static function verify(string $payload, string $sig): bool
    {
        return $sig !== '' && \hash_equals(self::sign($payload), $sig);
    }

    /**
     * Runs $render for a payload that verify() accepted, with canShow() open.
     */
    public static function runSigned(callable $render)
    {
        self::$signed++;
        try
        {
            return $render();
        }
        finally
        {
            self::$signed--;
        }
    }
}
