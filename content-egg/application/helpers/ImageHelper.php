<?php

namespace ContentEgg\application\helpers;

use ContentEgg\application\ImageProxy;
use ContentEgg\application\libs\WpHttpClient;



defined('\ABSPATH') || exit;

/**
 * ImageHelper class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 *
 */
class ImageHelper
{

	const DOWNLOAD_TIMEOUT = 10;

	// Upper bound on source pixels (W*H) we will decode for resizing. A tiny
	// "pixel-flood" file (e.g. 25000x25000 PNG, a few KB on disk) passes the
	// cheap getimagesize() header check but needs ~W*H*4 bytes once GD/Imagick
	// decodes it, OOM-killing the worker. An OOM fatal is not catchable, so it
	// would also stall the backfill on that post forever; skip such files.
	const MAX_DECODE_PIXELS = 40000000;
	const USERAGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.3 Safari/605.1.15';
	const USERAGENT2 = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:139.0) Gecko/20100101 Firefox/139.0';

	public static function saveImgLocaly($img_uri, $title = '', $check_image_type = true)
	{
		$newfilename = self::slugifyTitle($title);
		if (!$newfilename)
		{
			$newfilename = time();
		}

		$uploads = \wp_upload_dir();

		if ($newfilename = self::downloadImg($img_uri, $uploads['path'], $newfilename, null, $check_image_type))
		{
			return $newfilename;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Build a readable, SEO-friendly filename slug from a product title.
	 *
	 * Produces a lowercase, hyphen-separated slug (accents transliterated),
	 * e.g. "Nespresso Vertuo 30 Count" -> "nespresso-vertuo-30-count".
	 * Returns '' for titles that yield no latin characters (e.g. CJK), so the
	 * caller can fall back to a timestamp as before.
	 */
	public static function slugifyTitle($title, $max_length = 80)
	{
		$slug = \sanitize_title((string) $title);

		// sanitize_title percent-encodes non-latin characters (CJK, etc.),
		// which makes an unreadable filename; drop those sequences.
		$slug = preg_replace('/%[0-9a-f]{2}/i', '', $slug);

		// Hard whitelist: the result is used as a filename, so guarantee it
		// contains ONLY [a-z0-9-] regardless of what sanitize_title (or a
		// third-party 'sanitize_title' filter) returned. No dots, slashes,
		// backslashes, null bytes or traversal sequences can survive this.
		$slug = strtolower($slug);
		$slug = preg_replace('/[^a-z0-9-]/', '', $slug);
		$slug = trim(preg_replace('/-+/', '-', $slug), '-');

		if ($max_length > 0 && strlen($slug) > $max_length)
		{
			$slug = substr($slug, 0, $max_length);
			// Trim back to the last whole word so we don't cut mid-word.
			if (strpos($slug, '-') !== false)
				$slug = preg_replace('/-[^-]*$/', '', $slug);
			$slug = trim($slug, '-');
		}

		return $slug;
	}

	static public function getUserAgent($img_uri)
	{
		if (strpos($img_uri, 'https://www.iciparisxl.nl/medias') !== false)
			$user_agent = self::USERAGENT2;
		else
			$user_agent = self::USERAGENT;

		return \apply_filters('cegg_image_user_agent', $user_agent, $img_uri);
	}

	public static function downloadImg($img_uri, $save_dir, $file_name, $file_ext = null, $check_image_type = true)
	{
		$response = \wp_remote_get($img_uri, array(
			'timeout'     => self::DOWNLOAD_TIMEOUT,
			'redirection' => 1,
			'sslverify'   => WpHttpClient::sslVerifyDefault(),
			'user-agent'  => self::getUserAgent($img_uri),
		));

		if (\is_wp_error($response) || (int) \wp_remote_retrieve_response_code($response) !== 200)
			return false;

		if ($file_ext === null)
		{
			$img_path = parse_url($img_uri, PHP_URL_PATH);
			$file_ext = pathinfo(basename($img_path), PATHINFO_EXTENSION);
			// Trust the path-derived extension only when it is a real image
			// extension. Otherwise fall back to the response content-type: some
			// CDNs put a non-image token where the extension would be — e.g.
			// img.logo.dev/nike.com yields "com", and legacy .aspx/.image
			// handlers — which must not be used as the saved file type.
			$image_exts = array('jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp', 'bmp', 'svg', 'ico', 'avif', 'tif', 'tiff');
			if (!$file_ext || !in_array(strtolower($file_ext), $image_exts, true))
			{
				$headers = \wp_remote_retrieve_headers($response);
				if (empty($headers['content-type']))
					return false;

				$types = array_search(str_replace(';charset=UTF-8', '', $headers['content-type']), \wp_get_mime_types());

				if (!$types)
					return false;

				$exts     = explode('|', $types);
				$file_ext = $exts[0];
			}
		}
		if ($file_ext)
		{
			$file_name .= '.' . $file_ext;
		}

		$file_name = \wp_unique_filename($save_dir, $file_name);

		if ($check_image_type)
		{
			$filetype = \wp_check_filetype($file_name, null);

			if (substr($filetype['type'], 0, 5) != 'image')
			{
				return false;
			}
		}

		$image_string = \wp_remote_retrieve_body($response);
		$file_path    = \trailingslashit($save_dir) . $file_name;
		if (!file_put_contents($file_path, $image_string))
		{
			return false;
		}

		if ($check_image_type && !self::isImage($file_path))
		{
			@unlink($file_path);

			return false;
		}
		if (!defined('FS_CHMOD_FILE'))
		{
			define('FS_CHMOD_FILE', (fileperms(ABSPATH . 'index.php') & 0777 | 0644));
		}
		@chmod($file_path, FS_CHMOD_FILE);

		return $file_name;
	}

	public static function isImage($path)
	{
		if (!$a = getimagesize($path))
		{
			return false;
		}
		$image_type = $a[2];
		if (in_array($image_type, array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_BMP, IMAGETYPE_WEBP)))
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	public static function getFullImgPath($img_path)
	{
		$uploads = \wp_upload_dir();
		$basedir = trailingslashit($uploads['basedir']);

		$img_path = self::normalizeRelativeImgPath($img_path);
		if ($img_path === '')
			return $basedir;

		return $basedir . $img_path;
	}

	/**
	 * Sanitize an img_file value coming from user input (post meta).
	 *
	 * Strips markup and reduces the value to a safe, uploads-relative path.
	 */
	public static function sanitizeImgFile($img_path): string
	{
		return self::normalizeRelativeImgPath(\wp_strip_all_tags((string) $img_path));
	}

	/**
	 * Normalize a stored img_file value to a safe, uploads-relative path.
	 *
	 * img_file is persisted in post meta and later concatenated onto the
	 * uploads basedir before file operations (unlink/copy) by getFullImgPath().
	 * Because the value is only wp_strip_all_tags()-sanitized on input, a
	 * hostile Author could store a traversal sequence (e.g.
	 * "../../../wp-config.php") and have it resolve outside the uploads
	 * directory. Dropping "." / ".." and empty (absolute/leading-slash)
	 * segments guarantees the result stays inside uploads by construction,
	 * for every getFullImgPath() caller (unlink/copy/existence checks).
	 */
	private static function normalizeRelativeImgPath($img_path): string
	{
		if (!is_string($img_path))
			return '';

		// Kill null bytes and unify separators (Windows backslashes included).
		$img_path = str_replace(array("\0", '\\'), array('', '/'), $img_path);

		$segments = array();
		foreach (explode('/', $img_path) as $segment)
		{
			// Drop empty segments (leading/absolute paths, doubled slashes),
			// current-dir "." and any parent-dir ".." traversal.
			if ($segment === '' || $segment === '.' || $segment === '..')
				continue;

			$segments[] = $segment;
		}

		return implode('/', $segments);
	}

	/**
	 * Resize a locally saved image in place so its longest side fits $max_size.
	 *
	 * Same path, same format; the original file is replaced. Idempotent by
	 * construction: a file already within $max_size is never touched, so
	 * dimensions themselves mark the file as processed. GIFs are skipped
	 * (GD would flatten animation).
	 *
	 * @return bool true only when the file was actually rewritten.
	 */
	public static function optimizeImage($full_path, $max_size, $quality)
	{
		$max_size = (int) $max_size;
		$quality = (int) $quality;

		if ($max_size < 1 || !$full_path || !@is_file($full_path))
			return false;

		$size = @getimagesize($full_path);
		if (!$size || empty($size[0]) || empty($size[1]))
			return false;

		if (!empty($size[2]) && $size[2] === IMAGETYPE_GIF)
			return false;

		if (max($size[0], $size[1]) <= $max_size)
			return false;

		// Refuse to decode a decompression bomb: skip (return false) so the
		// backfill cursor advances past it instead of re-OOMing every run.
		if ((int) $size[0] * (int) $size[1] > self::MAX_DECODE_PIXELS)
			return false;

		if (function_exists('\wp_raise_memory_limit'))
			\wp_raise_memory_limit('image');

		try
		{
			$editor = \wp_get_image_editor($full_path);
			if (\is_wp_error($editor))
				return false;

			if (\is_wp_error($editor->resize($max_size, $max_size, false)))
				return false;

			if ($quality >= 10 && $quality <= 100)
				$editor->set_quality($quality);

			$saved = $editor->save($full_path);

			return !\is_wp_error($saved);
		}
		catch (\Throwable $e)
		{
			error_log('Content Egg: image optimization failed for ' . $full_path . ': ' . $e->getMessage());
			return false;
		}
	}
}
