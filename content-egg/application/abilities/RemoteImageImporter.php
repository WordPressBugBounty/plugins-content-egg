<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

/**
 * RemoteImageImporter class file
 *
 * Imports a remote image into the media library with the SSRF, size and MIME
 * checks the agent abilities need (set-featured-image, deal featured images).
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class RemoteImageImporter
{
    const MAX_IMAGE_BYTES = 12582912; // 12 MB

    /**
     * Download a remote image safely and add it to the media library attached to
     * $post_id. Throws AbilityInputException on any validation failure. Returns
     * the new attachment id.
     */
    public static function import(string $url, int $post_id): int
    {
        // SSRF: reject non-http(s), bad ports, localhost and private IPs up front.
        // downloadCapped() below streams via wp_safe_remote_get (reject_unsafe_urls),
        // which re-validates every redirect target, so redirects cannot bypass this.
        if (!\wp_http_validate_url($url))
        {
            throw new AbilityInputException(
                "image_url is not a valid, allowed URL (must be a public http(s) image URL)."
            );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = self::downloadCapped($url, 30);

        try
        {
            $size = (int) @filesize($tmp);
            if ($size <= 0 || $size > self::MAX_IMAGE_BYTES)
            {
                throw new AbilityInputException(
                    'Image is empty or exceeds the ' . round(self::MAX_IMAGE_BYTES / 1048576) . ' MB limit.'
                );
            }

            // Verify the MIME from the file CONTENTS (getimagesize reads the image
            // header), not the URL/filename — many image URLs carry no extension.
            // Only real, known image types are accepted, and the stored extension
            // comes from the detected type.
            $ext_by_mime = array(
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'image/avif' => 'avif',
            );
            $sized = @getimagesize($tmp);
            $mime = (is_array($sized) && !empty($sized['mime'])) ? strtolower((string) $sized['mime']) : '';
            if (!isset($ext_by_mime[$mime]))
            {
                throw new AbilityInputException(
                    'image_url did not return a supported image (JPEG, PNG, GIF, WebP or AVIF).'
                );
            }

            $base = \sanitize_file_name((string) \wp_basename((string) (parse_url($url, PHP_URL_PATH) ?: '')));
            $base = (string) preg_replace('/\.[a-z0-9]+$/i', '', $base);
            if ($base === '')
            {
                $base = 'featured-image';
            }
            $name = $base . '.' . $ext_by_mime[$mime];

            $file = array('name' => $name, 'tmp_name' => $tmp);
            $attachment_id = \media_handle_sideload($file, $post_id);
        }
        finally
        {
            if (file_exists($tmp))
            {
                @unlink($tmp);
            }
        }

        if (\is_wp_error($attachment_id))
        {
            throw new AbilityInputException('Could not import the image: ' . $attachment_id->get_error_message());
        }

        return (int) $attachment_id;
    }

    /**
     * Stream a remote URL to a temp file, aborting once it passes MAX_IMAGE_BYTES
     * instead of letting a hostile/huge response fill the disk before the size is
     * checked (as plain download_url() would). Uses wp_safe_remote_get so the SSRF
     * URL validation — including re-validation of every redirect target — still
     * applies. Returns the temp path (caller unlinks it); throws on any failure.
     */
    private static function downloadCapped(string $url, int $timeout): string
    {
        $tmp = \wp_tempnam($url);
        if (!$tmp)
        {
            throw new AbilityInputException('Could not create a temporary file for the image download.');
        }

        $response = \wp_safe_remote_get($url, array(
            'timeout' => $timeout,
            'stream' => true,
            'filename' => $tmp,
            // +1 so a file of exactly the limit still passes; anything larger trips
            // the transport's byte cap (cURL) or is truncated past the cap and then
            // rejected by the filesize() backstop in import().
            'limit_response_size' => self::MAX_IMAGE_BYTES + 1,
        ));

        if (\is_wp_error($response))
        {
            if (file_exists($tmp))
            {
                @unlink($tmp);
            }
            throw new AbilityInputException('Could not download image_url: ' . $response->get_error_message());
        }

        $code = (int) \wp_remote_retrieve_response_code($response);
        if ($code !== 200)
        {
            if (file_exists($tmp))
            {
                @unlink($tmp);
            }
            throw new AbilityInputException("image_url returned HTTP status {$code}; expected 200.");
        }

        return $tmp;
    }
}
