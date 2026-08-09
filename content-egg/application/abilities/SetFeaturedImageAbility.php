<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

/**
 * SetFeaturedImageAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Set a post's featured image (thumbnail) from an existing attachment_id or by
 * importing a remote image_url into the media library. The URL path is hardened:
 * the URL and every redirect target are validated against WordPress' unsafe-URL
 * blocklist (SSRF), the download is size-capped, and the result must be a real
 * image (MIME + getimagesize) before it is attached. Overwrites any current
 * featured image, so it is marked destructive (confirm before calling).
 */
final class SetFeaturedImageAbility extends AbilityBase
{
    // Cap the imported file so a hostile/huge URL cannot exhaust disk/memory.
    const MAX_IMAGE_BYTES = 12582912; // 12 MB

    public function name(): string
    {
        return 'content-egg/set-featured-image';
    }

    public function label(): string
    {
        return __('Set Featured Image', 'content-egg');
    }

    public function description(): string
    {
        return "Sets a post's featured image from an existing attachment_id, or imports a "
            . 'remote image_url into the media library and sets that. Provide exactly one of '
            . 'attachment_id or image_url; optional alt sets the image alt text. Imported URLs '
            . 'are validated (no internal/unsafe hosts), size-capped and must be real images. '
            . 'Overwrites any existing featured image. If the post had a Content Egg external '
            . 'featured image (e.g. a product image) that would otherwise display via the External '
            . 'Featured Images feature, that per-post override is cleared so the image you set is the '
            . 'one shown. Returns the attachment_id and thumbnail_url.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => array('object', 'null'),
            'properties' => array(
                'post_id' => array('type' => 'integer', 'minimum' => 1),
                'attachment_id' => array(
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'Existing media library image to use.',
                ),
                'image_url' => array(
                    'type' => 'string',
                    'description' => 'http(s) URL of an image to import and set (e.g. from search-images).',
                ),
                'alt' => array(
                    'type' => 'string',
                    'description' => 'Optional alt text for the image.',
                ),
            ),
            'required' => array('post_id'),
            'additionalProperties' => false,
        );
    }

    public function outputSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'post_id' => array('type' => 'integer'),
                'attachment_id' => array('type' => 'integer'),
                'thumbnail_url' => array('type' => 'string'),
                'cleared_external_featured_image' => array(
                    'type' => 'boolean',
                    'description' => 'Present and true when a Content Egg external featured-image override was cleared so this image displays.',
                ),
                'note' => array('type' => 'string'),
            ),
        );
    }

    public function annotations(): array
    {
        // Overwrites the current thumbnail: destructive + confirm-first. idempotent
        // is false so core routes it POST (destructive && idempotent => DELETE).
        return array('readonly' => false, 'destructive' => true, 'idempotent' => false);
    }

    public function checkPermission($input = null): bool
    {
        if (!PostScope::canEditPost($input))
        {
            return false;
        }
        // Importing a URL creates an attachment — needs the upload capability.
        $importing = is_array($input) && !empty($input['image_url']);
        return $importing ? \current_user_can('upload_files') : true;
    }

    public function execute(array $input): array
    {
        $post = PostScope::requirePost($input);
        $post_id = (int) $post->ID;

        $attachment_id = (int) ($input['attachment_id'] ?? 0);
        $image_url = trim((string) ($input['image_url'] ?? ''));
        $alt = trim((string) ($input['alt'] ?? ''));

        if ($attachment_id && $image_url !== '')
        {
            throw new AbilityInputException("Provide either 'attachment_id' or 'image_url', not both.");
        }
        if (!$attachment_id && $image_url === '')
        {
            throw new AbilityInputException("Provide 'attachment_id' or 'image_url'.");
        }

        $imported = false;
        if ($image_url !== '')
        {
            $attachment_id = $this->importImage($image_url, $post_id);
            $imported = true;
        }
        elseif (!\wp_attachment_is_image($attachment_id))
        {
            throw new AbilityInputException("attachment_id {$attachment_id} is not an image in the media library.");
        }

        if ($alt !== '')
        {
            // Alt text is global metadata on the attachment, so writing it is an
            // edit of that media object. A freshly imported image is authored by
            // the caller; an existing attachment_id may belong to anyone, so require
            // edit rights before mutating another user's media.
            if (!$imported && !\current_user_can('edit_post', $attachment_id))
            {
                throw new AbilityInputException(
                    "You do not have permission to edit attachment {$attachment_id}, so its alt text cannot be changed."
                );
            }
            \update_post_meta($attachment_id, '_wp_attachment_image_alt', \sanitize_text_field($alt));
        }

        if (!\set_post_thumbnail($post_id, $attachment_id))
        {
            throw new AbilityInputException('Could not set the featured image on this post.');
        }

        // An explicitly chosen featured image must actually display. Content Egg's
        // External Featured Images feature stores a product image URL in post meta
        // and, under "enabled"/"external priority", masks the real _thumbnail_id
        // with a sentinel id (getFakeThumbnailId) — so the attachment just set would
        // be shadowed and unresolvable via REST. Clear that per-post override so the
        // caller's choice wins. (A later product refresh may re-add it.)
        $cleared_external = false;
        if (class_exists('\ContentEgg\application\components\ExternalFeaturedImage'))
        {
            $cleared_external = (bool) \ContentEgg\application\components\ExternalFeaturedImage::removeExternalFeaturedImage($post_id);
        }

        $result = array(
            'post_id' => $post_id,
            'attachment_id' => (int) $attachment_id,
            'thumbnail_url' => (string) \wp_get_attachment_url($attachment_id),
        );

        if ($cleared_external)
        {
            $result['cleared_external_featured_image'] = true;
            $result['note'] = 'This post had a Content Egg external featured image (e.g. a product '
                . 'image) that would have displayed instead; it was cleared so the image you set is '
                . 'used. A later product refresh on this post may re-add an external image.';
        }

        return $result;
    }

    /**
     * Download a remote image safely and add it to the media library attached to
     * $post_id. Throws AbilityInputException on any validation failure. Returns
     * the new attachment id.
     */
    private function importImage(string $url, int $post_id): int
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

        $tmp = $this->downloadCapped($url, 30);

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
    private function downloadCapped(string $url, int $timeout): string
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
            // rejected by the filesize() backstop in importImage().
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
