<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ParserModule;

/**
 * SearchCouponsAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class SearchCouponsAbility extends AbstractSearchAbility
{
    public function name(): string
    {
        return 'content-egg/search-coupons';
    }

    public function label(): string
    {
        return __('Search Coupons', 'content-egg');
    }

    public function description(): string
    {
        return 'Searches coupons and deals through an active Content Egg coupon module '
            . '(Admitad, CJ Links, Skimlinks, Tradedoubler/Tradetracker coupons). Returns title, '
            . 'code, destination URL, merchant and start/end dates; pass fields="full" for the raw '
            . 'fields. Use the returned codes/URLs directly, '
            . 'or attach them to a post with content-egg/add-coupons-to-post (pass this response\'s '
            . 'search_token plus the chosen unique_ids) and render them with a content-egg/coupons '
            . 'block (in the classic editor or a non-Gutenberg post type, the [content-egg-block] shortcode). '
            . 'Consumes the module API quota; do not poll.';
    }

    protected function moduleType(): string
    {
        return ParserModule::PARSER_TYPE_COUPON;
    }

    protected function moduleKind(): string
    {
        return 'coupon';
    }

    protected function mapLean(array $items): array
    {
        return LeanCoupon::mapList($items);
    }
}
