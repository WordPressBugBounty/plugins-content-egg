<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

use ContentEgg\application\helpers\TextHelper;
use ContentEgg\application\models\PrefillQueueModel;
use ContentEgg\application\Plugin;

use function ContentEgg\prn;
use function ContentEgg\prnx;

/**
 * ProductPrefillService class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2025 keywordrush.com
 */

class ProductPrefillService
{
    protected PrefillQueueModel $queue;
    protected PrefillKeywordResolver $keywordResolver;
    protected ContentManipulator $contentManipulator;
    protected PrefillLogger $logger;

    protected $start_time = 0;

    public function __construct()
    {
        $this->queue = PrefillQueueModel::model();
        $this->logger = new PrefillLogger();
        $this->keywordResolver = new PrefillKeywordResolver($this->logger);
        $this->contentManipulator = new ContentManipulator();
    }

    public function processBatch(int $limit = 1): void
    {
        $batch = $this->queue->getNextBatch($limit);

        if (empty($batch))
        {
            return;
        }

        foreach ($batch as $item)
        {
            $post_id = (int)$item['post_id'];

            try
            {
                $this->processPost($post_id);
            }
            catch (\Throwable $e)
            {
                $error_message = TextHelper::truncate($e->getMessage(), 300);

                if (Plugin::isDevEnvironment())
                {
                    $error_message .= sprintf(
                        ' [File: %s, Line: %d]',
                        $e->getFile(),
                        $e->getLine()
                    );
                }

                $this->queue->markAsFailed(
                    $post_id,
                    $this->logger->format([
                        'note' => sprintf(__('Exception: %s', 'content-egg'), $error_message),
                    ]),
                    microtime(true) - $this->start_time,
                );
            }
        }
    }

