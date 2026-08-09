<?php

namespace ContentEgg\application\components;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\components\ai\PrefillPrompt;
use ContentEgg\application\models\PrefillQueueModel;;

defined('\ABSPATH') || exit;

/**
 * PrefillKeywordResolver class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

class PrefillKeywordResolver
{
    protected PrefillLogger $logger;
    protected $prompt;

    public function __construct(PrefillLogger $logger, $prompt)
    {
        $this->logger = $logger;
        $this->prompt = $prompt;
    }

    /**
     * Resolve the best keyword for given post and config.
     *
     * @param \WP_Post $post
     * @param array $config
     * @return string
     */
    public function resolve(\WP_Post $post, array $config, array $modules): string
    {
        $source = $config['keyword_source'] ?? '';

        switch ($source)
        {
            case 'post_title':
                return $this->fromPostTitle($post);

            case 'meta_field':
                $meta_key = $config['meta_field_name'] ?? '';
                return $this->fromMetaField($post, $meta_key);

            case 'gtin_woocommerce':
                $meta_key = '_global_unique_id';
                return $this->fromMetaField($post, $meta_key);

            case 'gtin_module':
                $module_id = $config['source_module_gtin'] ?? '';
                return $this->fromModuleData($post, $module_id, 'ean');

            case 'product_title_module':
                $module_id = $config['source_module_title'] ?? '';
                return $this->fromModuleData($post, $module_id, 'title');

            case 'thirsty_link':
                return $this->fromThirstyLink($post);

            case 'ai':
                return $this->fromAI($post, $modules);

            default:
                $this->logNotice(sprintf(__('Unknown keyword source "%s".', 'content-egg'), $source));
                return '';
        }
    }

    /**
     * Keyword = Post Title.
     */
    protected function fromPostTitle(\WP_Post $post): string
    {
        return trim($post->post_title);
    }

    /**
     * Keyword = Custom Meta Field Value.
     */
    protected function fromMetaField(\WP_Post $post, string $meta_key): string
    {
        if (empty($meta_key))
        {
            $this->logNotice(__('Meta field key is empty.', 'content-egg'));
            return '';
        }

        $value = get_post_meta($post->ID, $meta_key, true);

        if ($value === '' || $value === null)
        {
            $this->logNotice(sprintf(__('Meta field "%s" not found for post ID %d.', 'content-egg'), $meta_key, $post->ID));
            return '';
        }

        if (is_array($value))
        {
            $value = implode(' ', array_filter($value));
        }

        return is_string($value) ? trim($value) : '';
    }

    /**
     * Keyword = Value from existing module product data
     */
    protected function fromModuleData(\WP_Post $post, string $module_id, string $field): string
    {
        if (empty($module_id))
        {
            $this->logNotice(__('Module ID is empty for module keyword source.', 'content-egg'));
            return '';
        }

        $products = \ContentEgg\application\components\ContentManager::getData($post->ID, $module_id);

        if (empty($products) || !is_array($products))
        {
            $this->logNotice(sprintf(__('Module data is empty for module "%s" on post ID %d.', 'content-egg'), $module_id, $post->ID));

            return '';
        }

        foreach ($products as $item)
        {
            if (!empty($item[$field]) && is_scalar($item[$field]))
            {
                return trim((string)$item[$field]);
            }
        }

        $this->logNotice(sprintf(__('Field "%s" not found in products for module "%s" on post ID %d.', 'content-egg'), $field, $module_id, $post->ID));
        return '';
    }

    /**
     * Keyword = Affiliate URL stored in the product (e.g. a ThirstyAffiliates
     * cloaked link). A local cloaked link is resolved to its destination URL;
     * an external URL is used as-is. The resulting URL is passed to the module
     * search — modules that support URL lookup (Amazon, Aliexpress, Ebay2, ...)
     * turn it into an exact product match.
     */
    protected function fromThirstyLink(\WP_Post $post): string
    {
        $meta_key = apply_filters('cegg_prefill_thirsty_link_meta_key', '_product_url', $post);

        $url = get_post_meta($post->ID, $meta_key, true);

        if (!is_string($url) || trim($url) === '')
        {
            $this->logNotice(sprintf(__('No affiliate URL found in meta field "%s" for post ID %d.', 'content-egg'), $meta_key, $post->ID));
            return '';
        }

        $url = trim($url);

        return $this->resolveThirstyLink($url, $post);
    }

    /**
     * Resolve a ThirstyAffiliates cloaked link to its destination URL.
     *
     * ThirstyAffiliates stores links as a "thirstylink" custom post type; the
     * destination is kept in the "_ta_destination_url" post meta. The cloaked
     * URL is a local link whose last path segment is the thirstylink slug.
     * Non-local URLs are assumed to already be destination URLs and returned
     * unchanged.
     */
    protected function resolveThirstyLink(string $url, \WP_Post $post): string
    {
        // Only local (same-site) links can be ThirstyAffiliates cloaks.
        $home = trailingslashit(home_url());
        if (strpos($url, $home) !== 0)
        {
            return $url;
        }

        $path = trim((string) wp_parse_url($url, PHP_URL_PATH), '/');
        if ($path === '')
        {
            return $url;
        }

        $segments = explode('/', $path);
        $slug     = end($segments);

        $link = get_page_by_path($slug, OBJECT, 'thirstylink');
        if (!$link)
        {
            $this->logNotice(sprintf(__('ThirstyAffiliates link not found for URL "%s" (post ID %d). Using the original URL.', 'content-egg'), $url, $post->ID));
            return $url;
        }

        $destination_meta_key = apply_filters('cegg_prefill_ta_destination_meta_key', '_ta_destination_url', $link);
        $destination          = get_post_meta($link->ID, $destination_meta_key, true);

        if (!is_string($destination) || trim($destination) === '')
        {
            $this->logNotice(sprintf(__('ThirstyAffiliates destination is empty for link "%s" (post ID %d). Using the original URL.', 'content-egg'), $slug, $post->ID));
            return $url;
        }

        return trim($destination);
    }

    /**
     * Keyword = Generated by AI
     */
    protected function fromAI(\WP_Post $post, array $modules): string
    {
        $max_keywords = apply_filters('cegg_prefill_ai_keywords_count', 2);

        $keywords = $this->prompt->suggestProductKeywordsForPost(
            $post->post_title,
            $post->post_content,
            $max_keywords,
            $modules,
        );

        PrefillQueueModel::model()->updateAiStat($post->ID, $this->prompt->getLastUsageStat());

        if (!$keywords || !is_array($keywords))
        {
            return '';
        }

        return reset($keywords);
    }

    protected function logNotice(string $message): void
    {
        if ($this->logger)
        {
            $this->logger->notice($message);
        }
    }
}
