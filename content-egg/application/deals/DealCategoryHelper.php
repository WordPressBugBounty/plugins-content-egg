<?php

namespace ContentEgg\application\deals;

defined('\ABSPATH') || exit;

/**
 * DealCategoryHelper class file
 *
 * PostHelper's category creation, for deal_category - the import picks a
 * helper by post type and calls these two by name.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DealCategoryHelper
{
    public static function createCategory($category): int
    {
        $path = array_values(array_filter(array_map('sanitize_text_field', (array) $category), static function ($v) {
            return $v !== '';
        }));

        return $path ? self::createNestedCategories($path) : 0;
    }

    public static function createNestedCategories(array $path): int
    {
        $parent = 0;
        $term_id = 0;

        foreach ($path as $part)
        {
            $part = \sanitize_text_field($part);
            if ($part === '')
                continue;

            $existing = \term_exists($part, DealPostType::TAX_CATEGORY, $parent);
            if ($existing)
            {
                $term_id = (int) (is_array($existing) ? $existing['term_id'] : $existing);
            }
            else
            {
                $created = \wp_insert_term($part, DealPostType::TAX_CATEGORY, array('parent' => $parent));
                if (\is_wp_error($created))
                    return $term_id;

                $term_id = (int) $created['term_id'];
            }

            $parent = $term_id;
        }

        return $term_id;
    }
}
