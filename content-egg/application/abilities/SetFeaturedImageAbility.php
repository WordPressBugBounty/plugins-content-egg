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
    const MAX_IMAGE_BYTES = RemoteImageImporter::MAX_IMAGE_BYTES;

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
            'type' => 'object',
            'default' => array(),
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
            $attachment_id = RemoteImageImporter::import($image_url, $post_id);
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
}
