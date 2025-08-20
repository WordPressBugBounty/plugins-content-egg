<?php

namespace ContentEgg\application\helpers;

defined('\ABSPATH') || exit;

/**
 * WooHelper class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2025 keywordrush.com
 *
 */
class WooHelper
{

	public static function isWooActive()
	{
		if (class_exists('\WooCommerce'))
			return true;
		elseif (in_array('woocommerce/woocommerce.php', \apply_filters('active_plugins', \get_option('active_plugins'))))
			return true;
		else
			return false;
	}

	public static function getWooCategoryList()
	{
		$terms = \get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
		if (!$terms || \is_wp_error($terms))
			return array();
		$categories = array();

		foreach ($terms as $term)
		{
			$categories[$term->term_id] = $term->name . ' [' . $term->term_id . ']';
		}
		return $categories;
	}

	public static function getPostCategoryList()
	{
		$terms = \get_terms(array('taxonomy' => 'category', 'hide_empty' => false));
		if (!$terms || \is_wp_error($terms))
			return array();
		$categories = array();

		foreach ($terms as $term)
		{
			$categories[$term->term_id] = $term->name . ' [' . $term->term_id . ']';
		}
		return $categories;
	}

	public static function createCategory($category)
	{
		if (!is_array($category))
			$category = array($category);

		return self::createNestedCategories($category);
	}

	public static function createNestedCategories(array $categoryPath)
	{
		$parent = 0;
		foreach ($categoryPath as $category)
		{
			$category = \sanitize_text_field($category);

			if (!$ids = \term_exists($category, 'product_cat', $parent))
			{
				$ids = \wp_insert_term($category, 'product_cat', array('parent' => $parent));
				if (\is_wp_error($ids))
					return false;
			}

			$parent = $ids['term_id'];
		}
		return $parent;
	}

	public static function uploadMedias(array $image_urls, $post_id, $title = '')
	{
		$attach_ids = array();
		foreach ($image_urls as $image_url)
		{
			if ($attach_id = self::uploadMedia($image_url, $post_id, $title))
				$attach_ids[] = $attach_id;
		}
		return $attach_ids;
	}

	public static function uploadMedia($image_url, $post_id, $title = '')
	{
		$check_image_type = \apply_filters('cegg_check_image_type', true);

		if (!$file_name = ImageHelper::saveImgLocaly($image_url, $title, $check_image_type))
			return false;

		$uploads = \wp_upload_dir();
		$img_path = ltrim(trailingslashit($uploads['subdir']), '\/') . $file_name;
		$img_file = ImageHelper::getFullImgPath($img_path);

		$img_file = \apply_filters('cegg_handle_upload_media', $img_file);

		return self::addMedia($img_file, $post_id, $title);
	}

	public static function addMedia($img_file, $post_id, $title = '')
	{
		require_once(ABSPATH . 'wp-admin/includes/image.php');

		$filetype = \wp_check_filetype(basename($img_file), null);
		$attachment = array(
			'guid' => $img_file,
			'post_mime_type' => $filetype['type'],
			'post_title' => $title,
			'post_content' => '',
			'post_status' => 'inherit'
		);

		$attach_id = \wp_insert_attachment($attachment, $img_file, $post_id);

		if ($title)
			\update_post_meta($attach_id, '_wp_attachment_image_alt', $title);

		$attach_data = \wp_generate_attachment_metadata($attach_id, $img_file);
		\wp_update_attachment_metadata($attach_id, $attach_data);
		return $attach_id;
	}
}
