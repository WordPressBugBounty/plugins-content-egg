<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\ProductManagerLoader;

/**
 * PostSaveDataNotifier class file
 *
 * Re-fires `content_egg_save_data` when a post is saved while a React product
 * manager owns the screen.
 *
 * The Angular metabox submitted product data with the post form, so saving a
 * post always ran ContentManager::saveData() -> content_egg_save_data for every
 * module. Themes that mirror CE data into their own post meta rely on that: the
 * mirror was rewritten on every Save draft / Publish, late in the save.
 *
 * The React manager writes through the REST API while the user edits, so the
 * post save no longer carries product data and EggMetabox::saveMeta() bails (no
 * `contentegg_nonce`) — nothing fires. A mirror written during the REST call is
 * then only as durable as the rest of the save, and a theme metabox can undo it
 * in the very next request. Rehub is the case that surfaced this: its Offer
 * panel owns the same meta keys its CE sync writes (rehub_offer_name,
 * rehub_offer_product_url/_price/_desc/_thumb) and its save_single_meta_field()
 * deletes any field submitted empty:
 *
 *     } elseif ('' == $new && $old) { delete_post_meta($post_id, $field['id'], $old); }
 *
 * The panel is rendered when the edit screen loads — before the manager has
 * added anything — so its inputs are empty, and saving the post deletes exactly
 * the keys the panel owns. Under the Angular metabox CE re-wrote them later in
 * the same request; with the React manager the deletion stood, and the post
 * stayed broken until the next CE write (an "Update listing" or a cron refresh).
 *
 * This restores the old guarantee: on post save, announce the data that is
 * already stored — once per module that has data, without touching it. The
 * action only notifies; it never writes, so price history and price alerts
 * (which live inside saveData) are not re-run.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class PostSaveDataNotifier
{
    /** Posts already announced in this request — guards re-entrant post saves. */
    private static $notified = array();

    public static function initAction()
    {
        // Priority 100 is load-bearing, not cosmetic: theme metaboxes save their
        // own fields at the default 10, and one of them may delete a key this
        // announcement restores (see the class docblock). Running last also means
        // any meta the editor submits alongside the post — a theme's "which offer
        // to sync" selector, say — is already stored when listeners read it.
        \add_action('save_post', array(__CLASS__, 'onSavePost'), 100, 2);
    }

    /**
     * @param int $post_id
     * @param \WP_Post|null $post
     */
    public static function onSavePost($post_id, $post = null)
    {
        if (\defined('DOING_AUTOSAVE') && \DOING_AUTOSAVE)
            return;

        $post_id = (int) $post_id;
        if (!$post_id || \wp_is_post_revision($post_id))
            return;

        // The Angular metabox fires the action from its own save path.
        if (ProductManagerLoader::mode() === 'metabox')
            return;

        if (!$post instanceof \WP_Post)
            $post = \get_post($post_id);

        if (!$post instanceof \WP_Post)
            return;

        // An auto-draft is not a save the user made; trashing a post is not a
        // data change either.
        if (in_array($post->post_status, array('auto-draft', 'trash'), true))
            return;

        if (!in_array($post->post_type, (array) GeneralConfig::getInstance()->option('post_types')))
            return;

        if (isset(self::$notified[$post_id]))
            return;

        if (!\apply_filters('cegg_notify_save_data_on_post_save', true, $post_id, $post))
            return;

        $module_ids = self::modulesWithData($post_id);
        if (!$module_ids)
            return;

        self::$notified[$post_id] = true;

        $last = count($module_ids);
        $i = 0;
        foreach ($module_ids as $module_id)
        {
            $i++;
            \do_action(
                'content_egg_save_data',
                ContentManager::getData($post_id, $module_id),
                $module_id,
                $post_id,
                $i === $last
            );
        }
    }

    /**
     * Ids of the active modules that hold data for this post, read from the
     * `_cegg_data_<module_id>` meta keys. Modules without data are skipped:
     * a listener re-reads whatever it needs anyway, and an install can have
     * hundreds of active modules.
     *
     * @return array<int,string>
     */
    private static function modulesWithData($post_id): array
    {
        global $wpdb;

        $prefix = ContentManager::META_PREFIX_DATA;

        $meta_keys = $wpdb->get_col($wpdb->prepare(
            "SELECT meta_key FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
            $post_id,
            $wpdb->esc_like($prefix) . '%'
        ));

        if (!$meta_keys)
            return array();

        $mm = ModuleManager::getInstance();

        $module_ids = array();
        foreach ($meta_keys as $meta_key)
        {
            $module_id = substr($meta_key, strlen($prefix));
            if (!$module_id)
                continue;

            if (!$mm->moduleExists($module_id) || !$mm->isModuleActive($module_id))
                continue;

            if (!ContentManager::isNotEmptyDataExists($post_id, $module_id))
                continue;

            $module_ids[] = $module_id;
        }

        return $module_ids;
    }
}