    public function processPost(int $post_id): void
    {
        $this->start_time = microtime(true);

        // 1. Load queue entry
        $row = $this->queue->findByPostId($post_id);
        if (!$row || empty($row['config_key']))
        {
            throw new \RuntimeException("Missing queue config for post ID {$post_id}");
        }

        $config = get_transient($row['config_key']);

        if (!is_array($config))
        {
            throw new \RuntimeException("Prefill config not found or expired for key: {$row['config_key']}");
        }

        // 2. Load post
        $post = get_post($post_id);
        if (!$post || $post->post_status === 'trash')
        {
            throw new \RuntimeException("Post not found or is in trash: ID {$post_id}");
        }

        // 3. Validate modules
        $available_modules = ModuleManager::getInstance()->getAffiliateParsersList(true, true, true);
        $available_module_ids = array_keys($available_modules);
        $config_modules = $config['modules'] ?? [];
        $modules = array_values(array_intersect($available_module_ids, $config_modules));

        $existing_module_behavior = $config['existing_module_behavior'] ?? 'skip_module';
        $existing_modules = $this->getExistingModuleData($post_id, $modules);

        if ($existing_module_behavior === 'skip_post' && !empty($existing_modules))
        {
            $this->queue->markAsDone(
                $post_id,
                $this->logger->format([
                    'note' => __('Post skipped because existing module data was found:', 'content-egg') . ' ' . implode(', ', ModuleManager::getInstance()->getModuleNamesByIds($existing_modules)),
                ]),
                microtime(true) - $this->start_time,
            );
            return;
        }

        if ($existing_module_behavior === 'skip_module' && !empty($existing_modules))
        {
            $modules = array_values(array_diff($modules, $existing_modules));
            $skipped_modules = array_intersect($modules, $existing_modules);
            if ($skipped_modules)
            {
                $this->logger->notice(sprintf(__('Skipped modules with existing data: %s', 'content-egg'), implode(', ', ModuleManager::getInstance()->getModuleNamesByIds($skipped_modules))));
            }
        }

        if (empty($modules))
        {
            if (!apply_filters('cegg_prefill_continue_without_modules', false))
            {
                $this->queue->markAsDone(
                    $post_id,
                    $this->logger->format([
                        'note' => __('No modules to process.', 'content-egg'),
                    ]),
                    microtime(true) - $this->start_time,
                );
                return;
            }
        }

        // 4. Resolve Keyword
        $keyword = $this->keywordResolver->resolve($post, $config);
        if (!$keyword)
        {
            if (!apply_filters('cegg_prefill_continue_without_keyword', false))
            {
                $this->queue->markAsFailed(
                    $post_id,
                    $this->logger->format([
                        'note' => __('No keyword found for prefill.', 'content-egg'),
                    ]),
                    microtime(true) - $this->start_time,
                );
                return;
            }
        }

        // 5. Prefill products
        $max_products_total = (int)$config['max_products_total'] ? (int)$config['max_products_total'] : 100;
        $total_products_added = 0;
        $product_counts = [];

        foreach ($modules as $module_id)
        {
            if (!$keyword)
            {
                continue;
            }

            $parser = ModuleManager::getInstance()->parserFactory($module_id);
            $max_per_module = (int)($config['max_products_per_module'] ?? 0);

            if ($max_per_module <= 0 || $max_per_module > 10)
            {
                $max_per_module = (int)($parser->getConfigInstance()->option('entries_per_page_update') ?: 10);
            }

            $settings = ['entries_per_page' => $max_per_module];

            if (!empty($config['product_group']))
            {
                $keyword = $keyword . '->' . $config['product_group'];
            }

            try
            {
                $parser->getConfigInstance()->applayCustomOptions($settings);
                $data = $parser->doMultipleRequests($keyword);
            }
            catch (\Exception $e)
            {
                $this->logger->notice(sprintf(__('Module error "%s": %s', 'content-egg'), $module_id, TextHelper::truncate($e->getMessage(), 200)));
                continue;
            }

            if (!$data)
            {
                $this->logger->notice(sprintf(__('No products found for module "%s".', 'content-egg'), $module_id));
                continue;
            }

            if ($total_products_added + count($data) > $max_products_total)
            {
                $remaining = $max_products_total - $total_products_added;
                $data = array_slice($data, 0, $remaining);
            }

            ContentManager::saveData($data, $parser->getId(), $post->ID);

            $product_counts[$module_id] = count($data);
            $total_products_added += count($data);

            if ($total_products_added >= $max_products_total)
            {
                $this->logger->notice(sprintf(__('Max products limit reached: %d', 'content-egg'), $max_products_total));
                break;
            }
        }

        if (!$total_products_added)
        {
            if (!apply_filters('cegg_prefill_continue_without_products', false))
            {
                $this->queue->markAsFailed(
                    $post_id,
                    $this->logger->format([
                        'note' => __('No products added.', 'content-egg'),
                    ]),
                    microtime(true) - $this->start_time,
                );
                return;
            }
        }

        // 6. Insert shortcodes/blocks if configured
        if (!empty($config['shortcode_blocks']) && is_array($config['shortcode_blocks']))
        {
            $this->contentManipulator->injectAndSave($post, $config['shortcode_blocks']);
        }

        // 7. Finish
        $this->queue->markAsDone(
            $post_id,
            $this->logger->format([
                'keyword' => $keyword,
                'keyword_source' => $config['keyword_source'] ?? '',
                'product_counts' => $product_counts,
                'shortcode_positions' => $this->contentManipulator->getInsertedPositions(),
            ]),
            microtime(true) - $this->start_time,
        );
    }

    protected function getKeywordFromPostTitle(\WP_Post $post): string
    {
        return trim($post->post_title);
    }

    /**
     * Get list of modules that already have product data for a post.
     *
     * @param int $post_id
     * @param array $modules List of module IDs to check
     * @return array List of module IDs with existing data
     */
    protected function getExistingModuleData(int $post_id, array $modules): array
    {
        if (empty($modules))
        {
            return [];
        }

        $existing = [];

        foreach ($modules as $module_id)
        {
            if (ContentManager::isNotEmptyDataExists($post_id, $module_id))
            {
                $existing[] = $module_id;
            }
        }

        return $existing;
    }
}
