<?php

namespace ContentEgg\application\admin;

defined('\ABSPATH') || exit;

/**
 * UnseenRejectionNotice class file
 *
 * The classic metabox posts every module's whole offer list. An offer an AI
 * agent rejected after the form was loaded was never shown to the person, so
 * the save keeps the rejection and drops the offer (OfferDecisions::
 * applyManual(), pages with a product passport). This class tells the person:
 * the save stores a one-time count per user and post (short-lived), and the
 * next load of that edit screen prints it in the metabox and forgets it.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class UnseenRejectionNotice
{
    const PREFIX = 'cegg_unseen_rej_';

    // The notice waits this long for the next load of the edit screen.
    const TTL = 900;

    public static function key(int $user_id, int $post_id): string
    {
        return self::PREFIX . $user_id . '_' . $post_id;
    }

    /**
     * Add $count dropped offers to the current user's notice for the post.
     */
    public static function remember(int $post_id, int $count): void
    {
        $user_id = (int) \get_current_user_id();
        if ($user_id <= 0 || $post_id <= 0 || $count <= 0)
            return;

        $key = self::key($user_id, $post_id);
        $stored = (int) \get_transient($key);
        \set_transient($key, max(0, $stored) + $count, self::TTL);
    }

    /**
     * The count waiting for the current user and post, 0 for none; it is
     * forgotten once read.
     */
    public static function take(int $post_id): int
    {
        $user_id = (int) \get_current_user_id();
        if ($user_id <= 0 || $post_id <= 0)
            return 0;

        $key = self::key($user_id, $post_id);
        $count = (int) \get_transient($key);
        if ($count > 0)
            \delete_transient($key);

        return max(0, $count);
    }

    public static function message(int $count): string
    {
        return sprintf(
            /* translators: %d: number of offers. */
            \_n(
                '%d offer was not saved: an AI agent rejected it after this page was opened. Add it again to keep it.',
                '%d offers were not saved: an AI agent rejected them after this page was opened. Add them again to keep them.',
                $count,
                'content-egg'
            ),
            $count
        );
    }

    /**
     * Print the waiting notice for the post, if any (once).
     */
    public static function render(int $post_id): void
    {
        $count = self::take($post_id);
        if ($count <= 0)
            return;

        echo '<div class="notice notice-warning inline cegg-unseen-rejection-notice"><p>' . \esc_html(self::message($count)) . '</p></div>';
    }
}
