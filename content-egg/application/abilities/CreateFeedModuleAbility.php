<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\AffiliateFeedParserModule;
use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\components\ModuleName;
use ContentEgg\application\components\feed\FeedDetector;
use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\helpers\AdminHelper;

/**
 * CreateFeedModuleAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class CreateFeedModuleAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/create-feed-module';
    }

    public function label(): string
    {
        return __('Create Feed Module', 'content-egg');
    }

    public function description(): string
    {
        return 'Creates a new product feed module from a feed URL and schedules the first '
            . 'import. The feed format (CSV/XML/JSON), compression (none/zip/gz), encoding, '
            . 'CSV delimiter, currency, merchant domain, a display name (from the feed\'s '
            . 'advertiser/merchant or domain) and the XML product node are auto-detected from '
            . 'a sample of the feed, so you normally only need feed_url; pass any of the others '
            . 'to override a detection. The import runs '
            . 'asynchronously — poll content-egg/get-feed-status with the returned module_id '
            . 'until its state is "completed" (or "failed"). With auto_mapping "enabled" '
            . '(default) the plugin maps feed columns to product fields by column-name match, '
            . 'then falls back to AI mapping if an OpenAI key is configured; if required '
            . 'fields still cannot be mapped and no AI key is set, the call is rejected with '
            . 'the detected columns so you can pass an explicit "mapping" (product field => '
            . 'feed column) and retry. After a completed import the feed is searchable via '
            . 'content-egg/search-products.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => array('object', 'null'),
            'properties' => array(
                'feed_name' => array('type' => 'string', 'minLength' => 2, 'maxLength' => 60, 'description' => "Display name for the feed. Omit to auto-name it from the feed's advertiser/merchant (e.g. \"Andyanand US\") or its domain; only pass this when the user asks for a specific name."),
                'feed_url' => array('type' => 'string', 'description' => 'http(s) URL of the product feed file.'),
                'feed_format' => array('type' => 'string', 'enum' => array('csv', 'xml', 'json'), 'description' => 'Auto-detected from the feed when omitted.'),
                'archive_format' => array('type' => 'string', 'enum' => array('none', 'zip', 'gz'), 'description' => 'Compression. Auto-detected from the feed when omitted.'),
                'encoding' => array('type' => 'string', 'enum' => array('UTF-8', 'ISO-8859-1'), 'description' => 'Auto-detected from the feed when omitted.'),
                'currency' => array('type' => 'string', 'description' => 'Feed currency code, e.g. USD or EUR. Auto-detected from the feed when omitted.'),
                'domain' => array('type' => 'string', 'description' => 'Default merchant domain for the feed products. Auto-detected from the feed when omitted.'),
                'auto_mapping' => array('type' => 'string', 'enum' => array('enabled', 'disabled'), 'default' => 'enabled'),
                'mapping' => array(
                    'type' => 'object',
                    'description' => 'Explicit product-field => feed-column mapping (optional). '
                        . 'Keys are product fields (e.g. "title", "price", "affiliate link", '
                        . '"image link"); values are the matching feed column names.',
                    'additionalProperties' => array('type' => 'string'),
                ),
                'in_stock' => array('type' => 'boolean', 'default' => false, 'description' => 'Import only in-stock products.'),
            ),
            'required' => array('feed_url'),
            'additionalProperties' => false,
        );
    }

    public function outputSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'module_id' => array('type' => 'string'),
                'feed_name' => array('type' => 'string'),
                'import' => array('type' => 'string', 'enum' => array('scheduled')),
                'detected' => array(
                    'type' => 'object',
                    'description' => 'Feed parameters actually used (auto-detected unless you overrode them).',
                    'properties' => array(
                        'feed_format' => array('type' => 'string'),
                        'archive_format' => array('type' => 'string'),
                        'currency' => array('type' => 'string'),
                        'domain' => array('type' => 'string'),
                    ),
                ),
                'mapping' => array(
                    'type' => 'object',
                    'description' => 'Resolved product-field => feed-column mapping (explicit + column-name match).',
                ),
                'next' => array('type' => 'string'),
            ),
        );
    }

    public function annotations(): array
    {
        return array('readonly' => false, 'destructive' => false, 'idempotent' => false);
    }

    public function checkPermission($input = null): bool
    {
        return \current_user_can('manage_options');
    }

    public function execute(array $input): array
    {
        $feed_name = \sanitize_text_field((string) ($input['feed_name'] ?? ''));
        $feed_url = \esc_url_raw(trim((string) ($input['feed_url'] ?? '')));

        if (!preg_match('#^https?://#i', $feed_url))
        {
            throw new AbilityInputException("'feed_url' must be an http(s) URL.");
        }

        $module = AdminHelper::getAddNewFeedModule();
        if (!$module)
        {
            throw new AbilityInputException(
                'No free feed slot available (feed module limit reached). '
                    . 'Remove an unused feed module first.'
            );
        }

        $module_id = (string) $module->getId();

        if ($module->isActive())
        {
            throw new AbilityInputException(
                "Feed slot '{$module_id}' is unexpectedly configured. Retry the call; "
                    . 'if it persists, an administrator should review the feed modules list.'
            );
        }

        $config = ModuleManager::configFactory($module_id);

        // One detection pass over a feed sample yields the format, archive,
        // encoding, delimiters, currency, domain, XML product node AND the
        // deterministic column-name mapping — so the caller doesn't have to know
        // any of them (e.g. for a compressed .csv.gz feed). Best-effort: on a
        // fetch/parse failure it returns array() and we fall back to explicit
        // input and defaults.
        $analysis = $this->analyzeFeed($module, $config, $feed_url) ?: array();

        // Name the feed from its own data when the caller didn't pass one: the
        // detected advertiser/merchant name (e.g. "Andyanand US"), else the
        // merchant domain, else "Feed". De-duplicated against existing modules.
        if ($feed_name === '')
        {
            $detected_name = \sanitize_text_field((string) ($analysis['feed_name'] ?? ''));
            $feed_name = $detected_name !== '' ? $detected_name : 'Feed';
        }
        $feed_name = self::clampName($feed_name);
        $feed_name = self::uniqueFeedName($feed_name, $module_id);

        // Explicit input wins; otherwise adopt the detected value; else default.
        $feed_format = self::pickEnum($input['feed_format'] ?? null, array('csv', 'xml', 'json'), $analysis['format'] ?? null, 'csv');
        $archive_format = self::pickEnum($input['archive_format'] ?? null, array('none', 'zip', 'gz'), $analysis['archive_format'] ?? null, 'none');
        $encoding = self::pickEnum($input['encoding'] ?? null, array('UTF-8', 'ISO-8859-1'), $analysis['encoding'] ?? null, 'UTF-8');
        $xml_processor = self::pickEnum(null, array('XmlStringStreamer', 'XmlReader'), $analysis['xml_processor'] ?? null, 'XmlStringStreamer');
        $csv_delimiter = self::pickEnum(null, array('auto', 'tab', ';', ',', '|'), $analysis['csv_delimiter'] ?? null, 'auto');
        $csv_enclosure = self::pickEnum(null, array('auto', '"', "'", 'none'), $analysis['csv_enclosure'] ?? null, 'auto');
        $price_decimal_separator = self::pickEnum(null, array('auto', '.', ','), $analysis['price_decimal_separator'] ?? null, 'auto');

        $currency = strtoupper(\sanitize_text_field((string) ($input['currency'] ?? '')));
        if ($currency === '' && !empty($analysis['currency']))
        {
            $currency = strtoupper((string) $analysis['currency']);
        }
        if ($currency === '')
        {
            throw new AbilityInputException(
                'Could not detect the feed currency. Pass "currency" (e.g. USD, EUR, GBP).'
            );
        }

        $domain = \sanitize_text_field((string) ($input['domain'] ?? ''));
        if ($domain === '' && !empty($analysis['domain']))
        {
            $domain = \sanitize_text_field((string) $analysis['domain']);
        }

        $explicit = is_array($input['mapping'] ?? null) ? $input['mapping'] : array();
        if ($explicit)
        {
            // Same coercion the feed wizard applies: values become trimmed
            // sanitized strings (raw nested values would fatal the async
            // import's strpos() mapping checks).
            $explicit = (array) $config->mappingSanitize($explicit);
        }

        $auto_mapping = (string) ($input['auto_mapping'] ?? 'enabled');
        $mapping_fields = $config->mappingFields();

        // Deterministic (no-AI) column-name mapping from the same detection pass,
        // so a feed can import without an OpenAI key; explicit mapping wins.
        $prefill = is_array($analysis['mapping_prefill'] ?? null)
            ? (array) $config->mappingSanitize($analysis['mapping_prefill'])
            : array();
        $columns = is_array($analysis['feed_fields'] ?? null) ? $analysis['feed_fields'] : array();

        $resolved = self::mergeMapping($explicit, $prefill, $mapping_fields);
        $mapping = $resolved['mapping'];

        // Carry the detected XML product node the way the wizard does, so the
        // import parses the right repeating element (e.g. <item> in an RSS /
        // Google-Merchant-Center feed). Explicit mapping wins.
        if ($feed_format === 'xml' && empty($mapping['product node']) && !empty($analysis['product_node']))
        {
            $mapping['product node'] = \sanitize_text_field((string) $analysis['product_node']);
        }

        // No explicit mapping, deterministic prefill couldn't cover the required
        // fields, and there is no AI key to finish the job at import time: refuse
        // now with an actionable message instead of scheduling an import that
        // would only fail later with "OpenAI API key is not configured".
        if ($resolved['missing'] && $auto_mapping === 'enabled' && !self::hasAiKey())
        {
            $missing = implode(', ', $resolved['missing']);
            $cols = $columns ? ' Detected feed columns: ' . implode(', ', $columns) . '.' : '';
            throw new AbilityInputException(
                "Could not auto-map required feed fields: {$missing}. This site has no OpenAI API key "
                    . "for AI mapping.{$cols} Pass an explicit \"mapping\" (product field => feed column) "
                    . 'that covers the required fields, then call this ability again.'
            );
        }

        $values = array(
            'is_active' => 1,
            'feed_name' => $feed_name,
            'feed_url' => $feed_url,
            'feed_format' => $feed_format,
            'xml_processor' => $xml_processor,
            'archive_format' => $archive_format,
            'encoding' => $encoding,
            'currency' => $currency,
            'domain' => $domain,
            'csv_delimiter' => $csv_delimiter,
            'csv_enclosure' => $csv_enclosure,
            'price_decimal_separator' => $price_decimal_separator,
            'sync_interval' => '43200.',
            'in_stock' => !empty($input['in_stock']) ? 1 : 0,
            'auto_mapping' => $auto_mapping,
            'mapping' => $mapping,
        );

        \update_option($config->option_name(), $values);
        ModuleName::getInstance()->saveName($module_id, $feed_name);

        if ($module instanceof AffiliateFeedParserModule)
        {
            $module->requestForceRefresh();
            $module->refreshFeedData(true);
        }

        return array(
            'module_id' => $module_id,
            'feed_name' => $feed_name,
            'import' => 'scheduled',
            'detected' => array(
                'feed_format' => $feed_format,
                'archive_format' => $archive_format,
                'currency' => $currency,
                'domain' => $domain,
            ),
            'mapping' => $mapping,
            'next' => 'Poll content-egg/get-feed-status with module_id "' . $module_id
                . '" until the import state is "completed" (or "failed" — then check its error).',
        );
    }

    /**
     * Merge an explicit product-field => feed-column mapping over a deterministic
     * prefill (same orientation) and report which required product fields are
     * still unmapped. Explicit entries always win; empty values are dropped so
     * they neither persist nor count as "mapped". Pure — no WP, no I/O.
     *
     * @param array $explicit       Product field => feed column, caller-supplied.
     * @param array $prefill        Product field => feed column, from FeedDetector::heuristicMapping().
     * @param array $mapping_fields FeedConfig::mappingFields(): product field => bool(required).
     * @return array{mapping: array<string,string>, missing: string[]}
     */
    public static function mergeMapping(array $explicit, array $prefill, array $mapping_fields): array
    {
        // Explicit wins over prefill (later array in array_merge with string keys).
        $merged = array_merge($prefill, $explicit);

        $merged = array_filter($merged, static function ($v)
        {
            return is_string($v) ? trim($v) !== '' : !empty($v);
        });

        $missing = array();
        foreach ($mapping_fields as $field => $required)
        {
            if ($required && (!isset($merged[$field]) || trim((string) $merged[$field]) === ''))
            {
                $missing[] = (string) $field;
            }
        }

        return array('mapping' => $merged, 'missing' => $missing);
    }

    /** True when an OpenAI key is configured for AI-driven mapping. */
    private static function hasAiKey(): bool
    {
        return (bool) GeneralConfig::getOption('system_ai_key', '', 'contentegg_options');
    }

    /** Trim a feed name to the input schema's 60-char limit (multibyte-safe). */
    private static function clampName(string $name): string
    {
        $name = trim($name);

        return function_exists('mb_substr')
            ? trim(\mb_substr($name, 0, 60))
            : trim(substr($name, 0, 60));
    }

    /**
     * Suffix the name with " 2", " 3"… if another feed module already uses it,
     * mirroring the setup wizard so agent- and wizard-created feeds don't clash.
     */
    private static function uniqueFeedName(string $name, string $module_id): string
    {
        $names = \get_option(ModuleName::OPTION_NAME, array());
        if (!is_array($names))
        {
            $names = array();
        }
        unset($names[$module_id]);

        $candidate = $name;
        $suffix = 2;
        while (in_array($candidate, $names, true))
        {
            $candidate = $name . ' ' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * First valid value among the explicit input then the detected value, else
     * the default. Guards every persisted enum against a bad detection or a bad
     * caller value. Pure.
     */
    public static function pickEnum($explicit, array $allowed, $detected, string $default): string
    {
        foreach (array($explicit, $detected) as $candidate)
        {
            $c = is_scalar($candidate) ? (string) $candidate : '';
            if ($c !== '' && in_array($c, $allowed, true))
            {
                return $c;
            }
        }

        return $default;
    }

    /**
     * One best-effort detection pass over a feed sample — the same call the
     * setup wizard makes. Returns FeedDetector::analyze()'s result (format,
     * archive, encoding, delimiters, currency, domain, product node, feed_fields
     * and the deterministic mapping_prefill), or null when the feed can't be
     * fetched/parsed.
     *
     * @param AffiliateFeedParserModule $module
     */
    private function analyzeFeed($module, $config, string $feed_url): ?array
    {
        $on_zip = method_exists($module, 'storePrefetchedArchive')
            ? array($module, 'storePrefetchedArchive')
            : null;
        $on_ftp = method_exists($module, 'downloadViaFtp')
            ? function (string $ftp_url) use ($module)
            {
                $timeout = method_exists($module, 'importTimeLimit') ? $module->importTimeLimit() : 900;
                return $module->downloadViaFtp($ftp_url, $timeout);
            }
            : null;

        try
        {
            return FeedDetector::analyze(
                $feed_url,
                array_keys($config->mappingFields()),
                $on_zip,
                $on_ftp
            );
        }
        catch (\Throwable $e)
        {
            return null;
        }
    }
}
