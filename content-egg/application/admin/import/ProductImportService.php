<?php

namespace ContentEgg\application\admin\import;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\admin\import\PresetRepository;
use ContentEgg\application\admin\import\ImportLogger;
use ContentEgg\application\components\ContentManager;
use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\helpers\ProductHelper;
use ContentEgg\application\models\ImportQueueModel;
use ContentEgg\application\helpers\TextHelper;
use ContentEgg\application\components\ai\NullPrompt;
use ContentEgg\application\helpers\PostHelper;
use ContentEgg\application\helpers\WooHelper;
use ContentEgg\application\Plugin;
use ContentEgg\application\WooIntegrator;

use function ContentEgg\prnx;

/**
 * Class ProductImportService
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ProductImportService
{
    const PRODUCT_GROUP = 'ProductImport';

    /** @var ImportQueueModel */
    protected $queue;

    /** @var ImportLogger */
    protected $logger;

    /** @var int job start micro-time */
    protected $startTime = 0;

    /** @var int[] queue rows claimed by the batch currently being processed */
    protected $batchIds = [];

    protected $productPrompt;
    protected $postPrompt;
    protected $isSysAiEnabled = false;
    protected $isAiEnabled = false;

    public function __construct()
    {
        $this->queue  = ImportQueueModel::model();
        $this->logger = new ImportLogger();

        $lang = GeneralConfig::getInstance()->option('ai_language');
        $system_ai_key = GeneralConfig::getInstance()->option('system_ai_key');

        if ($system_ai_key)
        {
            $this->productPrompt = new ImportProductPrompt($system_ai_key, $lang);
            $this->isSysAiEnabled = true;
        }
        else
        {
            $this->productPrompt = new NullPrompt($system_ai_key, $lang);
            $this->isSysAiEnabled = false;
        }
    }

    /**
     * Runs one batch of import jobs.
     *
     * @return int jobs claimed and run by this batch, 0 if the queue had none
     */
    public function processBatch(int $limit = 3): int
    {
        // Prune old and excess rows from the import queue log
        if (mt_rand(1, 10) === 1)
        {
            ImportQueueModel::model()->pruneLogs();
        }

        // Rescue any truly stuck jobs back to 'pending'
        $this->queue->resetStuckJobs();

        $jobs = $this->queue->getNextBatch($limit);   // mark rows = 'working'
        if (!$jobs)
        {
            return 0;
        }

        $this->batchIds = array_map('intval', array_column($jobs, 'id'));

        /**
         * A batch of import jobs is about to run, in this process. Paired with
         * 'cegg_import_batch_end', which fires even if the batch dies, and with
         * 'cegg_import_queue_drained', which fires once when the queue empties.
         *
         * Importing one product writes the post more than once — the insert,
         * then WooCommerce's own save — so a cache plugin set to purge on
         * publish purges the whole site several times per product. These three
         * actions are the window in which to stop that. Suppress purging for
         * the length of the import and purge once at the end (substitute your
         * cache plugin's own callback and purge function):
         *
         *   add_action('cegg_import_batch_start', function () {
         *       remove_action('save_post', 'my_cache_purge_all');
         *   });
         *   add_action('cegg_import_queue_drained', 'my_cache_purge_all');
         *
         * The removal only applies to the process running the batch, so there
         * is nothing to restore. The trade-off is that the cache stays stale
         * for as long as the queue takes to empty — hours, on a large import.
         * To bound that, purge per batch instead: still one purge per batch
         * rather than one per write, but never more than a batch behind.
         *
         *   add_action('cegg_import_batch_start', function () {
         *       remove_action('save_post', 'my_cache_purge_all');
         *   });
         *   add_action('cegg_import_batch_end', 'my_cache_purge_all');
         *
         * @param int[] $batchIds queue row ids claimed by this batch
         */
        do_action('cegg_import_batch_start', $this->batchIds);

        try
        {
            $this->runJobs($jobs);
        }
        finally
        {
            /**
             * The batch is over, whether it completed or died part-way. Always
             * paired with 'cegg_import_batch_start' in the same process.
             *
             * @param int[] $batchIds queue row ids claimed by this batch
             */
            do_action('cegg_import_batch_end', $this->batchIds);

            $this->batchIds = [];
        }

        return count($jobs);
    }

    protected function runJobs(array $jobs): void
    {
        foreach ($jobs as $idx => $row)
        {
            $this->logger->reset();

            // Breathing room between jobs. Raise it to cap how much CPU the
            // queue takes on a busy or shared box, at the cost of throughput.
            if ($idx)
            {
                sleep(max(0, (int) apply_filters('cegg_import_job_pause', 1)));
            }

            // Jobs run one at a time but were all claimed at the same moment,
            // so the ones still waiting their turn are ageing towards the
            // stuck threshold without being stuck. Keep the whole batch alive.
            $this->heartbeat();

            try
            {
                $this->processJob($row);
            }
            catch (\Throwable $e)
            {
                // generic final safeguard
                $msg = $e->getMessage();

                if (Plugin::isDevEnvironment())
                {
                    $msg .= ' in ' . basename($e->getFile()) . ':' . $e->getLine();
                }
                else
                {
                    $msg = TextHelper::truncate($msg, 400);
                }

                $this->queue->markFailed($row['id'], $this->logger->format([
                    'exception' => $msg,
                ]), microtime(true) - $this->startTime);
            }
        }
    }

    /**
     * Reports that this run is still alive.
     *
     * resetStuckJobs() infers death from updated_at alone, and the batch lock
     * expires on a timer, so a long-but-healthy run has to say so — otherwise
     * the next cron tick reclaims its rows and processes them a second time
     * alongside it. Called between jobs and between AI calls, the two places
     * where a run can legitimately go quiet for minutes at a time.
     */
    protected function heartbeat(): void
    {
        if (!$this->batchIds)
        {
            return;
        }

        $this->queue->touch($this->batchIds);
        ProductImportScheduler::refreshLock();
    }

    /* --------------------------------------------------------------------
       Single-row processing
    -------------------------------------------------------------------- */
    protected function processJob(array $row): void
    {
        $this->startTime = microtime(true);
        $jobId   = (int) $row['id'];
        $presetId = (int) $row['preset_id'];

        /* --------------------------------------------------------------
           1. Load & validate preset
        -------------------------------------------------------------- */
        $presetPost = get_post($presetId);
        if (!$presetPost || $presetPost->post_status === 'trash')
        {
            throw new \RuntimeException(
                sprintf(
                    'Preset #%s does not exist.',
                    esc_html(sanitize_text_field($presetId))
                )
            );
        }

        $preset = PresetRepository::get($presetId);
        if (!$preset)
        {
            throw new \RuntimeException(
                sprintf(
                    'Preset meta missing for #%s',
                    esc_html(sanitize_text_field($presetId))
                )
            );
        }

        /* --------------------------------------------------------------
           2. Obtain product data
        -------------------------------------------------------------- */
        $product = null;

        if (!empty($row['payload']))
        {
            // preferred: payload was stored at enqueue time
            $product = json_decode($row['payload'], true);
        }

        if (!$product)
        {
            // fallback: fetch on-the-fly via module & keyword
            $product = $this->fetchProductDynamically($row);
            if (!$product)
            {
                throw new \RuntimeException(
                    sprintf(
                        esc_html__('No products found for keyword "%s".', 'content-egg'),
                        esc_html($row['keyword'] ?? 'unknown')
                    )
                );
            }

            // store the fetched product data in the queue payload
            $queue = ImportQueueModel::model();
            $queue->updatePayload($jobId, $product);

            $this->logger->notice(sprintf(
                __('Fetched product: "%s".', 'content-egg'),
                TextHelper::truncate($product['title'], 60) ?? 'unknown'
            ));
        }

        // If a post/product already exists with this unique_id
        if (!empty($preset['avoid_duplicates']))
        {
            $unique_id = isset($product['unique_id'])
                ? sanitize_text_field($product['unique_id'])
                : '';

            if ('' !== $unique_id)
            {
                $existing_id = PostHelper::getPostIdByUniqueId($unique_id);
                if ($existing_id)
                {
                    $msg = sprintf(
                        __('Duplicate product found (Post #%d).', 'content-egg'),
                        $existing_id
                    );
                    $this->queue->markFailed(
                        $row['id'],
                        $this->logger->format(['exception' => $msg]),
                        microtime(true) - $this->startTime
                    );
                    return;
                }
            }
        }

        // Duplicate checks by GTIN/EAN (WooCommerce products only)
        if (! empty($preset['post_type']) && $preset['post_type'] === 'product' && ! empty($preset['avoid_duplicates_gtin']))
        {
            $gtin_raw = '';
            if (! empty($product['gtin']))
            {
                $gtin_raw = (string) $product['gtin'];
            }
            elseif (! empty($product['ean']))
            {
                $gtin_raw = (string) $product['ean'];
            }

            if ($gtin_raw !== '')
            {
                $gtin_sanitized  = sanitize_text_field($gtin_raw);
                $gtin_normalized = apply_filters('cegg_wc_normalize_gtin', $gtin_sanitized);

                if ($gtin_normalized !== '')
                {
                    $existing_post_id = WooHelper::getProductIdByGtin($gtin_normalized);
                    if ($existing_post_id)
                    {
                        $msg = sprintf(
                            __('Duplicate product found by GTIN/EAN (Post #%1$d, EAN: %2$s).', 'content-egg'),
                            absint($existing_post_id),
                            $gtin_sanitized
                        );

                        $this->queue->markFailed(
                            $row['id'],
                            $this->logger->format(['exception' => $msg]),
                            microtime(true) - $this->startTime
                        );
                        return;
                    }
                }
            }
        }

        /* --------------------------------------------------------------
           2.1 Fetch price comparison products
        -------------------------------------------------------------- */
        $comparison_product_data = [];
        if (!empty($preset['price_comparison']) && $preset['price_comparison'] === 'enabled')
        {
            $max     = (int) apply_filters('cegg_import_price_comparison_max_products', 5);
            $keyword = isset($row['keyword']) ? $row['keyword'] : '';
            $ean = TextHelper::isEan($keyword) ? $keyword : '';

            $comparison_product_data = $this->findPriceComparisonProducts($product, $max, $ean);

            if (!empty($comparison_product_data))
            {
                $moduleNames = ModuleManager::getInstance()->getModuleNamesByIds(array_keys($comparison_product_data));

                $totalComparisonProducts = 0;
                foreach ($comparison_product_data as $moduleId => $products)
                {
                    $totalComparisonProducts += count($products);
                }

                $this->logger->notice(
                    sprintf(
                        __('Found %d price comparison products in the modules: %s.', 'content-egg'),
                        $totalComparisonProducts,
                        implode(', ', $moduleNames)
                    )
                );
            }
        }

        /* --------------------------------------------------------------
           3. Create or update WP object
        -------------------------------------------------------------- */
        $createdPostId = $this->createPostFromPreset($preset, $product, $row, $comparison_product_data);

        /* --------------------------------------------------------------
            4. Map bridge page
        -------------------------------------------------------------- */
        if (!empty($row['source_post_id']))
        {
            $target_post_id = (int) $createdPostId;
            $source_post_id = (int) $row['source_post_id'];
            $module_id      = isset($row['module_id']) ? (string) $row['module_id'] : '';
            $unique_id      = isset($product['unique_id']) ? (string) $product['unique_id'] : '';

            if ($target_post_id > 0 && $module_id !== '' && $unique_id !== '')
            {
                $map = \ContentEgg\application\models\ProductMapModel::model();

                $makeCanonical = !empty($preset['make_canonical']) || !empty($row['make_canonical']);

                if ($makeCanonical)
                {
                    // only canonical mapping
                    try
                    {
                        $map->setCanonical($module_id, $unique_id, $target_post_id);
                        $this->logger->notice(__('Canonical bridge mapping created.', 'content-egg'));
                    }
                    catch (\Throwable $e)
                    {
                        $this->logger->notice(
                            sprintf(__('Failed to set canonical bridge mapping. Error: %s', 'content-egg'), $e->getMessage())
                        );
                    }
                }
                else
                {
                    // only per-post mapping
                    try
                    {
                        $map->upsertMapping($module_id, $unique_id, $source_post_id, $target_post_id);
                        $this->logger->notice(__('Per-post bridge mapping created.', 'content-egg'));
                    }
                    catch (\Throwable $e)
                    {
                        $this->logger->notice(
                            sprintf(__('Failed to create bridge mapping. Error: %s', 'content-egg'), $e->getMessage())
                        );
                    }
                }
            }
        }

        /* --------------------------------------------------------------
           5. Update queue row – SUCCESS
        -------------------------------------------------------------- */
        $this->queue->markDone($jobId, $createdPostId, $this->logger->format([
            'post_id'    => $createdPostId,
            'preset_id'  => $presetId,
            'module_id'  => $row['module_id'],
        ]), microtime(true) - $this->startTime);
    }

    /* --------------------------------------------------------------------
       Fetch product live if no payload stored
    -------------------------------------------------------------------- */
    protected function fetchProductDynamically(array $row): ?array
    {
        $moduleId = $row['module_id'];
        $keyword  = $row['keyword'] ?? '';

        if (!$moduleId)
        {
            throw new \RuntimeException(
                esc_html__('No module ID provided.', 'content-egg')
            );
        }

        if (!$keyword)
        {
            throw new \RuntimeException(
                esc_html__('No keyword provided.', 'content-egg')
            );
        }

        $settings = ['entries_per_page' => 1];

        try
        {
            $parser = ModuleManager::getInstance()->parserFactory($moduleId);
            $parser->getConfigInstance()->applyCustomOptions($settings);
            $data = $parser->doMultipleRequests($keyword);
            $data = ContentManager::dataPresavePrepare($data, $moduleId, $post_id = 0);
        }
        catch (\Exception $e)
        {
            $this->logger->notice(sprintf(
                __('Module error "%s": %s', 'content-egg'),
                ModuleManager::getInstance()->getModuleNameById($moduleId),
                TextHelper::truncate($e->getMessage(), 250)
            ));
            return [];
        }

        if ($data)
            return reset($data);
        else
            return [];
    }

    public function findPriceComparisonProducts(array $product, $max = 3, $ean = '')
    {
        if (empty($product['module_id']) || empty($product['unique_id']))
        {
            return [];
        }

        $current_module_id = $product['module_id'];
        $current_unique_id = $product['unique_id'];

        $modules_settings = [
            'Amazon' => [
                'results' => 1,
                'is_ean_search' => true,
                'is_url_search' => false,
            ],
            'AmazonNoApi' => [
                'results' => 1,
                'is_ean_search' => true,
                'is_url_search' => false,
            ],
            'Bestbuy' => [
                'results' => 1,
                'is_ean_search' => true,
                'is_url_search' => false,
            ],
            'Bolcom' => [
                'results' => 1,
                'is_ean_search' => true,
                'is_url_search' => false,
            ],
            'CjProducts' => [
                'results' => $max,
                'is_ean_search' => true,
                'is_url_search' => false,
            ],
            'Ebay2' => [
                'results' => $max,
                'is_ean_search' => true,
                'is_url_search' => false,
            ],
            'Kelkoo' => [
                'results' => $max,
                'is_ean_search' => true,
                'is_url_search' => false,
            ],
            'Kieskeurignl' => [
                'results' => $max,
                'is_ean_search' => true,
                'is_url_search' => false,
            ],
            'Viglink' => [
                'results' => $max,
                'is_ean_search' => true,
                'is_url_search' => true,
            ],
            'TradedoublerProducts' => [
                'results' => $max,
                'is_ean_search' => true,
                'is_url_search' => false,
            ],
            'TradetrackerProducts' => [
                'results' => $max,
                'is_ean_search' => true,
                'is_url_search' => false,
            ],
            'Walmart' => [
                'results' => 1,
                'is_ean_search' => true,
                'is_url_search' => false,
            ],
            'Webgains' => [
                'results' => 1,
                'is_ean_search' => true,
                'is_url_search' => false,
            ],
        ];

        $active_modules = ModuleManager::getInstance()->getAffiliateParsers(true, true);
        $modules_settings = array_intersect_key($modules_settings, $active_modules);

        foreach ($modules_settings as $module_id => $settings)
        {
            if ($module_id == $current_module_id && $settings['results'] == 1)
            {
                unset($modules_settings[$module_id]);
                continue;
            }
            elseif ($module_id == $current_module_id && $settings['results'] > 1)
            {
                $modules_settings[$module_id]['results'] = $settings['results'] + 1;
            }

            if (strstr($module_id, 'Amazon') && strstr($current_module_id, 'Amazon'))
            {
                unset($modules_settings[$module_id]);
                continue;
            }

            $modules_settings[$module_id]['priority'] = ModuleManager::getInstance()->getModulePriority($module_id);
        }

        // Add active feed modules
        $feed_modules = ModuleManager::getInstance()->getActiveFeedModules();
        foreach ($feed_modules as $module_id => $feed_module)
        {
            $modules_settings[$module_id] = [
                'results' => $max,
                'is_ean_search' => true,
                'is_url_search' => false,
                'priority' => $feed_module->config('module_priority'),
            ];
        }

        // Sorting by priority
        uasort($modules_settings, function ($a, $b)
        {
            return $a['priority'] <=> $b['priority'];
        });

        $amazon_product_found = false;
        $products = [];

        foreach ($modules_settings as $module_id => $settings)
        {
            if ($amazon_product_found && strstr($module_id, 'Amazon'))
            {
                continue;
            }

            if ($ean && $settings['is_ean_search'])
            {
                $keyword = $ean;
            }
            elseif ($product['ean'] && $settings['is_ean_search'] && self::isValidComparisonEan($product['ean'], $product))
            {
                $keyword = $product['ean'];
            }
            elseif ($product['orig_url'] && $settings['is_url_search'])
            {
                $keyword = $product['orig_url'];
            }
            else
                continue;

            if ($module_id == 'Amazon' && !Plugin::isDevEnvironment())
            {
                sleep(1);
            }

            $max_per_module = $settings['results'] ?? 1;
            $max_per_module = \apply_filters('cegg_import_price_comparison_max_per_module', $max_per_module, $module_id, $keyword);
            $settings = ['entries_per_page' => $max_per_module];

            try
            {
                $parser = ModuleManager::getInstance()->parserFactory($module_id);
                $parser->getConfigInstance()->applyCustomOptions($settings);
                $data = $parser->doMultipleRequests($keyword);
            }
            catch (\Exception $e)
            {
                $this->logger->notice(sprintf(
                    __('Module error "%s": %s', 'content-egg'),
                    $module_id,
                    TextHelper::truncate($e->getMessage(), 150)
                ));
                continue;
            }

            if (!is_array($data) || empty($data))
            {
                continue;
            }

            $products = array_merge($products, $data);

            // Use only one amazon module
            if (strstr($module_id, 'Amazon'))
            {
                $amazon_product_found = true;
            }

            if (count($products) >= $max)
                break;
        }

        $results = array_slice($products, 0, $max);

        // reformat
        $module_data = [];
        foreach ($results as $product)
        {
            if ($product->module_id == $current_module_id && $product->unique_id == $current_unique_id)
            {
                continue;
            }

            if (!isset($module_data[$product->module_id]))
            {
                $module_data[$product->module_id] = [];
            }

            $module_data[$product->module_id][] = $product;
        }

        return $module_data;
    }

    /**
     * Whether $ean (the source product's own EAN, as opposed to the
     * search-row keyword) is safe to use as a price-comparison search
     * keyword. Rejects non-EAN text (e.g. invalid feed data) so it can't be
     * sent to comparison modules as a de-facto keyword search.
     */
    private static function isValidComparisonEan($ean, array $product)
    {
        return (bool) \apply_filters('cegg_import_price_comparison_valid_ean', TextHelper::isEan($ean), $ean, $product);
    }

    /* --------------------------------------------------------------------
       Build post/product according to preset meta
    -------------------------------------------------------------------- */
    protected function createPostFromPreset(array $preset, array $product, array $row, array $comparison_product_data = []): int
    {
        if (
            Plugin::isFree()
            && isset($preset['title'])
            && substr_compare($preset['title'], '/Pro', -4, 4) === 0
        )
        {
            throw new \RuntimeException(
                esc_html__('AI content generation presets require the Pro version. Please upgrade to Content Egg Pro to use this preset.', 'content-egg')
            );
        }

        $isWoo = ($preset['post_type'] ?? 'post') === 'product';

        if ($isWoo && !\ContentEgg\application\helpers\WooHelper::isWooActive())
        {
            throw new \RuntimeException(
                esc_html__('WooCommerce is not active. Please install and activate WooCommerce plugin.', 'content-egg')
            );
        }

        $sourceProduct = $product;

        $ai = [
            'AI.title' => '',
            'AI.content' => '',
            'AI.short_desc' => '',
        ];

        // ---------- 0.1 AI product processing ----------
        if (! empty($preset['ai_product_content']))
        {
            if (! $this->isSysAiEnabled)
            {
                throw new \RuntimeException(
                    esc_html__('OpenAI integration is not enabled. Please add your OpenAI API key under Content Egg → Settings → AI → OpenAI API Key.', 'content-egg')
                );
            }

            $ai_product_content = $preset['ai_product_content'];

            // Strip the "generate_" prefix:
            $gen_fields = array_map(
                fn(string $gen): string => preg_replace('/^generate_/', '', $gen),
                $ai_product_content
            );

            $product = $this->productPrompt->craftProductData($product, $gen_fields);

            $this->heartbeat();

            $this->logger->notice(sprintf(
                esc_html__('AI product data generated: %s.', 'content-egg'),
                join(', ', $gen_fields)
            ));
        }

        // ---------- 0.2 AI-Powered Post Content ----------
        // Prompts reached only through a %AI.<name>% placeholder — in a template
        // or a custom meta field — count too. Without them in the condition a
        // preset that uses nothing but placeholders would skip this block
        // entirely and every placeholder would resolve to an empty string.
        $referencedPrompts = PresetNormalizer::collectReferencedNames($preset);

        if (Plugin::isPro() && (! empty($preset['ai_title']) || ! empty($preset['ai_content']) || ! empty($preset['ai_short_desc']) || $referencedPrompts))
        {
            if (!(bool)GeneralConfig::getInstance()->option('ai_key'))
            {
                throw new \RuntimeException(
                    esc_html__('AI integration is not enabled. Please add your API key under Content Egg → Settings → AI → AI API Key.', 'content-egg')
                );
            }

            // Not $row: that is the queue row parameter, still needed further
            // down for module_id, scheduled_at and category_id.
            $promptRows = [];
            foreach ($preset['ai_prompts'] ?? [] as $promptRow)
            {
                if (is_array($promptRow) && !empty($promptRow['name']))
                {
                    $promptRows[$promptRow['name']] = $promptRow;
                }
            }

            $custom_ai_model = isset($preset['ai_model']) ? $preset['ai_model'] : '';

            $postPrompt = self::createPostPrompt($custom_ai_model);
            $postPrompt->setSourceProduct($sourceProduct);
            $postPrompt->setProduct($product);
            $postPrompt->setCustomPrompts($promptRows);

            // Which sinks are switched on, and which prompt (or built-in) runs each.
            $sinks = [];
            foreach (['title' => 'ai_title', 'content' => 'ai_content', 'short_desc' => 'ai_short_desc'] as $node => $presetKey)
            {
                if (!empty($preset[$presetKey]))
                {
                    $sinks[$node] = $preset[$presetKey];
                }
            }

            // Alias map: every name a user may write in %AI.…% => the single
            // generation it stands for. A prompt selected as a sink is an alias
            // of that sink, so it is never generated twice.
            $aliasMap = [];
            foreach (array_keys($promptRows) as $promptName)
            {
                $aliasMap[strtolower($promptName)] = $promptName;
            }
            foreach ($sinks as $node => $methodKey)
            {
                $aliasMap[$node] = $node;

                if (isset($promptRows[$methodKey]))
                {
                    $aliasMap[strtolower($methodKey)] = $node;
                }
            }

            $generator = function (string $node) use ($postPrompt, $sinks): string
            {
                if ($node === 'title')
                {
                    $value = $postPrompt->generateTitle($sinks['title']);

                    // Built-in description methods interpolate the post title, so
                    // this has to follow the title node wherever it is generated —
                    // it may now be pulled in as another prompt's dependency.
                    $postPrompt->setPostTitle($value);

                    return $value;
                }

                if ($node === 'content')
                {
                    return $postPrompt->generateDescription($sinks['content']);
                }

                if ($node === 'short_desc')
                {
                    return $postPrompt->generateShortDescription($sinks['short_desc']);
                }

                return $postPrompt->generateCustomPrompt($node);
            };

            $resolver = new AiValueResolver($aliasMap, $generator);
            $postPrompt->setValueResolver($resolver);

            // ---- Entry points: the three sinks, in their established order ----
            // A sink that throws fails the import row: an empty post title is
            // worse than a visible failure.
            $sinkLabels = [
                'title'      => esc_html__('AI post title generated: %s.', 'content-egg'),
                'content'    => esc_html__('AI post content generated: %s.', 'content-egg'),
                'short_desc' => esc_html__('AI short desc generated: %s.', 'content-egg'),
            ];
            $sinkErrors = [
                'title'      => 'AI: Post Title generation error: ',
                'content'    => 'AI: Post Content generation error: ',
                'short_desc' => 'AI: Post Short Description generation error: ',
            ];

            foreach ($sinks as $node => $methodKey)
            {
                if ($node === 'title' && !$postPrompt->canGenerateTitle($methodKey))
                {
                    continue;
                }
                if ($node === 'content' && !$postPrompt->canGenerateDescription($methodKey))
                {
                    continue;
                }
                if ($node === 'short_desc' && !$postPrompt->canGenerateShortDescription($methodKey))
                {
                    continue;
                }

                try
                {
                    $resolver->resolveOrFail($node);
                }
                catch (\Exception $e)
                {
                    throw new \RuntimeException($sinkErrors[$node] . esc_html($e->getMessage()));
                }

                $this->heartbeat();

                $this->logger->notice(sprintf($sinkLabels[$node], esc_html($methodKey)));
            }

            // ---- Entry points: placeholder-only prompts, in row order ----
            // Anything already pulled in as a dependency above is memoised, so
            // this loop is a no-op for it. A failure here is non-fatal: a missing
            // meta description must not kill a 500-product import.
            foreach (array_keys($promptRows) as $name)
            {
                if (!in_array($name, $referencedPrompts, true))
                {
                    continue;
                }

                // Prompts that are a sink's selected method already ran above.
                if (isset($sinks[$aliasMap[strtolower($name)] ?? '']))
                {
                    continue;
                }

                // resolve() reports its own failure, so only claim success when
                // it added no notice.
                $noticesBefore = count($resolver->notices());

                $resolver->resolve($name);

                if (count($resolver->notices()) === $noticesBefore)
                {
                    $this->logger->notice(sprintf(
                        __('AI custom prompt generated: %s.', 'content-egg'),
                        $name
                    ));
                }

                $this->heartbeat();
            }

            foreach ($resolver->notices() as $notice)
            {
                $this->logger->notice($notice);
            }

            $ai = array_merge($ai, $resolver->all());
        }

        // ---------- 1. Resolve title / content via templates ----------
        $titleTemplate = $preset['title_tpl'] ?? '%PRODUCT.title%';
        $bodyTemplate = $preset['body_tpl']  ?? '';
        $wooShortDescTemplate = $preset['woo_short_desc_tpl']  ?? '';

        $postTitle = ProductHelper::replaceImportPatterns($titleTemplate, $sourceProduct, $product, $ai);
        $postBody = ProductHelper::replaceImportPatterns($bodyTemplate, $sourceProduct, $product, $ai);
        $wooShortDesc = ProductHelper::replaceImportPatterns($wooShortDescTemplate, $sourceProduct, $product, $ai);

        // ---------- 2. Construct post array ----------
        $postArr = [
            'post_title'   => $postTitle,
            'post_content' => $postBody,
            'post_status'  => $preset['post_status'] ?? 'draft',
            'post_author'  => $preset['author_id'],
            'post_type'    => $isWoo ? 'product' : 'post',
            'post_name'    => apply_filters(
                'cegg_import_post_name',
                TextHelper::sluggable($postTitle),
                $sourceProduct,
                $product,
                $ai,
                $preset
            ),
        ];

        // ---------- 2.1 Schedule post if needed ----------
        $scheduled = $row['scheduled_at'] ?? '';
        $now_ts    = current_time('timestamp');

        if ($scheduled && strtotime($scheduled) > $now_ts)
        {
            $postArr['post_date'] = $scheduled;
            $postArr['post_date_gmt'] = get_gmt_from_date($scheduled);
            if ($postArr['post_status'] === 'publish')
            {
                $postArr['post_status'] = 'future';
            }

            // Log the scheduled date in the site’s timezone
            $timestamp = mysql2date('U', $scheduled);
            $format    = get_option('date_format') . ' ' . get_option('time_format');
            $when      = date_i18n($format, $timestamp);

            $this->logger->notice(
                sprintf(
                    __('Post scheduled for %s.', 'content-egg'),
                    esc_html($when)
                )
            );
        }

        // ---------- 3. Insert post ----------
        wp_set_current_user($preset['author_id']);

        // Carried by the insert rather than written by a wp_update_post() of
        // its own straight afterwards: on a site whose cache plugin purges on
        // publish, every post write is a full purge, and an import already
        // writes each product more than once. Sanitised here, after the author
        // switch, so kses runs in exactly the context the insert does.
        if ($isWoo && $wooShortDesc)
        {
            $postArr['post_excerpt'] = wp_kses_post($wooShortDesc);
        }

        $postId = wp_insert_post($postArr, true);
        if (is_wp_error($postId))
        {
            throw new \RuntimeException(esc_html($postId->get_error_message()));
        }

        // ---------- 3.1 Set WooCommerce product data ----------
        if ($isWoo)
        {
            if (!empty($preset['product_type']) && $preset['product_type'] == 'external')
            {
                $classname = \WC_Product_Factory::get_product_classname($postId, 'external');
                $wooprod = new $classname($postId);
                $wooprod->save();
            }

            // if manual sync is enabled, set the product to be synced
            $product_sync = GeneralConfig::getInstance()->option('woocommerce_product_sync');
            if ($product_sync == 'manually')
            {
                $product['woo_sync'] = 'true';

                if (GeneralConfig::getInstance()->option('woocommerce_attributes_sync'))
                {
                    $product['woo_attr'] = 'true';
                }
            }
        }

        // ---------- 4. Categories ----------
        $priorityCateg = isset($row['category_id']) ? (int) $row['category_id'] : 0;

        if (empty($product['categoryPath']) && !empty($product['category']))
        {
            $product['categoryPath'] = [$product['category']];
        }

        if ($isWoo)
        {
            $helper        = WooHelper::class;
            $taxonomy      = 'product_cat';
            $defaultTermId = (int) $preset['default_woo_cat'];
            $setter        = fn($postId, $termIds) => wp_set_post_terms($postId, $termIds, $taxonomy);
        }
        else
        {
            $helper        = PostHelper::class;
            $taxonomy      = 'category';
            $defaultTermId = (int) $preset['default_cat'];
            $setter        = fn($postId, $termIds) => wp_set_post_categories($postId, $termIds);
        }

        $categoryId = 0;

        /**
         * If a priority category ID is supplied AND exists in the current taxonomy, use it.
         */
        if ($priorityCateg)
        {
            $termExists = term_exists((int) $priorityCateg, $taxonomy);

            if ($termExists && !is_wp_error($termExists))
            {
                $categoryId = (int) (is_array($termExists) ? ($termExists['term_id'] ?? 0) : $termExists);
            }
        }

        /**
         * Then the preset's own mapping: a feed category the user has pointed
         * at one of their store categories. It runs ahead of dynamic creation
         * so a mapped product never creates a merchant category.
         */
        if (!$categoryId && !empty($preset['category_map']))
        {
            $mappedId = CategoryMapper::resolve((array) $preset['category_map'], $product, $taxonomy);

            // The term can be deleted long after the mapping was saved.
            if ($mappedId && term_exists($mappedId, $taxonomy))
            {
                $categoryId = $mappedId;
            }
        }

        /**
         * Otherwise, apply dynamic category creation rules
         */
        if (!$categoryId)
        {
            // “Create” mode: single-level category
            if (
                'create' === (string) ($preset['dynamic_categories'] ?? '')
                && ! empty($product['category'])
            )
            {
                $categoryId = $helper::createCategory($product['category']);
            }
            // “Nested” mode: multi-level path
            elseif (
                'create_nested' === (string) ($preset['dynamic_categories'] ?? '')
                && ! empty($product['categoryPath'])
                && is_array($product['categoryPath'])
            )
            {
                $categoryId = $helper::createNestedCategories($product['categoryPath']);
            }
        }

        //  Finally, fall back to the default term
        if (! $categoryId)
        {
            $categoryId = $defaultTermId;
        }

        $categoryId = absint($categoryId);

        /**
         * Last word on categories, for mapping rules too dynamic to express in
         * the preset UI — a per-merchant table, a regex, several terms at once.
         *
         *   add_filter('cegg_import_category_ids', function ($ids, $product, $preset, $taxonomy, $post_id) {
         *       return $ids;
         *   }, 10, 5);
         */
        $termIds = apply_filters('cegg_import_category_ids', [$categoryId], $product, $preset, $taxonomy, $postId);

        // Apply to the post
        $setter($postId, array_map('absint', (array) $termIds));

        // ---------- 5. Save product data ----------
        update_post_meta($postId, '_cegg_import_unique_id', $product['unique_id'] ?? '');

        $group = apply_filters('cegg_import_product_group', self::PRODUCT_GROUP);

        ContentManager::saveData([$product], $product['module_id'], $postId, true, $group);

        // Force sync now after content_egg_save_data fired
        if ($isWoo)
        {
            $preparedData = ContentManager::dataPreviewPrepare([$product], $row['module_id'], $postId);
            $syncProduct = reset($preparedData);
            WooIntegrator::wooSync($syncProduct, $row['module_id'], $postId);
        }

        foreach ($comparison_product_data as $mid => $data)
        {
            ContentManager::saveData($data, $mid, $postId, true, $group);
        }

        // ---------- 6. Custom fields from preset ----------
        if (!empty($preset['custom_fields']) && is_array($preset['custom_fields']))
        {
            $cf_added = [];
            foreach ($preset['custom_fields'] as $cf)
            {
                if (empty($cf['key']))
                {
                    continue;
                }

                // Decide on the template the admin typed, NOT on the resolved
                // value: product data comes from a queue payload that a mere
                // Contributor can supply, so testing the resolved string would
                // let markup smuggled through %SOURCE.description% flip this to
                // the permissive branch. Markup is allowed only where the admin
                // wrote markup, e.g. "<div>%AI.faq%</div>".
                $allow_markup = TextHelper::isHtmlTagDetected($cf['value'] ?? '');

                $val = ProductHelper::replaceImportPatterns($cf['value'] ?? '', $sourceProduct, $product, $ai);

                // Plain values skip kses on purpose — it would rewrite a bare "&"
                // and corrupt an SEO keyword like "M&S".
                $val = $allow_markup
                    ? TextHelper::sanitizeHtml($val)
                    : sanitize_textarea_field($val);

                $cf_added[] = $cf['key'];

                update_post_meta($postId, $cf['key'], $val);
            }

            if ($cf_added)
            {
                $this->logger->notice(sprintf(
                    __('Custom fields added: %s.', 'content-egg'),
                    implode(', ', $cf_added)
                ));
            }
        }

        // ---------- 6.1 Featured image alt text ----------
        // Deliberately placed after the custom fields: the thumbnail is attached
        // back in wp_insert_post() / wooSync(), long before those meta values
        // exist, so a filter hooked at attach time could not read them. Here a
        // filter can use either the AI values or any meta the preset just wrote.
        //
        //   add_filter('cegg_import_featured_image_alt', function ($alt, $post_id, $source, $product, $ai) {
        //       return $ai['AI.image_alt'] ?? $alt;          // a custom prompt named image_alt
        //   }, 10, 5);
        //
        //   add_filter('cegg_import_featured_image_alt', function ($alt, $post_id) {
        //       return get_post_meta($post_id, '_yoast_wpseo_focuskw', true) ?: $alt;
        //   }, 10, 2);
        //
        // Returning '' (the default) leaves the alt WordPress already stored,
        // which is the product title.
        $image_alt = apply_filters('cegg_import_featured_image_alt', '', $postId, $sourceProduct, $product, $ai);

        if (is_string($image_alt) && $image_alt !== '')
        {
            $thumb_id = get_post_thumbnail_id($postId);

            // In external-featured-image mode get_post_thumbnail_id() returns a
            // synthetic id that is not a real attachment, so there is nothing to
            // write meta to. Alt for that mode belongs in a template filter.
            if ($thumb_id && get_post_type($thumb_id) === 'attachment')
            {
                update_post_meta($thumb_id, '_wp_attachment_image_alt', sanitize_text_field($image_alt));

                $this->logger->notice(sprintf(
                    __('Featured image alt text set: %s.', 'content-egg'),
                    $image_alt
                ));
            }
        }

        // ---------- 7. Tags ----------
        if (!empty($preset['tags']))
        {
            $tags = [];
            $preset_tags = TextHelper::getArrayFromCommaList($preset['tags']);
            foreach ($preset_tags as $tag)
            {
                $tag = ProductHelper::replaceImportPatterns($tag, $sourceProduct, $product, $ai);

                if (!empty($tag))
                {
                    $tags[] = sanitize_text_field($tag);
                }
            }

            if (!empty($tags))
            {
                if ($isWoo)
                {
                    wp_set_object_terms($postId, $tags, 'product_tag', true);
                }
                else
                {
                    wp_set_post_tags($postId, $tags, true);
                }

                $this->logger->notice(sprintf(
                    __('Tags added: %d.', 'content-egg'),
                    count($tags)
                ));
            }
        }

        if ($isWoo)
        {
            $label = __('Woo Product ID: %d.', 'content-egg');
        }
        else
        {
            $label = __('Post ID: %d.', 'content-egg');
        }
        $this->logger->notice(sprintf($label, (int) $postId));

        return $postId;
    }

    public static function createPostPrompt($custom_ai_model = null): ImportPostPromptPro
    {
        $config = GeneralConfig::getInstance();

        // Pick random API key
        $keys = array_filter(array_map('trim', explode(',', (string) $config->option('ai_key'))));
        $apiKey = $keys ? $keys[array_rand($keys)] : '';

        // Resolve model: custom → global
        $model = $custom_ai_model ?: $config->option('ai_model');

        $lang = $config->option('ai_language');
        $temp = $config->option('ai_temperature');

        $extraOpts = [];

        // OpenRouter unified router
        if ($model === 'openrouter/auto')
        {
            $extraOpts = TextHelper::getArrayFromCommaList(
                (string) $config->option('openrouter_models')
            );
        }

        // Reproducible randomness in dev
        if (\ContentEgg\application\Plugin::isDevEnvironment())
        {
            mt_srand(12345678);
        }

        $prompt = new ImportPostPromptPro($apiKey, $model, $extraOpts);
        $prompt->setLang($lang);
        $prompt->setTemperature($temp);

        return $prompt;
    }
}
