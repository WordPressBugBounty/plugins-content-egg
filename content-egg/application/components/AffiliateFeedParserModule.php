<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

use ContentEgg\application\helpers\TemplateHelper;
use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\helpers\TextHelper;
use ContentEgg\application\Plugin;
use \ContentEgg\application\vendor\XmlStringStreamer\Parser\StringWalker;
use \ContentEgg\application\vendor\XmlStringStreamer\Stream\File;
use \ContentEgg\application\vendor\XmlStringStreamer\XmlStringStreamer;

use function ContentEgg\prn;
use function ContentEgg\prnx;

/**
 * AffiliateFeedParserModule abstract class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2025 keywordrush.com
 */
abstract class AffiliateFeedParserModule extends AffiliateParserModule
{
    const TRANSIENT_LAST_IMPORT_DATE = 'cegg_products_last_import_';
    const PRODUCTS_TTL = 43200;
    const MULTIPLE_INSERT_ROWS = 100;
    const IMPORT_TIME_LIMT = 600;
    const DATAFEED_DIR_NAME = 'cegg-datafeeds';
    const TRANSIENT_LAST_IMPORT_ERROR = 'cegg_last_import_error_';

    protected $rmdir;
    protected $product_model;
    protected $product_node;

    abstract public function getProductModel();

    abstract public function getFeedUrl();

    abstract protected function feedProductPrepare(array $data);

    public function __construct($module_id = null)
    {
        parent::__construct($module_id);
        $this->product_model = $this->getProductModel();

        // download feed in background
        \add_action('cegg_' . $this->getId() . '_init_products', array(get_called_class(), 'initProducts'), 10, 1);
    }

    public static function initProducts($module_id)
    {
        $m = ModuleManager::factory($module_id);

        try
        {
            $m->maybeImportProducts();
        }
        catch (\Exception $e)
        {
            $error = $e->getMessage();
            if (!strstr($error, 'Product import is in progress'))
            {
                $m->setLastImportError($error);
            }
        }
    }

    public function requirements()
    {
        $required_version = '5.6.4';
        $mysql_version = $this->product_model->getDb()->get_var('SELECT VERSION();');
        $errors = array();

        if (version_compare($required_version, $mysql_version, '>'))
        {
            $errors[] = sprintf('You are using MySQL %s. This module requires at least <strong>MySQL %s</strong>.', $mysql_version, $required_version);
        }

        return $errors;
    }

    public function isZippedFeed()
    {
        return false;
    }

    public function maybeCreateProductTable()
    {
        if (!$this->product_model->isTableExists())
        {
            $this->dbDelta();
        }
    }

    protected function dbDelta()
    {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $sql = $this->product_model->getDump();
        dbDelta($sql);
    }

    public function getLastImportDate()
    {
        return \get_transient(self::TRANSIENT_LAST_IMPORT_DATE . $this->getId());
    }

    public function getLastImportError()
    {
        return \get_transient(self::TRANSIENT_LAST_IMPORT_ERROR . $this->getId());
    }

    public function setLastImportDate($time = null)
    {
        if ($time === null)
            $time = time();

        \set_transient(self::TRANSIENT_LAST_IMPORT_DATE . $this->getId(), $time);
    }

    public function setLastImportError($error)
    {
        $error = TextHelper::truncate($error, 500);
        \set_transient(self::TRANSIENT_LAST_IMPORT_ERROR . $this->getId(), $error);
    }

    public function maybeImportProducts()
    {
        $last_export = $this->getLastImportDate();

        // product import is in progress?
        if ($last_export && $last_export < 0)
        {
            if (time() + $last_export > static::IMPORT_TIME_LIMT)
                $last_export = 0;
            else
                throw new \Exception('Product import is in progress. Try later.');
        }

        if ($this->isImportTime())
        {
            // remove shedule if exists
            $hook = 'cegg_' . $this->getId() . '_init_products';
            if (\wp_next_scheduled($hook, array('module_id' => $this->getId())))
            {
                \wp_unschedule_event(\wp_next_scheduled($hook, array('module_id' => $this->getId())), $hook, array('module_id' => $this->getId()));
            }

            $this->deleteTemporaryFiles();
            $this->setLastImportDate(time() * -1); // set in progress flag
            $this->maybeCreateProductTable();

            if (!$this->product_model->isTableExists())
                throw new \Exception(sprintf('Table %s does not exist', $this->product_model->tableName()));

            $this->importProducts($this->getFeedUrl());

            return true;
        }

        return false;
    }

