<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\matching\DiscoveryLog;
use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\components\ParserModule;

/**
 * DiscoveryRecorder class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Logs which modules were searched for a page, and how (DiscoveryLog), from the
 * two writes that consume a search: add-products-to-post and set-offer-decisions.
 * The search abilities stay read-only, so a search is logged only when an agent
 * acts on it: with its search_token, or (for a search that found nothing, which
 * issues no token) with a `searched` entry.
 *
 * The mode of a query (gtin, url, native_id, keyword) is read the way the search
 * itself reads it, by SearchMode.
 */
final class DiscoveryRecorder
{
    const MAX_SEARCHED = 20;

    /**
     * Log the search behind a token: the module it ran on, its keyword, how many
     * results it stored. Nothing is logged when the token is unknown, expired or
     * another user's, the module is not available in this build, or a keyword
     * search would replace the module's identifier search (DiscoveryLog::record()).
     *
     * @return string the logged module id, or '' when nothing was logged
     */
    public static function fromToken(int $post_id, string $search_token): string
    {
        $search_token = trim($search_token);
        if ($post_id <= 0 || $search_token === '')
        {
            return '';
        }

        $payload = SearchResultCache::get($search_token);
        if ($payload === null)
        {
            return '';
        }

        $module_id = trim((string) ($payload['module_id'] ?? ''));
        if ($module_id === '')
        {
            return '';
        }

        $entry = self::entryOrNull(function () use ($module_id, $payload)
        {
            return self::entryFor(
                $module_id,
                (string) ($payload['keyword'] ?? ''),
                is_array($payload['items'] ?? null) ? count($payload['items']) : 0
            );
        });
        if ($entry === null)
        {
            return '';
        }

        // A keyword search does not replace the module's identifier search: nothing logged then.
        return DiscoveryLog::record($post_id, $module_id, $entry) ? $module_id : '';
    }

    /**
     * The entry $build returns, or null when it cannot be built: a module that is not a product
     * module (AbilityInputException) or a mode DiscoveryLog does not know (\InvalidArgumentException,
     * which AbilityInputException extends). fromToken() runs after the write it logs has succeeded,
     * so an entry it cannot build is left out instead of failing that write.
     *
     * @param callable $build function (): array
     */
    public static function entryOrNull(callable $build): ?array
    {
        try
        {
            return $build();
        }
        catch (\InvalidArgumentException $e)
        {
            return null;
        }
    }

    /**
     * Log searches the agent reports, each {module_id, query, results}. All the
     * entries are checked before the first one is written.
     *
     * @return string[] the logged module ids
     * @throws AbilityInputException a bad entry, or a module that is not a loadable product module
     */
    public static function fromSearched(int $post_id, array $searched): array
    {
        return self::record($post_id, self::prepareSearched($searched));
    }

    /**
     * Check and build the entries of a `searched` list without writing: [module_id => entry].
     * A module named twice keeps its last entry, as the log keeps one per module.
     *
     * @throws AbilityInputException
     */
    public static function prepareSearched(array $searched): array
    {
        $entries = array();
        foreach (self::normalizeSearched($searched) as $row)
        {
            $entries[$row['module_id']] = self::entryFor($row['module_id'], $row['query'], $row['results']);
        }

        return $entries;
    }

    /**
     * Write prepared entries.
     *
     * @param array $entries module_id => DiscoveryLog entry (prepareSearched())
     * @return string[] the logged module ids (a keyword entry kept out by an identifier one is not)
     */
    public static function record(int $post_id, array $entries): array
    {
        $logged = array();
        foreach ($entries as $module_id => $entry)
        {
            if (DiscoveryLog::record($post_id, (string) $module_id, $entry))
            {
                $logged[] = (string) $module_id;
            }
        }

        return $logged;
    }

    /**
     * The shape of a `searched` list: at most MAX_SEARCHED rows of module_id,
     * query and results (an integer of 0 or more). Pure.
     *
     * @return array[] {module_id: string, query: string, results: int}
     * @throws AbilityInputException naming the row that is wrong
     */
    public static function normalizeSearched(array $searched): array
    {
        if (count($searched) > self::MAX_SEARCHED)
        {
            throw new AbilityInputException("'searched' takes at most " . self::MAX_SEARCHED . ' entries per call.');
        }

        $rows = array();
        foreach (array_values($searched) as $i => $row)
        {
            if (!is_array($row))
            {
                throw new AbilityInputException("searched[{$i}] must be an object {module_id, query, results}.");
            }

            $module_id = (isset($row['module_id']) && is_scalar($row['module_id'])) ? trim((string) $row['module_id']) : '';
            if ($module_id === '')
            {
                throw new AbilityInputException("searched[{$i}] needs a module_id.");
            }

            $query = (isset($row['query']) && is_scalar($row['query'])) ? trim((string) $row['query']) : '';
            if ($query === '')
            {
                throw new AbilityInputException("searched[{$i}] needs the query that was searched.");
            }

            $results = $row['results'] ?? null;
            if (is_string($results) && preg_match('/^\d+$/', trim($results)))
            {
                $results = (int) $results;
            }
            if (!is_int($results) || $results < 0)
            {
                throw new AbilityInputException("searched[{$i}].results must be the number of results the search found (0 or more).");
            }

            $rows[] = array('module_id' => $module_id, 'query' => $query, 'results' => $results);
        }

        return $rows;
    }

    /**
     * One discovery entry, with the mode of the query read from the module.
     *
     * @throws AbilityInputException when the module is not a loadable product module
     */
    private static function entryFor(string $module_id, string $query, int $results): array
    {
        $module = null;
        if (ModuleManager::getInstance()->moduleExists($module_id))
        {
            try
            {
                $module = ModuleManager::parserFactory($module_id);
            }
            catch (\Exception $e)
            {
                $module = null;
            }
        }

        // Activity-independent, as add-products-to-post checks it: a module that has since been switched off was still searched.
        if (!$module || $module->getParserType() !== ParserModule::PARSER_TYPE_PRODUCT)
        {
            throw new AbilityInputException(
                "Unknown product module '{$module_id}'. Call content-egg/list-modules for the module ids."
            );
        }

        return DiscoveryLog::entry(
            SearchMode::detect($query, $module, $module_id, $module->isFeedModule()),
            $query,
            $results,
            time()
        );
    }
}
