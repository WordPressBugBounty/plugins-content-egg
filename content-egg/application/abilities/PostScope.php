<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\components\ContentManager;

/**
 * PostScope class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Shared post-scoped permission + lookup for abilities, mirroring
 * PostProductsRestController::permissions_check (CE post-type scoping +
 * per-post capability).
 */
final class PostScope
{
    /**
     * Statuses that make a post publicly reachable (now or on a schedule) and so
     * require the post type's publish capability, not merely edit access. `future`
     * is included because wp-cron transitions a scheduled post to `publish` with
     * no further capability check — so authorizing it as an ordinary edit would let
     * a user without publish rights self-publish on a delay. Mirrors the statuses
     * WordPress core gates behind the publish capability in its REST controller.
     */
    const PUBLISHING_STATUSES = array('publish', 'private', 'future');

    public static function isPublishingStatus(string $status): bool
    {
        return in_array($status, self::PUBLISHING_STATUSES, true);
    }

    /**
     * @param mixed $input
     */
    public static function canEditPost($input): bool
    {
        $post_id = is_array($input) ? (int) ($input['post_id'] ?? 0) : 0;

        if (!$post_id)
        {
            // Schema/execute guards reject the request anyway; don't leak more.
            return \current_user_can('edit_posts');
        }

        $post = \get_post($post_id);
        if (!$post)
        {
            return \current_user_can('edit_posts');
        }

        $post_types = (array) GeneralConfig::getInstance()->option('post_types');
        if (!in_array($post->post_type, $post_types, true))
        {
            return false;
        }

        return \current_user_can('edit_post', $post_id);
    }

    /**
     * Module ids that hold data on a post: the suffix of each _cegg_data_<module_id>
     * meta key, in meta order.
     *
     * @return string[]
     */
    public static function attachedModuleIds(int $post_id): array
    {
        return self::moduleIdsFromMetaKeys(array_keys((array) \get_post_meta($post_id)));
    }

    /**
     * The module ids in a list of post meta keys. Pure.
     *
     * @param array $meta_keys
     * @return string[]
     */
    public static function moduleIdsFromMetaKeys(array $meta_keys): array
    {
        $prefix = ContentManager::META_PREFIX_DATA;
        $module_ids = array();
        foreach ($meta_keys as $meta_key)
        {
            if (!is_string($meta_key) || strpos($meta_key, $prefix) !== 0)
            {
                continue;
            }
            $module_id = (string) substr($meta_key, strlen($prefix));
            if ($module_id !== '')
            {
                $module_ids[] = $module_id;
            }
        }

        return $module_ids;
    }

    /**
     * @return \WP_Post
     */
    public static function requirePost(array $input)
    {
        $post_id = (int) ($input['post_id'] ?? 0);
        $post = \get_post($post_id);

        if (!$post)
        {
            throw new AbilityInputException("Post {$post_id} not found.");
        }

        return $post;
    }
}