    public function getProductsTtl()
    {
        $ttl = (int) \apply_filters('cegg_feed_products_ttl', self::PRODUCTS_TTL);
        $ttl = (int) \apply_filters('cegg_feed_products_module_ttl', $ttl, $this->getId());
        return $ttl;
    }

    public function isImportTime()
    {
        $last_import = $this->getLastImportDate();

        if (!$last_import)
            return true;

        if (\apply_filters('cegg_is_feed_import_time', false, $this->getId(), $last_import))
            return true;

        if (time() - $last_import > $this->getProductsTtl())
            return true;
        else
            return false;
    }

    public function importProducts($feed_url)
    {
        if (!defined('\WP_CLI') || !\WP_CLI)
            @set_time_limit(static::IMPORT_TIME_LIMT);

        \wp_raise_memory_limit();
        $this->setLastImportError('');
        register_shutdown_function(array($this, 'fatalHandler'));
        $this->product_model->truncateTable();
        $file = $this->downloadFeed($feed_url);

        $this->processFeed($file);
        $this->setLastImportDate();

        @unlink($file);
        if ($this->rmdir)
        {
            @rmdir($this->rmdir);
            $this->rmdir = null;
        }
    }

    /**
     * Download (and—if zipped—unzip) a feed in the most memory-efficient way.
     *
     * @param string $feed_url
     * @return string Absolute path to the downloaded (or extracted) file.
     * @throws Exception On failure to download or extract.
     */
    protected function downloadFeed(string $feed_url): string
    {
        if (! function_exists('download_url'))
        {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $tmp_file = \download_url($feed_url, 900);
        if (\is_wp_error($tmp_file))
        {
            $this->setLastImportDate(0);
            throw new \Exception(sprintf(
                'Failed to download feed URL: %s',
                $tmp_file->get_error_message()
            ));
        }

        // 3) If it’s not a ZIP, return the downloaded path
        if (! $this->isZippedFeed())
        {
            return $tmp_file;
        }

        // 4) ZIP → extract
        $dest_dir = trailingslashit($this->getDatafeedDir())
            . wp_unique_filename($this->getDatafeedDir(), basename($tmp_file) . '-unzipped-dir');

        $result = $this->unzipSingleFeed($tmp_file, $dest_dir);
        if (is_wp_error($result))
        {
            @unlink($tmp_file);
            $this->setLastImportDate(0);
            throw new \Exception(sprintf(
                'Unable to unzip feed archive: %s',
                $result->get_error_message()
            ));
        }

        // 5) Cleanup the original downloaded ZIP
        @unlink($tmp_file);

        // 6) Store for later cleanup, return the extracted file path
        $this->rmdir = $dest_dir;
        return $result;
    }

    /**
     * Safely and efficiently unzip a single feed file.
     *
     * @param string      $zip_path    Absolute path to the .zip file.
     * @param string      $dest_dir    Absolute path to the directory that should receive the file.
     * @param string|null $feed_inside Optional. Exact relative path (inside the ZIP) of the entry to extract.
     *                                 Leave null to auto-detect the first regular file.
     * @return string|\WP_Error Absolute path to the extracted feed file, or WP_Error on failure.
     */
    protected function unzipSingleFeed($zip_path, $dest_dir, $feed_inside = null)
    {
        if (! file_exists($zip_path) || ! is_readable($zip_path))
        {
            return new \WP_Error('zip_not_found', 'ZIP file does not exist or is not readable.');
        }
        if (! wp_mkdir_p($dest_dir))
        {
            return new \WP_Error('dest_dir_unwritable', 'Destination directory is not writable.', $dest_dir);
        }

        /** ------------------------------------------------------------------
         *  FAST PATH – use ZipArchive if available (streams, no memory spike)
         * ----------------------------------------------------------------- */
        if (class_exists('\ZipArchive'))
        {
            $zip = new \ZipArchive();
            $opened = $zip->open($zip_path, \ZipArchive::CHECKCONS);
            if (true !== $opened)
            {
                return new \WP_Error('zip_open_failed', 'Could not open ZIP archive.', $opened);
            }

            // 1. Decide which entry we will extract.
            if (empty($feed_inside))
            {
                for ($i = 0; $i < $zip->numFiles; $i++)
                {
                    $info = $zip->statIndex($i);
                    if (! $info || str_ends_with($info['name'], '/') || str_starts_with($info['name'], '__MACOSX/'))
                    {
                        continue;               // skip directories & Mac resource forks
                    }
                    if (0 !== validate_file($info['name']))
                    {
                        continue;               // invalid path ­→ skip
                    }
                    $feed_inside = $info['name'];
                    break;
                }
            }
            elseif (false === $zip->locateName($feed_inside, \ZipArchive::FL_NOCASE))
            {
                $zip->close();
                return new \WP_Error('entry_not_found', 'Requested file does not exist in the archive.', $feed_inside);
            }

            if (empty($feed_inside))
            {
                $zip->close();
                return new \WP_Error('no_valid_entry', 'No valid feed file found inside the archive.');
            }

            // 2. Extract just that entry.
            if (! $zip->extractTo($dest_dir, $feed_inside))
            {
                $zip->close();
                return new \WP_Error('extract_failed', 'Could not extract file from archive.', $feed_inside);
            }
            $zip->close();

            return trailingslashit($dest_dir) . basename($feed_inside);
        }

        /** ------------------------------------------------------------------
         *  FALLBACK – ZipArchive missing → use WordPress unzip_file()
         * ----------------------------------------------------------------- */

        if (! function_exists('unzip_file'))
        {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        if (! function_exists('WP_Filesystem'))
        {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        global $wp_filesystem;
        if (! $wp_filesystem || ! is_a($wp_filesystem, '\WP_Filesystem_Base'))
        {
            WP_Filesystem();
        }

        $result  = unzip_file($zip_path, $dest_dir);
        if (is_wp_error($result))
        {
            return $result; // propagate core error
        }

        // Locate the feed file we want inside the temporary extraction tree.
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dest_dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        $found = null;
        foreach ($iterator as $fileinfo)
        {
            if ($fileinfo->isDir())
            {
                continue;
            }
            if (
                empty($feed_inside) ||
                $fileinfo->getFilename() === basename($feed_inside) ||
                wp_normalize_path($fileinfo->getPathname()) === wp_normalize_path(trailingslashit($dest_dir) . $feed_inside)
            )
            {
                $found = $fileinfo->getPathname();
                break;
            }
        }
        if (! $found)
        {
            return new \WP_Error('entry_not_found', 'Requested file does not exist in the archive.', $feed_inside);
        }

        $dest_file = trailingslashit($dest_dir) . basename($found);

        if (! rename($found, $dest_file))
        {
            return new \WP_Error('move_failed', 'Could not move extracted file to destination.');
        }

        return $dest_file;
    }

    protected function processFeed(string $file): void
    {
        $format = strtolower(trim($this->config('feed_format', 'csv')));

        switch ($format)
        {
            case 'xml':
                $this->processFeedXml($file);
                break;

            case 'json':
                $this->processFeedJson($file);
                break;

            case 'csv':
                $this->processFeedCsv($file);
                break;

            default:
                throw new \InvalidArgumentException(sprintf(
                    'Unsupported feed format: %s',
                    esc_html($format)
                ));
        }
    }

    protected function processFeedCsv($file)
    {
        $encoding = $this->config('encoding', 'UTF-8');
        $csv_settings = $this->detectCsvSettings($file);
        $delimiter = $csv_settings['delimiter'];
        $enclosure = $csv_settings['enclosure'];
        $in_stock_only = $this->config('in_stock', false);

        $handle = fopen($file, 'r');

        if (!$handle)
        {
            $this->setLastImportError('Cannot open CSV file.');
            return;
        }

        $fields   = [];
        $products = [];

        $inserted = 0;

        $skipped = [
            'invalid_column_count' => 0,
            'exception'            => 0,
            'empty_product'        => 0,
            'out_of_stock'         => 0,
        ];

        while (($data = fgetcsv($handle, 0, $delimiter, $enclosure)) !== false)
        {
            $data = self::convertEncoding($data, $encoding);

            if (!$fields)
            {
                // first row → header
                $data   = str_replace("\xEF\xBB\xBF", '', $data);   // strip BOM
                $fields = array_map('trim', $data);
                continue;
            }

            $data = array_map(static fn($item) => trim((string)$item, " '"), $data);

            // ignore unnamed columns
            if (count($data) > count($fields))
            {
                $data = array_slice($data, 0, count($fields));
            }

            if (count($fields) !== count($data))
            {
                ++$skipped['invalid_column_count'];
                continue;
            }

            $data = array_combine($fields, $data);

            try
            {
                $product = $this->feedProductPrepare($data);
            }
            catch (\Exception $e)
            {
                if ($inserted > 0)
                {
                    ++$skipped['exception'];
                    continue;
                }

                $this->setLastImportError($e->getMessage());
                fclose($handle);
                return;
            }

            if (!$product)
            {
                ++$skipped['empty_product'];
                continue;
            }

            if (!empty($product['ean']))
            {
                $product['ean'] = TextHelper::fixEan($product['ean']);
            }

            if (
                $in_stock_only &&
                $product['stock_status'] == ContentProduct::STOCK_STATUS_OUT_OF_STOCK
            )
            {
                ++$skipped['out_of_stock'];
                continue;
            }

            $products[] = $product;
            ++$inserted;

            if ($inserted % static::MULTIPLE_INSERT_ROWS === 0)
            {
                $this->product_model->multipleInsert($products, static::MULTIPLE_INSERT_ROWS);
                $products = [];
            }
        }

        if ($products)
        {
            $this->product_model->multipleInsert($products, static::MULTIPLE_INSERT_ROWS);
        }

        // build warning about skipped products
        $skipped = array_filter($skipped);
        if (Plugin::isDevEnvironment() && $skipped)
        {
            $parts = [];
            foreach ($skipped as $reason => $count)
            {
                $parts[] = sprintf('%d %s', $count, str_replace('_', ' ', $reason));
            }
            $warning_skipped_products = 'Skipped products: ' . implode(', ', $parts);
            $this->setLastImportError($warning_skipped_products);
        }

        fclose($handle);
    }

    protected function processFeedXml($file)
    {
        $uniqueNode = $this->getProductNode($file, 'xml');

        if (!$uniqueNode)
        {
            $uniqueNode = 'product';
        }

        $streamer = \ContentEgg\application\vendor\XmlStringStreamer\XmlStringStreamer::createUniqueNodeParser($file, array('uniqueNode' => $uniqueNode));
        $in_stock_only = $this->config('in_stock', false);

        $i = 0;
        $products = array();

        libxml_use_internal_errors(true);

        $encoding = $this->config('encoding', 'UTF-8');

        while ($node_string = $streamer->getNode())
        {
            if ($encoding != 'UTF-8')
            {
                $node_string = iconv($encoding, 'UTF-8//TRANSLIT//IGNORE', $node_string);
            }

            $node = simplexml_load_string($node_string);
            if ($node === false)
            {
                $err_mess = 'Unable to load XML source.';
                if ($error = libxml_get_last_error())
                {
                    $err_mess .= $error->message;
                }

                $this->setLastImportError($err_mess);

                return;
            }

            $data = $this->mapXmlData($node);

            try
            {
                $product = $this->feedProductPrepare($data);
            }
            catch (\Exception $e)
            {
                if ($i > 0)
                {
                    continue;
                }

                $this->setLastImportError($e->getMessage());

                return;
            }

            if (!$product)
            {
                continue;
            }

            if (!empty($product['ean']))
            {
                $product['ean'] = TextHelper::fixEan($product['ean']);
            }

            if ($in_stock_only && $product['stock_status'] == ContentProduct::STOCK_STATUS_OUT_OF_STOCK)
            {
                continue;
            }

            $products[] = $product;
            $i++;
            if ($i % static::MULTIPLE_INSERT_ROWS == 0)
            {
                $this->product_model->multipleInsert($products, static::MULTIPLE_INSERT_ROWS);
                $products = array();
            }
        }

        if ($i == 0)
        {
            $this->setLastImportError('Product node not found in the feed.');
        }

        if ($products)
        {
            $this->product_model->multipleInsert($products, static::MULTIPLE_INSERT_ROWS);
        }
    }

    protected function processFeedJson($file)
    {
        $encoding = $this->config('encoding', 'UTF-8');
        $in_stock_only = $this->config('in_stock', false);

        $json = file_get_contents($file);
        $json_arr = json_decode($json, true);

        if (!$json_arr)
        {
            $this->setLastImportError(trim('Cannot decode JSON source. ' . json_last_error_msg()));
            return;
        }

        $node = $this->getProductNode($file, 'json');

        if (!$node && is_array($json_arr))
        {
            $node = 'products';
            $json_arr = array($node => $json_arr);
        }

        if (!isset($json_arr[$node]) || !is_array($json_arr[$node]))
        {
            $this->setLastImportError('The product node "' . \esc_html($node) . '" does not exist.');

            return;
        }

        $i = 0;
        foreach ($json_arr[$node] as $data)
        {
            if (!$data)
            {
                continue;
            }

            $data = self::convertEncoding($data, $encoding);

            try
            {
                $product = $this->feedProductPrepare($data);
            }
            catch (\Exception $e)
            {
                if ($i > 0)
                {
                    continue;
                }
                $this->setLastImportError($e->getMessage());

                return;
            }

            if (!$product)
            {
                continue;
            }

            if (!empty($product['ean']))
            {
                $product['ean'] = TextHelper::fixEan($product['ean']);
            }

            if ($in_stock_only && $product['stock_status'] == ContentProduct::STOCK_STATUS_OUT_OF_STOCK)
            {
                continue;
            }

            $products[] = $product;
            $i++;
            if ($i % static::MULTIPLE_INSERT_ROWS == 0)
            {
                $this->product_model->multipleInsert($products, static::MULTIPLE_INSERT_ROWS);
                $products = array();
            }
            $i++;
        }
        if ($products)
        {
            $this->product_model->multipleInsert($products, static::MULTIPLE_INSERT_ROWS);
        }
    }

    protected function mapXmlData(\SimpleXMLElement $node): array
    {
        $data       = [];
        $mapping    = $this->config('mapping', []);
        $fields     = array_values($mapping);

        $attributes = $node->attributes();
        $children   = get_object_vars($node);

        foreach ($fields as $field)
        {
            $value = $this->extractXmlField($node, $field, $attributes, $children);
            if ($value !== null)
            {
                $data[$field] = $value;
            }
        }

        return $data;
    }

    private function extractXmlField(
        \SimpleXMLElement $node,
        string $field,
        ?\SimpleXMLElement $attributes = null,
        array $children = []
    ): ?string
    {
        // 1) XPath if it's a path
        if (strpos($field, '/') !== false)
        {
            $result = $node->xpath($field);
            return $this->sanitizeXPathResult($result);
        }

        // 2) Attribute of the current node
        if ($attributes && isset($attributes[$field]))
        {
            return (string) $attributes[$field];
        }

        // 3) Direct child element
        if (isset($children[$field]))
        {
            return $this->sanitizeString((string) $children[$field]);
        }

        // 4) Fallback: maybe someone slipped in a non‐XPath, non‐direct name?
        $result = $node->xpath($field);
        return $this->sanitizeXPathResult($result);
    }

    private function sanitizeXPathResult($result): ?string
    {
        if (empty($result) || !isset($result[0]))
        {
            return null;
        }
        return $this->sanitizeString((string) $result[0]);
    }

    private function sanitizeString(string $input): string
    {
        return trim(\wp_strip_all_tags($input));
    }

    public function isImportInProgress()
    {
        $last_import = $this->getLastImportDate();

        if ($last_import && $last_import < 0)
        {
            return true;
        }

        return false;
    }

    public function isImportScheduled()
    {
        $hook = 'cegg_' . $this->getId() . '_init_products';
        if (\wp_next_scheduled($hook, array('module_id' => $this->getId())))
        {
            return true;
        }

        return false;
    }

    public function getLastImportDateReadable()
    {
        $last_import = $this->getLastImportDate();

        if (empty($last_import))
        {
            return '';
        }

        if ($last_import < 0)
        {
            return __('Product import is in progress', 'content-egg');
        }

        if (time() - $last_import <= 43200)
        {
            return sprintf(__('%s ago', '%s = human-readable time difference', 'content-egg'), \human_time_diff($last_import, time()));
        }

        return TemplateHelper::dateFormatFromGmt($last_import, true);
    }

    public function getProductCount()
    {
        if (!$this->product_model->isTableExists())
        {
            return 0;
        }

        return $this->product_model->count();
    }

    protected function getDatafeedDir()
    {
        $upload_dir = \wp_upload_dir();
        $datafeed_dir = $upload_dir['basedir'] . '/' . static::DATAFEED_DIR_NAME;

        if (is_dir($datafeed_dir))
        {
            return $datafeed_dir;
        }

        $files = array(
            array(
                'file' => 'index.html',
                'content' => '',
            ),
            array(
                'file' => '.htaccess',
                'content' => 'deny from all',
            ),
        );

        foreach ($files as $file)
        {
            if (\wp_mkdir_p($datafeed_dir) && !file_exists(trailingslashit($datafeed_dir) . $file['file']))
            {
                if ($file_handle = @fopen(trailingslashit($datafeed_dir) . $file['file'], 'w'))
                {
                    fwrite($file_handle, $file['content']);
                    fclose($file_handle);
                }
            }
        }

        if (!is_dir($datafeed_dir))
        {
            throw new \Exception('Can not create temporary directory for datafeed.');
        }

        return $datafeed_dir;
    }
    protected function detectCsvSettings($file)
    {
        $delimiters = [';' => 0, ',' => 0, "\t" => 0, '|' => 0];
        $enclosures = ['"' => 0, "'" => 0];

        $handle = fopen($file, "r");
        if (!$handle)
        {
            return ['delimiter' => ',', 'enclosure' => '"']; // fallback
        }

        $sampleLines = [];
        for ($i = 0; $i < 5 && !feof($handle); $i++)
        {
            $line = fgets($handle);
            if ($line !== false)
            {
                $sampleLines[] = $line;
            }
        }
        fclose($handle);

        // Evaluate delimiters
        foreach ($delimiters as $delimiter => &$count)
        {
            $totalFields = 0;
            foreach ($sampleLines as $line)
            {
                $fields = str_getcsv($line, $delimiter);
                $totalFields += count($fields);
            }
            $count = $totalFields;
        }

        $bestDelimiter = array_search(max($delimiters), $delimiters);

        // Evaluate enclosures
        foreach ($enclosures as $enclosure => &$count)
        {
            $totalMatches = 0;
            foreach ($sampleLines as $line)
            {
                $matches = substr_count($line, $enclosure);
                $totalMatches += $matches;
            }
            $count = $totalMatches;
        }

        // Prefer '"' if tied or not found
        $bestEnclosure = array_search(max($enclosures), $enclosures);
        if (!$bestEnclosure)
        {
            $bestEnclosure = '"';
        }

        return [
            'delimiter' => $bestDelimiter,
            'enclosure' => $bestEnclosure,
        ];
    }

    public function fatalHandler()
    {
        if (!$error = error_get_last())
        {
            return;
        }

        if (!isset($error['file']) || !strpos($error['file'], 'AffiliateFeedParserModule.php'))
        {
            return;
        }

        $message = $error['message'];
        if (strstr($message, 'Allowed memory size'))
        {
            $message .= '. ' . sprintf(__('Your data feed is too large and cannot be imported. Use a smaller feed or increase <a target="_blank" href="%s">WP_MAX_MEMORY_LIMIT</a>.', 'content-egg'), 'https://wordpress.org/support/article/editing-wp-config-php/#increasing-memory-allocated-to-php');
        }

        $this->setLastImportError($message);
    }

    public function deleteTemporaryFiles()
    {
        $dir = trailingslashit($this->getDatafeedDir());
        $parts = explode('/', $dir);
        if ($parts[count($parts) - 2] !== self::DATAFEED_DIR_NAME)
        {
            throw new \Exception('Unexpected error while cleaning temporary directory.');

            return;
        }

        $scanned = array_values(array_diff(scandir($dir), array('..', '.', 'index.html', '.htaccess')));
        if (!$scanned)
        {
            return;
        }

        global $wp_filesystem;
        if (!$wp_filesystem)
        {
            require_once(ABSPATH . '/wp-admin/includes/file.php');
            \WP_Filesystem();
        }

        foreach ($scanned as $s)
        {
            $path = $dir . $s;

            if (is_dir($path) && !preg_match('/-unzipped-dir$/', $path))
            {
                continue;
            }

            if (is_file($path) && pathinfo($path, PATHINFO_EXTENSION) !== 'csv')
            {
                continue;
            }

            if ($wp_filesystem->exists($path) && time() - filemtime($path) > 1200)
            {
                $wp_filesystem->delete($path, true);
            }
        }
    }

    public function getProductNode($file, $format)
    {
        $mapping = $this->config('mapping');
        if (!empty($mapping['product node']))
        {
            $this->product_node = $mapping['product node'];
        }
        elseif ($format == 'xml')
        {
            $this->product_node = $this->detectLikelyProductNode($file, $format);
        }

        return $this->product_node;
    }

    public static function extractShippingCost($shipping_cost)
    {
        $shipping_cost = \apply_filters('cegg_shipping_cost_value', $shipping_cost);

        if (strstr($shipping_cost, ':') && strstr($shipping_cost, ','))
        {
            $parts = explode(',', $shipping_cost);
            $shipping_cost = reset($parts);
        }
        elseif (strstr($shipping_cost, ':'))
        {
            $parts = explode(':', $shipping_cost);
            foreach ($parts as $p)
            {
                if (strstr($p, 'EUR') || strstr($p, 'USD'))
                {
                    $shipping_cost = $p;
                    break;
                }
            }
        }

        if ($shipping_cost == '')
            return '';

        return (float) TextHelper::parsePriceAmount($shipping_cost);
    }

    public function refreshFeedData($is_active)
    {
        $this->setLastImportDate(0);
        $this->setLastImportError('');

        $hook = 'cegg_' . $this->getId() . '_init_products';

        if ($is_active && !$this->isImportScheduled())
        {
            \wp_schedule_single_event(time() + 1, $hook, array('module_id' => $this->getId()));
        }

        if (!$is_active && $this->isImportScheduled())
        {
            \wp_clear_scheduled_hook($hook, array('module_id' => $this->getId()));
        }
    }

    public static function convertEncoding(array $data, string $encoding): array
    {
        array_walk_recursive($data, function (&$value) use ($encoding)
        {
            if (!is_string($value))
            {
                return;
            }
            $value = mb_convert_encoding(
                $value,
                'UTF-8',
                $encoding === 'ISO-8859-1' ? 'ISO-8859-1' : 'UTF-8'
            );
        });

        return $data;
    }

    function detectLikelyProductNode(string $filePath, string $format, int $sampleCount = 100): ?string
    {
        $stream = new File($filePath);
        $parser = new StringWalker();
        $streamer = new XmlStringStreamer($parser, $stream);

        $childNameCounts = [];

        while ($node = $streamer->getNode())
        {
            $xml = @simplexml_load_string($node);
            if (!$xml)
            {
                continue;
            }

            foreach ($xml->children() as $child)
            {
                $name = $child->getName();
                if (!isset($childNameCounts[$name]))
                {
                    $childNameCounts[$name] = 0;
                }
                $childNameCounts[$name]++;
            }

            // Stop early if we have enough data
            $totalSampled = array_sum($childNameCounts);
            if ($totalSampled >= $sampleCount)
            {
                break;
            }
        }

        if (empty($childNameCounts))
        {
            return null;
        }

        arsort($childNameCounts);
        return array_key_first($childNameCounts); // Most common tag name
    }
}
