<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ParserModule;

/**
 * SearchImagesAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class SearchImagesAbility extends AbstractSearchAbility
{
    public function name(): string
    {
        return 'content-egg/search-images';
    }

    public function label(): string
    {
        return __('Search Images', 'content-egg');
    }

    public function description(): string
    {
        return 'Searches royalty-free images through an active Content Egg image module '
            . '(Pixabay, Unsplash, Pexels, Bing/Google Images, Flickr, Qwant). Returns title, '
            . 'image URL and source page; pass fields="full" for dimensions, author and license. '
            . 'Use the returned image URLs directly in your content, '
            . 'or attach them to a post with content-egg/add-images-to-post (pass this response\'s '
            . 'search_token plus the chosen unique_ids) and render them with a content-egg/images '
            . 'block (in the classic editor or a non-Gutenberg post type, the [content-egg-block] shortcode). '
            . 'Consumes the module API quota; do not poll.';
    }

    protected function moduleType(): string
    {
        return ParserModule::PARSER_TYPE_IMAGE;
    }

    protected function moduleKind(): string
    {
        return 'image';
    }

    protected function mapLean(array $items): array
    {
        return LeanImage::mapList($items);
    }
}
