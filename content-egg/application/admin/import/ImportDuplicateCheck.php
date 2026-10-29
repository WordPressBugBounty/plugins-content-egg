<?php

namespace ContentEgg\application\admin\import;

use ContentEgg\application\deals\DealLookup;
use ContentEgg\application\deals\DealPostType;
use ContentEgg\application\helpers\PostHelper;

defined('\ABSPATH') || exit;

/**
 * ImportDuplicateCheck class file
 *
 * The post an import of one product would duplicate. Posts and WooCommerce
 * products match on the import's unique id, as before. A deal import also
 * sees every deal for the same offer - imported, made by an agent or by hand -
 * in any status: an expired deal revives on its own when the offer is good
 * again, so the offer keeps its one deal page.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ImportDuplicateCheck
{
    public static function existingPostId(string $target_post_type, string $module_id, string $unique_id): int
    {
        if ($unique_id === '')
            return 0;

        if ($post_id = PostHelper::getPostIdByUniqueId($unique_id))
            return $post_id;

        if ($target_post_type !== DealPostType::POST_TYPE)
            return 0;

        if ($post_id = PostHelper::getPostIdByUniqueId($unique_id, array(DealPostType::POST_TYPE)))
            return $post_id;

        return DealLookup::importDuplicate($module_id, $unique_id);
    }
}
