<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

/**
 * SearchTokenResolver class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Resolves add-* item refs (unique_id strings, or objects with unique_id and
 * optional overrides) against a SearchResultCache payload. The cached item is
 * authoritative: every other field on an object ref is deliberately ignored,
 * so token-mode callers cannot alter stored content. Every failure is an
 * explicit AbilityInputException — never a silent drop.
 */
final class SearchTokenResolver
{
    /**
     * @param string $search_token token from the search response
     * @param array  $items        item refs as given by the caller
     * @param string $module_id    module the add call targets
     * @return array{items: array, keyword: string} resolved full items in input
     *         order (overrides re-attached where given) + cached search keyword
     * @throws AbilityInputException
     */
    public static function resolve(string $search_token, array $items, string $module_id): array
    {
        $payload = SearchResultCache::get($search_token);
        if ($payload === null)
        {
            throw new AbilityInputException(
                'Search results expired or unknown search_token — re-run the search and use the '
                    . 'new search_token from its response.'
            );
        }

        if ((string) ($payload['module_id'] ?? '') !== $module_id)
        {
            throw new AbilityInputException(
                "This search_token belongs to module '" . (string) ($payload['module_id'] ?? '') . "', "
                    . "but this call targets '{$module_id}'. Use the token returned by the search "
                    . 'you ran on this module.'
            );
        }

        $resolved = array();
        $missing = array();

        foreach (array_values($items) as $i => $entry)
        {
            $overrides = null;

            if (is_string($entry) || is_int($entry))
            {
                $uid = (string) $entry;
            }
            elseif (is_array($entry))
            {
                $uid = (string) ($entry['unique_id'] ?? '');
                if ($uid === '')
                {
                    throw new AbilityInputException(
                        "items[{$i}] must include a unique_id when search_token is set."
                    );
                }
                if (isset($entry['overrides']) && is_array($entry['overrides']))
                {
                    $overrides = $entry['overrides'];
                }
            }
            else
            {
                throw new AbilityInputException(
                    "items[{$i}] must be a unique_id string or an object with unique_id."
                );
            }

            if (!isset($payload['items'][$uid]))
            {
                $missing[] = $uid;
                continue;
            }

            // Cached item is authoritative — nothing from the ref survives
            // except unique_id (the lookup) and overrides (whitelisted later
            // by the products ability; stripped for other families).
            $item = $payload['items'][$uid];
            if ($overrides !== null)
            {
                $item['overrides'] = $overrides;
            }
            $resolved[] = $item;
        }

        if ($missing)
        {
            throw new AbilityInputException(
                "These unique_ids are not in this search_token's results: " . implode(', ', $missing)
                    . '. Pass unique_ids exactly as returned by the search that issued the token, '
                    . 'or re-run the search.'
            );
        }

        return array(
            'items' => $resolved,
            'keyword' => (string) ($payload['keyword'] ?? ''),
        );
    }
}
