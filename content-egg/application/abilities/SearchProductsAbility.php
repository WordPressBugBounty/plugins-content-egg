<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ParserModule;

/**
 * SearchProductsAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class SearchProductsAbility extends AbstractSearchAbility
{
    public function name(): string
    {
        return 'content-egg/search-products';
    }

    public function label(): string
    {
        return __('Search Products', 'content-egg');
    }

    public function description(): string
    {
        return 'Searches products through one active Content Egg product/affiliate module '
            . '(network API or imported feed). Returns a compact result list by default; pass '
            . 'fields="full" for all raw fields. NOTE: each call consumes the site\'s '
            . 'affiliate API quota and is rate-limited per user — batch your research, '
            . 'do not poll. To attach results to a post, pass the response\'s search_token plus the chosen unique_ids '
            . 'to content-egg/add-products-to-post — no need to echo items back or request fields="full". '
            . 'For images, videos or coupons use content-egg/search-images, search-videos or search-coupons.';
    }

    protected function moduleType(): string
    {
        return ParserModule::PARSER_TYPE_PRODUCT;
    }

    protected function moduleKind(): string
    {
        return 'product';
    }

    protected function mapLean(array $items): array
    {
        return LeanProduct::mapList($items);
    }
}
