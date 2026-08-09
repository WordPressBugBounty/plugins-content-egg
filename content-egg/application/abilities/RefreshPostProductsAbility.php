<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ProductDataService;

/**
 * RefreshPostProductsAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class RefreshPostProductsAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/refresh-post-products';
    }

    public function label(): string
    {
        return __('Refresh Post Products', 'content-egg');
    }

    public function description(): string
    {
        return 'Re-fetches a post\'s product data from the affiliate networks. '
            . 'type "prices" refreshes prices/stock of the attached items; type '
            . '"listings" re-runs the stored search keywords and REPLACES the product '
            . 'lists. Runs synchronously and may take up to a minute on posts with many '
            . 'modules; it consumes the site\'s affiliate API quota — do not call in a '
            . 'loop. Returns after all modules are refreshed. This rewrites stored product '
            . 'data, so any revision tokens you hold go stale — re-read get-post-products before further edits.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => array('object', 'null'),
            'properties' => array(
                'post_id' => array('type' => 'integer', 'minimum' => 1),
                'type' => array('type' => 'string', 'enum' => array('listings', 'prices')),
            ),
            'required' => array('post_id', 'type'),
            'additionalProperties' => false,
        );
    }

    public function outputSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'post_id' => array('type' => 'integer'),
                'ok' => array('type' => 'boolean'),
            ),
        );
    }

    public function annotations(): array
    {
        return array('readonly' => false, 'destructive' => false, 'idempotent' => false);
    }

    public function checkPermission($input = null): bool
    {
        return PostScope::canEditPost($input);
    }

    // A refresh runs synchronously, fans out live affiliate-API calls and can hold
    // a PHP worker for up to a minute, so it is far heavier than a search. Keep the
    // per-user allowance low to stop an agent loop from burning API quota / workers.
    const DEFAULT_RATE_PER_MINUTE = 5;

    public function execute(array $input): array
    {
        $post = PostScope::requirePost($input);
        $post_id = (int) $post->ID;
        $type = (string) ($input['type'] ?? '');

        // Unlike the read-only search abilities this is a write that consumes the
        // site's affiliate API quota; rate-limit it per user (same soft bucket the
        // searches use) so it cannot be hammered in a loop.
        $rate = (int) \apply_filters('content_egg_abilities_refresh_rate_limit', self::DEFAULT_RATE_PER_MINUTE);
        $wait = RateLimiter::hit('refresh:' . \get_current_user_id(), $rate);
        if ($wait > 0)
        {
            throw new AbilityRateLimitException($wait);
        }

        // ProductDataService::refresh throws \InvalidArgumentException on a bad
        // type; the registrar maps it to cegg_validation_failed.
        ProductDataService::refresh($post_id, $type);

        return array('post_id' => $post_id, 'ok' => true);
    }
}
