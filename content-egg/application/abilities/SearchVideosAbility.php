<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ParserModule;

/**
 * SearchVideosAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class SearchVideosAbility extends AbstractSearchAbility
{
    public function name(): string
    {
        return 'content-egg/search-videos';
    }

    public function label(): string
    {
        return __('Search Videos', 'content-egg');
    }

    public function description(): string
    {
        return 'Searches videos through an active Content Egg video module (YouTube, Pexels '
            . 'Videos). Returns title, watch URL, thumbnail and channel; pass fields="full" for '
            . 'the raw fields. Use the returned URLs directly (e.g. a core embed), '
            . 'or attach them to a post with content-egg/add-videos-to-post (pass this response\'s '
            . 'search_token plus the chosen unique_ids) and render them with a content-egg/videos '
            . 'block (in the classic editor or a non-Gutenberg post type, the [content-egg-block] '
            . 'shortcode). Consumes the module API quota; do not poll.';
    }

    protected function moduleType(): string
    {
        return ParserModule::PARSER_TYPE_VIDEO;
    }

    protected function moduleKind(): string
    {
        return 'video';
    }

    protected function mapLean(array $items): array
    {
        return LeanVideo::mapList($items);
    }
}
