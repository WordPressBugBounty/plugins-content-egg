<?php

namespace ContentEgg\application\modules\Feed;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\AffiliateFeedParserModule;
use ContentEgg\application\components\ModuleManager;

/**
 * CatalogSummary class file
 *
 * Public API: ContentEgg\application\modules\Feed\CatalogSummary::get($moduleId, $forceRefresh)
 * Builds a cached catalog snapshot (totals, price range, brands, clusters)
 * for any AffiliateFeedParserModule.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class CatalogSummary
{
    const MAX_CLUSTERS            = 15;
    const MAX_BRANDS              = 10;
    const MIN_CLUSTER_COUNT       = 5;
    const MIN_CATEGORIES_COVERAGE = 0.5;
    const MIN_DISTINCT_CATEGORIES = 3;
    const MAX_CLUSTER_NAME_LEN    = 120;
    const MAX_SAMPLE_TITLE_LEN    = 120;
    const MAX_SAMPLES_PER_CLUSTER  = 3;
    const SAMPLE_CANDIDATE_POOL    = 30;  // fetch this many, then dedup variants in PHP
    const SAMPLE_VARIANT_PREFIX_LEN = 40; // titles sharing this many leading chars are variants

    public static function get(string $moduleId): ?array
    {
        try
        {
            $module = ModuleManager::factory($moduleId);
        }
        catch (\Throwable $e)
        {
            return null;
        }

        if (!$module || !($module instanceof AffiliateFeedParserModule))
        {
            return null;
        }

        try
        {
            $summary = self::build($module);
        }
        catch (\Throwable $e)
        {
            error_log('[ContentEgg CatalogSummary] ' . $moduleId . ': ' . $e->getMessage());
            return null;
        }

        return $summary;
    }

    private static function build(AffiliateFeedParserModule $module): array
    {
        // First-time or cleared DB: import now so the summary reflects real data.
        // No-op when products already exist; throws when import is in progress.
        $module->importIfEmpty();

        $model = $module->getProductModel();

        if (!$model->isTableExists())
        {
            return self::emptySummary();
        }

        $db    = $model->getDb();
        $table = $model->tableName();

        $total = (int) $db->get_var("SELECT COUNT(*) FROM `{$table}`");

        if ($total === 0)
        {
            return self::emptySummary();
        }

        $price_range  = self::priceRange($db, $table, $module);
        $top_brands   = self::topBrands($db, $table);
        $cluster_data = self::clusters($db, $table, $total);

        return [
            'total_products' => $total,
            'price_range'    => $price_range,
            'clusters'       => $cluster_data['clusters'],
            'top_brands'     => $top_brands,
            'source'         => $cluster_data['source'],
        ];
    }

    private static function emptySummary(): array
    {
        return [
            'total_products' => 0,
            'price_range'    => ['min' => 0.0, 'max' => 0.0, 'currency' => ''],
            'clusters'       => [],
            'top_brands'     => [],
            'source'         => 'feed_categories',
        ];
    }

    private static function priceRange($db, string $table, AffiliateFeedParserModule $module): array
    {
        $row = $db->get_row("SELECT MIN(price) AS p_min, MAX(price) AS p_max FROM `{$table}` WHERE price > 0", ARRAY_A);

        $min = $row && $row['p_min'] !== null ? (float) $row['p_min'] : 0.0;
        $max = $row && $row['p_max'] !== null ? (float) $row['p_max'] : 0.0;

        return [
            'min'      => $min,
            'max'      => $max,
            'currency' => self::resolveCurrency($db, $table, $module),
        ];
    }

    private static function resolveCurrency($db, string $table, AffiliateFeedParserModule $module): string
    {
        $row = $db->get_row("SELECT product FROM `{$table}` LIMIT 1", ARRAY_A);
        if ($row && !empty($row['product']))
        {
            $blob = @unserialize($row['product']);
            if (is_array($blob))
            {
                // FeedModule stores raw feed rows; run the user mapping to get a canonical 'currency' key.
                if (method_exists($module, 'mapProduct'))
                {
                    $mapped = $module->mapProduct($blob);
                    if (is_array($mapped) && !empty($mapped['currency']))
                    {
                        $code = self::normalizeCurrency($mapped['currency']);
                        if ($code !== '')
                        {
                            return $code;
                        }
                    }
                }
                if (!empty($blob['currency']))
                {
                    $code = self::normalizeCurrency($blob['currency']);
                    if ($code !== '')
                    {
                        return $code;
                    }
                }
            }
        }

        return self::normalizeCurrency((string) $module->config('currency'));
    }

    private static function normalizeCurrency($value): string
    {
        $code = strtoupper(trim((string) $value));
        return preg_match('/^[A-Z]{3}$/', $code) ? $code : '';
    }

    private static function topBrands($db, string $table): array
    {
        // Case-folding done in PHP so we can return the most-frequent original casing per group.
        $rows = $db->get_results("SELECT brand, COUNT(*) AS cnt FROM `{$table}` WHERE brand <> '' GROUP BY brand", ARRAY_A);
        if (!$rows)
        {
            return [];
        }

        $groups = [];
        foreach ($rows as $r)
        {
            $name  = (string) $r['brand'];
            $count = (int) $r['cnt'];
            $key   = mb_strtolower($name);

            if (!isset($groups[$key]))
            {
                $groups[$key] = ['total' => 0, 'casings' => []];
            }
            $groups[$key]['total'] += $count;
            $groups[$key]['casings'][$name] = ($groups[$key]['casings'][$name] ?? 0) + $count;
        }

        uasort($groups, static function ($a, $b)
        {
            return $b['total'] <=> $a['total'];
        });

        $result = [];
        foreach ($groups as $g)
        {
            arsort($g['casings']);
            $result[] = [
                'name'  => (string) array_key_first($g['casings']),
                'count' => (int) $g['total'],
            ];
            if (count($result) >= self::MAX_BRANDS)
            {
                break;
            }
        }

        return $result;
    }

    private static function clusters($db, string $table, int $total): array
    {
        $candidates = $db->get_results(
            $db->prepare(
                "SELECT category, COUNT(*) AS cnt FROM `{$table}` WHERE category <> '' GROUP BY category HAVING cnt >= %d ORDER BY cnt DESC LIMIT %d",
                self::MIN_CLUSTER_COUNT,
                self::MAX_CLUSTERS
            ),
            ARRAY_A
        );

        $coverage       = (int) $db->get_var("SELECT COUNT(*) FROM `{$table}` WHERE category <> ''");
        $coverage_ratio = $total > 0 ? $coverage / $total : 0;

        $use_categories = $candidates
            && count($candidates) >= self::MIN_DISTINCT_CATEGORIES
            && $coverage_ratio >= self::MIN_CATEGORIES_COVERAGE;

        if ($use_categories)
        {
            return [
                'source'   => 'feed_categories',
                'clusters' => self::categoriesClusters($db, $table, $candidates),
            ];
        }

        return [
            'source'   => 'title_keywords',
            'clusters' => self::titleKeywordsClusters($db, $table),
        ];
    }

    private static function categoriesClusters($db, string $table, array $candidates): array
    {
        $clusters = [];
        foreach ($candidates as $c)
        {
            $name  = (string) $c['category'];
            $count = (int) $c['cnt'];

            $clusters[] = [
                'name'          => self::truncateLeft($name, self::MAX_CLUSTER_NAME_LEN),
                'product_count' => $count,
                'samples'       => self::categorySamples($db, $table, $name),
            ];
        }
        return $clusters;
    }

    private static function categorySamples($db, string $table, string $category): array
    {
        // Percentile sampling: pick one sample near each evenly-spaced price
        // target (25th / 50th / 75th percentile approximation). Samples from
        // different price tiers represent different products rather than colour
        // variants of the single median-priced SKU.
        $range = $db->get_row(
            $db->prepare(
                "SELECT MIN(price) AS p_min, MAX(price) AS p_max FROM `{$table}` WHERE category = %s AND price > 0",
                $category
            ),
            ARRAY_A
        );

        if ($range && $range['p_min'] !== null)
        {
            $priceMin = (float) $range['p_min'];
            $priceMax = (float) $range['p_max'];
            $samples  = [];
            $n        = self::MAX_SAMPLES_PER_CLUSTER;

            for ($i = 1; $i <= $n; $i++)
            {
                // e.g. for n=3: targets at 25%, 50%, 75% of the price range
                $target = $priceMax > $priceMin
                    ? $priceMin + ($priceMax - $priceMin) * ($i / ($n + 1))
                    : $priceMin;

                $sql = $db->prepare(
                    "SELECT title FROM `{$table}`
                     WHERE category = %s AND price > 0 AND title <> ''
                     ORDER BY ABS(price - %f) ASC
                     LIMIT %d",
                    $category,
                    $target,
                    self::SAMPLE_CANDIDATE_POOL
                );

                foreach ((array) $db->get_col($sql) as $t)
                {
                    $title = self::truncate((string) $t, self::MAX_SAMPLE_TITLE_LEN);
                    if (!self::isDuplicateVariant($title, $samples))
                    {
                        $samples[] = $title;
                        break;
                    }
                }
            }

            if (!empty($samples))
            {
                return $samples;
            }
        }

        // Fallback: no priced rows — pick distinct titles from the full pool.
        $samples = [];
        foreach ((array) $db->get_col(
            $db->prepare(
                "SELECT title FROM `{$table}` WHERE category = %s AND title <> '' LIMIT %d",
                $category,
                self::SAMPLE_CANDIDATE_POOL
            )
        ) as $t)
        {
            $title = self::truncate((string) $t, self::MAX_SAMPLE_TITLE_LEN);
            if (!self::isDuplicateVariant($title, $samples))
            {
                $samples[] = $title;
                if (count($samples) >= self::MAX_SAMPLES_PER_CLUSTER)
                {
                    break;
                }
            }
        }
        return $samples;
    }

    private static function titleKeywordsClusters($db, string $table): array
    {
        $titles = $db->get_col("SELECT title FROM `{$table}` WHERE title <> ''");
        if (!$titles)
        {
            return [];
        }

        $stopwords           = self::stopwords();
        $phrase_doc_counts   = []; // phrase => number of titles containing it
        $phrase_first_titles = []; // phrase => first N original titles seen, for samples

        foreach ($titles as $title)
        {
            $title_str = (string) $title;
            $tokens    = self::tokenize($title_str, $stopwords);
            if (!$tokens)
            {
                continue;
            }

            // Build distinct phrase set per title — count titles, not occurrences.
            $phrases = [];
            foreach ($tokens as $tok)
            {
                $phrases[$tok] = true;
            }
            $count = count($tokens);
            for ($i = 0; $i < $count - 1; $i++)
            {
                $phrases[$tokens[$i] . ' ' . $tokens[$i + 1]] = true;
            }

            foreach (array_keys($phrases) as $phrase)
            {
                $phrase_doc_counts[$phrase] = ($phrase_doc_counts[$phrase] ?? 0) + 1;
                if (!isset($phrase_first_titles[$phrase]))
                {
                    $phrase_first_titles[$phrase] = [];
                }
                if (count($phrase_first_titles[$phrase]) < self::MAX_SAMPLES_PER_CLUSTER
                    && !self::isDuplicateVariant($title_str, $phrase_first_titles[$phrase]))
                {
                    $phrase_first_titles[$phrase][] = $title_str;
                }
            }
        }

        $phrase_doc_counts = array_filter(
            $phrase_doc_counts,
            static fn($c) => $c >= self::MIN_CLUSTER_COUNT
        );

        if (!$phrase_doc_counts)
        {
            return [];
        }

        arsort($phrase_doc_counts);
        $phrase_doc_counts = array_slice($phrase_doc_counts, 0, self::MAX_CLUSTERS, true);

        $clusters = [];
        foreach ($phrase_doc_counts as $phrase => $count)
        {
            $samples = [];
            foreach (($phrase_first_titles[$phrase] ?? []) as $t)
            {
                $samples[] = self::truncate($t, self::MAX_SAMPLE_TITLE_LEN);
            }
            $clusters[] = [
                'name'          => self::truncateLeft((string) $phrase, self::MAX_CLUSTER_NAME_LEN),
                'product_count' => (int) $count,
                'samples'       => $samples,
            ];
        }

        return $clusters;
    }

    private static function tokenize(string $title, array $stopwords): array
    {
        $title = mb_strtolower($title);
        $title = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $title);
        $title = trim($title);
        if ($title === '')
        {
            return [];
        }

        $parts  = preg_split('/\s+/', $title);
        $tokens = [];
        foreach ($parts as $p)
        {
            if (mb_strlen($p) < 2 || isset($stopwords[$p]))
            {
                continue;
            }
            $tokens[] = $p;
        }
        return $tokens;
    }

    private static function stopwords(): array
    {
        static $cached = null;
        if ($cached !== null)
        {
            return $cached;
        }
        $list = [
            'a',
            'an',
            'and',
            'or',
            'but',
            'as',
            'at',
            'be',
            'by',
            'for',
            'from',
            'has',
            'have',
            'had',
            'he',
            'her',
            'his',
            'how',
            'in',
            'into',
            'is',
            'it',
            'its',
            'of',
            'on',
            'off',
            'out',
            'over',
            'per',
            'she',
            'so',
            'than',
            'that',
            'the',
            'their',
            'them',
            'then',
            'this',
            'to',
            'was',
            'we',
            'were',
            'what',
            'when',
            'where',
            'which',
            'who',
            'will',
            'with',
            'you',
            'your',
            'up',
            'down',
            'no',
            'not',
        ];
        $cached = array_flip($list);
        return $cached;
    }

    /**
     * Returns true when $title shares its leading SAMPLE_VARIANT_PREFIX_LEN
     * characters with any title already in $selected — i.e. it is a colour/size
     * variant of a product already chosen as a sample.
     */
    private static function isDuplicateVariant(string $title, array $selected): bool
    {
        $prefix = mb_substr($title, 0, self::SAMPLE_VARIANT_PREFIX_LEN);
        foreach ($selected as $existing)
        {
            if (mb_substr($existing, 0, self::SAMPLE_VARIANT_PREFIX_LEN) === $prefix)
            {
                return true;
            }
        }
        return false;
    }

    private static function truncate(string $s, int $max): string
    {
        if (mb_strlen($s) <= $max)
        {
            return $s;
        }
        return rtrim(mb_substr($s, 0, $max - 1)) . '…';
    }

    /**
     * Truncate from the left, keeping the rightmost (most specific) characters.
     * Used for hierarchical category paths so the leaf node is always preserved.
     */
    private static function truncateLeft(string $s, int $max): string
    {
        if (mb_strlen($s) <= $max)
        {
            return $s;
        }
        return '…' . ltrim(mb_substr($s, mb_strlen($s) - ($max - 1)));
    }
}
