<?php

namespace ContentEgg\application\components\feed;

defined('\ABSPATH') || exit;

use ContentEgg\application\helpers\CsvReader;
use ContentEgg\application\helpers\CsvSettingsDetector;
use ContentEgg\application\helpers\TextHelper;
use ContentEgg\application\vendor\XmlStringStreamer\XmlStringStreamer;

/**
 * FeedDetector class file
 *
 * Detects feed parameters (format, encoding, CSV settings, product node,
 * currency, merchant domain, decimal separator) from a downloaded sample and
 * prefills the field mapping heuristically. Pure helpers are static and
 * WordPress-free; analyze()/fetchSample() orchestrate via the WP HTTP API.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class FeedDetector
{
    const SAMPLE_BYTES = 524288; // 512 KiB

    /**
     * Known affiliate-network click domains that findOriginalUrl() cannot
     * unwrap; never report them as the merchant domain.
     */
    const NETWORK_HOSTS = array(
        'awin1.com',
        'tradedoubler.com',
        'webgains.com',
        'tradetracker.net',
        'shareasale.com',
        'anrdoezrs.net',
        'jdoqocy.com',
        'dpbolvw.net',
        'kqzyfj.com',
        'prf.hn',
        'go2cloud.org',
    );

    // ------------------------------------------------------------------
    // Pure detection helpers (no WordPress dependencies)
    // ------------------------------------------------------------------

    public static function detectArchive(string $head): string
    {
        if (strncmp($head, "\x1f\x8b", 2) === 0)
        {
            return 'gz';
        }
        if (strncmp($head, "PK\x03\x04", 4) === 0)
        {
            return 'zip';
        }

        return 'none';
    }

    public static function detectFormat(string $head): string
    {
        $head = preg_replace('/^\xEF\xBB\xBF/', '', $head);
        $head = ltrim($head);

        if ($head === '')
        {
            return 'csv';
        }

        if ($head[0] === '<')
        {
            return 'xml';
        }
        if ($head[0] === '{' || $head[0] === '[')
        {
            return 'json';
        }

        return 'csv';
    }

    public static function detectEncoding(string $head): string
    {
        if (strncmp($head, "\xEF\xBB\xBF", 3) === 0)
        {
            return 'UTF-8';
        }

        if (preg_match('/<\?xml[^>]*encoding=["\']([^"\']+)["\']/i', $head, $m))
        {
            $declared = strtolower(trim($m[1]));

            return in_array($declared, array('iso-8859-1', 'latin1', 'latin-1', 'iso8859-1'), true)
                ? 'ISO-8859-1'
                : 'UTF-8';
        }

        $detected = mb_detect_encoding($head, array('UTF-8', 'ISO-8859-1'), true);

        return $detected === 'UTF-8' || $detected === false ? 'UTF-8' : 'ISO-8859-1';
    }

    public static function detectDecimalSeparator(array $price_values): string
    {
        $votes = array('.' => 0, ',' => 0);

        foreach ($price_values as $value)
        {
            $value = trim((string) $value);
            if (!preg_match('/\d/', $value))
            {
                continue;
            }

            $last_dot = strrpos($value, '.');
            $last_comma = strrpos($value, ',');

            if ($last_dot !== false && $last_comma !== false)
            {
                $votes[$last_dot > $last_comma ? '.' : ',']++;
            }
            elseif ($last_comma !== false && preg_match('/,\d{1,2}\s*\D*$/', $value))
            {
                $votes[',']++;
            }
            elseif ($last_dot !== false && preg_match('/\.\d{1,2}\s*\D*$/', $value))
            {
                $votes['.']++;
            }
        }

        if ($votes['.'] === $votes[','])
        {
            return 'auto';
        }

        return $votes['.'] > $votes[','] ? '.' : ',';
    }

    public static function detectCurrency(array $records): string
    {
        $votes = array();

        foreach ($records as $record)
        {
            foreach ((array) $record as $key => $value)
            {
                $value = trim((string) $value);
                if (preg_match('/currency/i', (string) $key) && preg_match('/^[a-z]{3}$/i', $value))
                {
                    $code = strtoupper($value);
                    $votes[$code] = (isset($votes[$code]) ? $votes[$code] : 0) + 1;
                }
            }
        }

        if (!$votes)
        {
            $symbols = array('€' => 'EUR', '£' => 'GBP', '$' => 'USD');

            foreach ($records as $record)
            {
                foreach ((array) $record as $value)
                {
                    $value = trim((string) $value);
                    if ($value === '' || strlen($value) > 20 || !preg_match('/\d/', $value))
                    {
                        continue;
                    }

                    foreach ($symbols as $symbol => $code)
                    {
                        if (strpos($value, $symbol) !== false)
                        {
                            $votes[$code] = (isset($votes[$code]) ? $votes[$code] : 0) + 1;
                            continue 2;
                        }
                    }

                    if (preg_match('/\b(USD|EUR|GBP|CHF|SEK|NOK|DKK|PLN|CZK|AUD|CAD|NZD|JPY)\b/i', $value, $m))
                    {
                        $code = strtoupper($m[1]);
                        $votes[$code] = (isset($votes[$code]) ? $votes[$code] : 0) + 1;
                    }
                }
            }
        }

        if (!$votes)
        {
            return '';
        }

        arsort($votes);

        return (string) array_key_first($votes);
    }

    public static function detectDomain(array $records, string $feed_url): string
    {
        $votes = array();

        foreach ($records as $record)
        {
            foreach ((array) $record as $key => $value)
            {
                if (!preg_match('/link|url/i', (string) $key))
                {
                    continue;
                }

                $value = trim((string) $value);
                if (!filter_var($value, FILTER_VALIDATE_URL))
                {
                    continue;
                }

                $target = TextHelper::findOriginalUrl($value);
                if (!$target)
                {
                    $target = $value;
                }

                $host = self::normalizeHost((string) parse_url($target, PHP_URL_HOST));
                if ($host === '' || self::isNetworkHost($host))
                {
                    continue;
                }

                $votes[$host] = (isset($votes[$host]) ? $votes[$host] : 0) + 1;
            }
        }

        if ($votes)
        {
            arsort($votes);

            return (string) array_key_first($votes);
        }

        return self::normalizeHost((string) parse_url($feed_url, PHP_URL_HOST));
    }

    public static function suggestFeedName(string $domain): string
    {
        $host = strtolower(trim($domain));

        $stripped = true;
        while ($stripped)
        {
            $new = preg_replace('/^(www|shop|store|feed|feeds|data|datafeed|productdata|cdn|static|media|assets)\./', '', $host, 1);
            $stripped = ($new !== $host);
            $host = (string) $new;
        }

        $parts = explode('.', $host);
        $label = trim((string) $parts[0]);

        if ($label === '')
        {
            return 'Feed';
        }

        return ucfirst($label);
    }

    /**
     * Prefill the CE-field → feed-field mapping by normalized synonym match.
     * Keys of the result are the original CE field names (whatever unicode
     * quirks they carry); values are original feed field names.
     */
    public static function heuristicMapping(array $feed_fields, array $ce_fields): array
    {
        $normalized_feed = array();
        foreach ($feed_fields as $field)
        {
            $normalized_feed[(string) $field] = self::normalizeFieldName((string) $field);
        }

        $mapping = array();
        $used = array();

        // Priority order: unambiguous identity fields first.
        $priority = array(
            'id', 'title', 'price', 'sale price', 'affiliate link', 'image link',
            'description', 'currency', 'availability', 'is in stock', 'gtin',
            'brand', 'category', 'direct link', 'shipping cost',
            'additional image link', 'short description', 'subtitle', 'isbn',
        );

        $ce_by_normalized = array();
        foreach ($ce_fields as $ce_field)
        {
            $ce_by_normalized[self::normalizeFieldName((string) $ce_field)] = (string) $ce_field;
        }

        $synonyms = self::mappingSynonyms();

        foreach ($priority as $ce_name)
        {
            $ce_key = self::normalizeFieldName($ce_name);
            if (!isset($ce_by_normalized[$ce_key], $synonyms[$ce_key]))
            {
                continue;
            }

            $original_ce = $ce_by_normalized[$ce_key];

            // Pass 1: exact normalized match, in synonym priority order.
            foreach ($synonyms[$ce_key] as $synonym)
            {
                foreach ($normalized_feed as $original => $normalized)
                {
                    if ($normalized === $synonym && empty($used[$original]))
                    {
                        $mapping[$original_ce] = $original;
                        $used[$original] = true;
                        continue 3;
                    }
                }
            }

            // Pass 2: suffix match (paths and prefixes like item/id, g:id).
            foreach ($synonyms[$ce_key] as $synonym)
            {
                foreach ($normalized_feed as $original => $normalized)
                {
                    if (
                        strlen($normalized) > strlen($synonym)
                        && substr($normalized, -strlen($synonym)) === $synonym
                        && empty($used[$original])
                    )
                    {
                        $mapping[$original_ce] = $original;
                        $used[$original] = true;
                        continue 3;
                    }
                }
            }
        }

        return $mapping;
    }

    /** Values of price-like fields across sample records (for separator/currency detection). */
    public static function priceValues(array $records): array
    {
        $price_synonyms = array('price', 'searchprice', 'gprice', 'storeprice', 'currentprice', 'sellingprice', 'gsaleprice', 'saleprice', 'specialprice');
        $values = array();

        foreach ($records as $record)
        {
            foreach ((array) $record as $key => $value)
            {
                if (in_array(self::normalizeFieldName((string) $key), $price_synonyms, true))
                {
                    $values[] = (string) $value;
                }
            }
        }

        return $values;
    }

    // ------------------------------------------------------------------
    // Sample record parsing
    // ------------------------------------------------------------------

    public static function sampleRecords(string $file, string $format, array $settings = array(), int $limit = 10): array
    {
        switch ($format)
        {
            case 'csv':
                return self::sampleCsv($file, $settings, $limit);
            case 'json':
                return self::sampleJson($file, $settings, $limit);
            case 'xml':
                return self::sampleXml($file, $settings, $limit);
        }

        return array();
    }

    private static function sampleCsv(string $file, array $settings, int $limit): array
    {
        $handle = @fopen($file, 'r');
        if (!$handle)
        {
            return array();
        }

        $delimiter = isset($settings['csv_delimiter']) ? $settings['csv_delimiter'] : ',';
        if ($delimiter === 'tab')
        {
            $delimiter = "\t";
        }
        $enclosure = isset($settings['csv_enclosure']) ? $settings['csv_enclosure'] : '"';
        if ($enclosure === 'none')
        {
            $enclosure = "\0";
        }

        $reader = new CsvReader($handle, $delimiter, $enclosure, '\\');
        $fields = null;
        $rows = array();

        while (($data = $reader->readRow()) !== false)
        {
            if ($fields === null)
            {
                $data = str_replace("\xEF\xBB\xBF", '', $data);
                $fields = array_map('trim', $data);
                $reader->setExpectedColumns(count($fields));
                continue;
            }

            if (count($data) > count($fields))
            {
                $data = array_slice($data, 0, count($fields));
            }
            if (count($data) !== count($fields))
            {
                continue;
            }

            $rows[] = array_combine($fields, array_map(static function ($value)
            {
                return trim((string) $value);
            }, $data));

            if (count($rows) >= $limit)
            {
                break;
            }
        }

        fclose($handle);

        return $rows;
    }

    private static function sampleJson(string $file, array $settings, int $limit): array
    {
        $node = isset($settings['product_node']) && $settings['product_node'] !== ''
            ? (string) $settings['product_node']
            : null;

        $reader = new \ContentEgg\application\helpers\JsonStreamReader($file, $node);
        $rows = array();

        while (count($rows) < $limit && ($item = $reader->read()) !== null)
        {
            $row = array();
            foreach ($item as $key => $value)
            {
                if (is_scalar($value) || $value === null)
                {
                    $row[(string) $key] = (string) $value;
                }
                else
                {
                    $row[(string) $key] = (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
            $rows[] = $row;
        }

        $reader->close();

        return $rows;
    }

    private static function sampleXml(string $file, array $settings, int $limit): array
    {
        $node = isset($settings['product_node']) && $settings['product_node'] !== ''
            ? (string) $settings['product_node']
            : 'product';

        $ns_decls = self::extractNamespaceDecls($file);
        $rows = array();

        libxml_use_internal_errors(true);

        try
        {
            $streamer = XmlStringStreamer::createUniqueNodeParser($file, array('uniqueNode' => $node));

            while (count($rows) < $limit && ($node_xml = $streamer->getNode()))
            {
                $wrapped = '<ceggwrap ' . $ns_decls . '>' . $node_xml . '</ceggwrap>';

                $dom = new \DOMDocument();
                if (!@$dom->loadXML($wrapped, LIBXML_NONET | (defined('LIBXML_PARSEHUGE') ? LIBXML_PARSEHUGE : 0)))
                {
                    continue; // truncated / broken node in a partial sample
                }

                $product = null;
                foreach ($dom->documentElement->childNodes as $child)
                {
                    if ($child instanceof \DOMElement)
                    {
                        $product = $child;
                        break;
                    }
                }

                if (!$product)
                {
                    continue;
                }

                $row = array();
                self::flattenInto($product, '', $row);
                if ($row)
                {
                    $rows[] = $row;
                }
            }
        }
        catch (\Throwable $e)
        {
            // Partial samples can break the streamer mid-node: keep what we have.
        }

        return $rows;
    }

    /** First 64 KiB xmlns declarations, re-attached when parsing isolated nodes. */
    private static function extractNamespaceDecls(string $file): string
    {
        $head = (string) @file_get_contents($file, false, null, 0, 65536);

        if (preg_match_all('/xmlns(?::[A-Za-z0-9_.\-]+)?\s*=\s*("[^"]*"|\'[^\']*\')/', $head, $m))
        {
            return implode(' ', array_unique($m[0]));
        }

        return '';
    }

    private static function flattenInto(\DOMElement $el, string $prefix, array &$out): void
    {
        if ($el->hasAttributes())
        {
            foreach ($el->attributes as $attr)
            {
                $key = ($prefix !== '' ? $prefix . '/' : '') . '@' . $attr->nodeName;
                if (!isset($out[$key]))
                {
                    $out[$key] = (string) $attr->nodeValue;
                }
            }
        }

        foreach ($el->childNodes as $child)
        {
            if (!$child instanceof \DOMElement)
            {
                continue;
            }

            $path = ($prefix !== '' ? $prefix . '/' : '') . $child->nodeName;

            $has_element_children = false;
            foreach ($child->childNodes as $grand)
            {
                if ($grand instanceof \DOMElement)
                {
                    $has_element_children = true;
                    break;
                }
            }

            if ($has_element_children)
            {
                self::flattenInto($child, $path, $out);
            }
            else
            {
                if (!isset($out[$path]))
                {
                    $out[$path] = trim((string) $child->textContent);
                }

                if ($child->hasAttributes())
                {
                    foreach ($child->attributes as $attr)
                    {
                        $key = $path . '/@' . $attr->nodeName;
                        if (!isset($out[$key]))
                        {
                            $out[$key] = (string) $attr->nodeValue;
                        }
                    }
                }
            }
        }
    }

    // ------------------------------------------------------------------
    // Orchestration (WordPress HTTP API)
    // ------------------------------------------------------------------

    /**
     * Download a decompressed sample of the feed (up to SAMPLE_BYTES of
     * payload; ZIP archives require a full download).
     *
     * @param callable|null $onZipDownloaded Optional hook invoked with
     *   ($tmp_file, $url) after a ZIP archive has been downloaded and read.
     *   Return true to take ownership of $tmp_file (it will not be deleted
     *   here); used by the setup wizard to hand the archive off to the real
     *   import so it isn't downloaded a second time.
     * @return array{head:string, archive:string, complete:bool, bytes_total:int, warnings:array}
     * @throws \Exception when nothing usable could be downloaded.
     */
    public static function fetchSample(string $url, ?callable $onZipDownloaded = null): array
    {
        $result = array('head' => '', 'archive' => 'none', 'complete' => false, 'bytes_total' => 0, 'warnings' => array());

        $response = \wp_remote_get($url, array(
            'timeout' => 30,
            'redirection' => 3,
            'limit_response_size' => self::SAMPLE_BYTES,
            'headers' => array('Range' => 'bytes=0-' . (self::SAMPLE_BYTES - 1)),
        ));

        if (\is_wp_error($response))
        {
            throw new \Exception(sprintf(
                esc_html__('Could not download the feed: %s', 'content-egg'),
                esc_html($response->get_error_message())
            ));
        }

        $code = (int) \wp_remote_retrieve_response_code($response);
        if ($code !== 200 && $code !== 206)
        {
            throw new \Exception(sprintf(
                esc_html__('The feed URL responded with HTTP %d.', 'content-egg'),
                $code
            ));
        }

        $body = (string) \wp_remote_retrieve_body($response);
        if ($body === '')
        {
            throw new \Exception(esc_html__('The feed returned an empty response.', 'content-egg'));
        }

        if ($code === 206 && preg_match('#/(\d+)\s*$#', (string) \wp_remote_retrieve_header($response, 'content-range'), $m))
        {
            $result['bytes_total'] = (int) $m[1];
        }
        else
        {
            $result['bytes_total'] = (int) \wp_remote_retrieve_header($response, 'content-length');
        }

        $result['complete'] = $result['bytes_total'] > 0
            ? strlen($body) >= $result['bytes_total']
            : ($code === 200 && strlen($body) < self::SAMPLE_BYTES);

        $result['archive'] = self::detectArchive($body);

        if ($result['archive'] === 'gz')
        {
            $body = self::inflateSample($body);
        }
        elseif ($result['archive'] === 'zip')
        {
            $body = self::fetchZipSample($url, $result, $onZipDownloaded);
        }

        if (trim($body) === '')
        {
            throw new \Exception(esc_html__('Could not read any data from the feed sample.', 'content-egg'));
        }

        $result['head'] = $body;

        return $result;
    }

    /**
     * Full detection pass for the setup wizard.
     *
     * @param string        $url             Feed URL.
     * @param array         $ce_fields       Mapping field names from FeedConfig::mappingFields().
     * @param callable|null $onZipDownloaded See fetchSample().
     * @throws \Exception on download failure.
     */
    public static function analyze(string $url, array $ce_fields, ?callable $onZipDownloaded = null): array
    {
        $sample = self::fetchSample($url, $onZipDownloaded);
        $head = $sample['head'];
        $warnings = $sample['warnings'];

        $format = self::detectFormat($head);
        $encoding = self::detectEncoding($head);

        if (!function_exists('wp_tempnam'))
        {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $tmp = \wp_tempnam('cegg-feed-sample');
        file_put_contents($tmp, $head);

        $settings = array('csv_delimiter' => 'auto', 'csv_enclosure' => 'auto', 'product_node' => '');

        if ($format === 'csv')
        {
            $csv = (new CsvSettingsDetector())->detect($tmp);
            $settings['csv_delimiter'] = $csv['delimiter'];
            $settings['csv_enclosure'] = $csv['enclosure'];
        }
        elseif ($format === 'xml')
        {
            $node = self::detectProductNode($tmp);
            $settings['product_node'] = $node !== null ? $node : 'product';
        }

        $records = self::sampleRecords($tmp, $format, $settings, 10);

        $ai_sample = null;
        if ($records)
        {
            $ai_sample = $format === 'xml' ? self::firstXmlNode($tmp, $settings['product_node']) : $records[0];
        }
        else
        {
            $warnings[] = esc_html__('Could not parse sample products from the feed. Please verify the detected settings.', 'content-egg');
        }

        @unlink($tmp);

        $feed_fields = array();
        foreach ($records as $record)
        {
            foreach (array_keys($record) as $key)
            {
                if (!in_array($key, $feed_fields, true))
                {
                    $feed_fields[] = $key;
                }
            }
        }

        $currency = self::detectCurrency($records);
        $domain = self::detectDomain($records, $url);

        return array(
            'format' => $format,
            'archive_format' => $sample['archive'],
            'encoding' => $encoding,
            'csv_delimiter' => $settings['csv_delimiter'] === "\t" ? 'tab' : $settings['csv_delimiter'],
            'csv_enclosure' => $settings['csv_enclosure'],
            'product_node' => $settings['product_node'],
            'price_decimal_separator' => self::detectDecimalSeparator(self::priceValues($records)),
            'currency' => $currency !== '' ? $currency : 'USD',
            'currency_detected' => $currency !== '',
            'domain' => $domain,
            'feed_name' => self::suggestFeedName($domain),
            'feed_fields' => $feed_fields,
            'sample_records' => $records,
            'mapping_prefill' => self::heuristicMapping($feed_fields, $ce_fields),
            'ai_sample' => $ai_sample,
            'estimated_rows' => self::estimateRows($head, $format, $settings, $sample),
            'bytes_total' => $sample['bytes_total'],
            'complete' => $sample['complete'],
            'warnings' => $warnings,
        );
    }

    /**
     * Detect the most likely XML product node by counting direct children of
     * sampled top-level elements (static equivalent of the module detector).
     */
    public static function detectProductNode(string $file, int $sample_count = 100): ?string
    {
        libxml_use_internal_errors(true);

        try
        {
            $stream = new \ContentEgg\application\vendor\XmlStringStreamer\Stream\File($file);
            $parser = new \ContentEgg\application\vendor\XmlStringStreamer\Parser\StringWalker();
            $streamer = new XmlStringStreamer($parser, $stream);

            $counts = array();

            while ($node = $streamer->getNode())
            {
                $node = preg_replace('/^\xEF\xBB\xBF/', '', $node);
                $xml = @simplexml_load_string(
                    $node,
                    'SimpleXMLElement',
                    (defined('LIBXML_NONET') ? LIBXML_NONET : 0) | (defined('LIBXML_PARSEHUGE') ? LIBXML_PARSEHUGE : 0)
                );
                if (!$xml)
                {
                    continue;
                }

                foreach ($xml->children() as $child)
                {
                    $name = $child->getName();
                    $counts[$name] = (isset($counts[$name]) ? $counts[$name] : 0) + 1;
                }

                if (array_sum($counts) >= $sample_count)
                {
                    break;
                }
            }
        }
        catch (\Throwable $e)
        {
            return null;
        }

        if (!$counts)
        {
            return null;
        }

        arsort($counts);

        return (string) array_key_first($counts);
    }

    /** Raw XML string of the first product node (for AI mapping). */
    public static function firstXmlNode(string $file, string $node): ?string
    {
        libxml_use_internal_errors(true);

        try
        {
            $streamer = XmlStringStreamer::createUniqueNodeParser($file, array('uniqueNode' => $node ?: 'product'));
            $xml = $streamer->getNode();

            return $xml ? (string) $xml : null;
        }
        catch (\Throwable $e)
        {
            return null;
        }
    }

    private static function inflateSample(string $data): string
    {
        if (!function_exists('inflate_init'))
        {
            throw new \Exception(esc_html__('This server cannot decompress gzip feeds (zlib missing).', 'content-egg'));
        }

        $ctx = @inflate_init(ZLIB_ENCODING_GZIP);
        $out = $ctx ? @inflate_add($ctx, $data) : false;

        if ($out === false || $out === '')
        {
            throw new \Exception(esc_html__('Could not decompress the gzip feed sample.', 'content-egg'));
        }

        return $out;
    }

    /** ZIP central directory sits at EOF: a partial sample is useless, download fully. */
    private static function fetchZipSample(string $url, array &$result, ?callable $onZipDownloaded = null): string
    {
        if (!class_exists('\ZipArchive'))
        {
            throw new \Exception(esc_html__('This feed is a ZIP archive, but the ZipArchive PHP extension is not available on your server.', 'content-egg'));
        }

        if (!function_exists('download_url'))
        {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $tmp = \download_url($url, 300);
        if (\is_wp_error($tmp))
        {
            throw new \Exception(sprintf(
                esc_html__('Could not download the ZIP feed: %s', 'content-egg'),
                esc_html($tmp->get_error_message())
            ));
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmp) !== true)
        {
            @unlink($tmp);
            throw new \Exception(esc_html__('Could not open the ZIP feed archive.', 'content-egg'));
        }

        $body = '';
        for ($i = 0; $i < $zip->numFiles; $i++)
        {
            $info = $zip->statIndex($i);
            if (!$info || substr($info['name'], -1) === '/' || strpos($info['name'], '__MACOSX/') === 0)
            {
                continue;
            }

            $stream = $zip->getStream($info['name']);
            if ($stream)
            {
                $body = (string) stream_get_contents($stream, self::SAMPLE_BYTES * 4);
                fclose($stream);
                $result['bytes_total'] = (int) $info['size'];
                $result['complete'] = strlen($body) >= (int) $info['size'];
            }
            break;
        }

        $zip->close();

        $kept = $onZipDownloaded !== null && (bool) $onZipDownloaded($tmp, $url);
        if (!$kept)
        {
            @unlink($tmp);
        }

        return $body;
    }

    private static function estimateRows(string $head, string $format, array $settings, array $sample): int
    {
        if ($format === 'csv')
        {
            $in_head = max(0, substr_count($head, "\n") - 1);
        }
        elseif ($format === 'xml')
        {
            $node = $settings['product_node'] !== '' ? $settings['product_node'] : 'product';
            $in_head = substr_count($head, '</' . $node . '>') + substr_count($head, '<' . $node . ' ') - substr_count($head, '</' . $node . '>');
            $in_head = max($in_head, substr_count($head, '</' . $node . '>'));
        }
        else
        {
            $in_head = max(0, substr_count($head, '{') - 1);
        }

        if ($sample['complete'] || $sample['bytes_total'] <= 0 || strlen($head) === 0)
        {
            return (int) $in_head;
        }

        // Head is decompressed; bytes_total may be the compressed size. The
        // sample consumed at most SAMPLE_BYTES of the wire payload.
        $wire_sample = $sample['archive'] === 'gz' ? self::SAMPLE_BYTES : strlen($head);

        return (int) round($in_head * $sample['bytes_total'] / max(1, $wire_sample));
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private static function normalizeFieldName(string $name): string
    {
        // Strip zero-width/format characters (the CE image-link key carries them).
        $name = (string) preg_replace('/\p{Cf}/u', '', $name);

        return (string) preg_replace('/[^a-z0-9]/', '', strtolower($name));
    }

    private static function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));

        return (string) preg_replace('/^www\./', '', $host);
    }

    private static function isNetworkHost(string $host): bool
    {
        foreach (self::NETWORK_HOSTS as $network)
        {
            if ($host === $network || substr($host, -strlen('.' . $network)) === '.' . $network)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Synonyms per normalized CE field name, in match-priority order.
     * Feed field names are normalized the same way before comparison
     * (g:image_link → gimagelink, aw_deep_link → awdeeplink).
     */
    private static function mappingSynonyms(): array
    {
        return array(
            'id' => array('gid', 'awproductid', 'merchantproductid', 'productid', 'itemid', 'offerid', 'sku', 'productsku', 'articlenumber', 'identifier', 'asin', 'id'),
            'title' => array('gtitle', 'productname', 'producttitle', 'itemtitle', 'title', 'name'),
            'description' => array('gdescription', 'productdescription', 'longdescription', 'description', 'desc'),
            'affiliatelink' => array('awdeeplink', 'deeplink', 'deepurl', 'trackingurl', 'trackinglink', 'clickurl', 'clickouturl', 'affiliatelink', 'affiliateurl', 'afflink', 'glink', 'producturl', 'buyurl', 'url', 'link'),
            'imagelink' => array('gimagelink', 'merchantimageurl', 'awimageurl', 'imagelink', 'imageurl', 'mainimage', 'pictureurl', 'picture', 'imagelarge', 'imgurl', 'image', 'img'),
            'price' => array('searchprice', 'gprice', 'storeprice', 'currentprice', 'sellingprice', 'priceamount', 'price'),
            'saleprice' => array('gsaleprice', 'saleprice', 'specialprice', 'discountprice', 'offerprice', 'promoprice', 'dealprice'),
            'currency' => array('gcurrency', 'currencycode', 'pricecurrency', 'currency'),
            'availability' => array('gavailability', 'stockstatus', 'availabilitystatus', 'availability'),
            'isinstock' => array('isinstock', 'instock'),
            'gtin' => array('ggtin', 'gtin', 'eancode', 'ean', 'barcode', 'upc'),
            'brand' => array('gbrand', 'brandname', 'manufacturer', 'brand', 'vendor'),
            'category' => array('merchantcategory', 'gproducttype', 'categorypath', 'producttype', 'categoryname', 'category', 'googleproductcategory'),
            'directlink' => array('merchantdeeplink', 'directlink', 'directurl', 'merchanturl'),
            'shippingcost' => array('deliverycost', 'shippingcost', 'gshipping', 'shippingprice', 'shipping'),
            'additionalimagelink' => array('gadditionalimagelink', 'additionalimagelink', 'alternateimage', 'additionalimage', 'imageurl2'),
            'shortdescription' => array('shortdescription', 'summary'),
            'subtitle' => array('subtitle'),
            'isbn' => array('isbn'),
        );
    }
}
