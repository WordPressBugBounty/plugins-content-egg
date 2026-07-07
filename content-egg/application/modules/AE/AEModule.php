<?php

namespace ContentEgg\application\modules\AE;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\AeIntegrationConfig;
use ContentEgg\application\components\AffiliateParserModule;
use ContentEgg\application\components\ContentProduct;
use ContentEgg\application\admin\PluginAdmin;
use ContentEgg\application\helpers\TextHelper;
use ContentEgg\application\components\LinkHandler;
use ContentEgg\application\components\ContentManager;
use \Keywordrush\AffiliateEgg\ParserManager;

/**
 * AEModule class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class AEModule extends AffiliateParserModule
{

    public function __construct($module_id = null)
    {
        if (!AeIntegrationConfig::isAEIntegrationPosible())
        {
            throw new \Exception('The required Affiliate Egg plugin is not installed.');
        }

        parent::__construct($module_id);
    }

    public function info()
    {
        $sm = \Keywordrush\AffiliateEgg\ShopManager::getInstance();
        $shortId = $this->getMyShortId();
        $shop = $sm->getItem($shortId);
        if ($shop)
        {
            $name = $sm->getShopName($shortId);
            if (method_exists($shop, 'isDeprecated') && $shop->isDeprecated())
                $name .= ' (deprecated)';
        }
        else
        {
            // Custom domain: decode the dot-free key back to the domain.
            $name = ucfirst(AeModuleId::decodeKey($shortId));
        }

        return array(
            'name' => $name . ' [AE]',
            'docs_uri' => 'https://ce-docs.keywordrush.com/modules/affiliate-egg-integration',
        );
    }

    public function getShopHost()
    {
        $uri = \Keywordrush\AffiliateEgg\ShopManager::getInstance()->getShopUri($this->getMyShortId());
        if (!$uri)
        {
            // Custom domain: decode the dot-free key back to the bare host.
            return strtolower(AeModuleId::decodeKey($this->getMyShortId()));
        }
        $uri = str_replace('http://', '', $uri);
        $uri = str_replace('https://', '', $uri);
        $uri = str_replace('www.', '', $uri);
        $uri = strtolower($uri);
        $uri = trim($uri, "/");

        return $uri;
    }

    public function getParserType()
    {
        return self::PARSER_TYPE_PRODUCT;
    }

    public function defaultTemplateName()
    {
        return 'data_grid';
    }

    public function isItemsUpdateAvailable()
    {
        return true;
    }

    public function isFree()
    {
        return true;
    }

    public function isUrlSearchAllowed()
    {
        return true;
    }

    public function doRequest($keyword, $query_params = array(), $is_autoupdate = false)
    {
        $this->search_notice = null;

        if ($is_autoupdate)
            $entries_per_page = $this->config('entries_per_page_update');
        else
            $entries_per_page = $this->config('entries_per_page');

        $results = array();

        $is_url_passed = false;
        $is_catalog_url_passed = false;

        // Listing url? [import] is the primary prefix; [catalog] is a backward-compatible alias.
        if ($catalog = AeCatalogPrefix::parse($keyword, $entries_per_page))
        {
            $keyword = $catalog['url'];
            $entries_per_page = $catalog['limit'];
            $is_catalog_url_passed = true;
        }

        // ASIN?
        if (TextHelper::isAsin($keyword))
            $keyword = 'https://www.' . $this->getShopHost() . '/dp/' . $keyword . '/';

        // A pasted URL without a scheme (e.g. "shop.com/p/123") — normalize it so it
        // isn't mistaken for a search keyword. Requires a path, so plain keywords
        // ("nike shoes") are never affected. Interactive searches only.
        if (!$is_autoupdate && !filter_var($keyword, FILTER_VALIDATE_URL)
            && preg_match('~^[\w.-]+\.[a-z]{2,}/\S~i', $keyword))
            $keyword = 'https://' . $keyword;

        // 1. Url passed?
        $is_url_passed = filter_var($keyword, FILTER_VALIDATE_URL) && TextHelper::getDomainWithoutSubdomain($this->getShopHost()) == TextHelper::getHostName($keyword);

        // A URL from a different store: don't search for it — ask for a matching URL.
        if (!$is_autoupdate && !$is_url_passed && filter_var($keyword, FILTER_VALIDATE_URL))
        {
            $this->search_notice = $this->buildSearchNotice('wrong_domain');
            return array();
        }

        // Track what we're parsing so a failure can be classified (product vs
        // catalog/search) into an actionable hint in the catch block below.
        $url_type = 'catalog';

        try
        {
            if ($is_url_passed)
            {
                $url = $keyword;
                // parse product by url
                if (!$is_catalog_url_passed)
                {
                    $url_type = 'product';
                    $results[] = ParserManager::getInstance()->parseProduct($url);
                    if ($results)
                    {
                        return $this->prepareResults($results);
                    }
                }

                // try parse catalog
                $url_type = 'catalog';
                $product_urls = ParserManager::getInstance()->parseCatalog($url, $entries_per_page);
                if (!$product_urls)
                    return array();
            }

            // 2. Parse catalog (keyword search)
            if (!$is_url_passed)
            {
                $shortId = $this->getMyShortId();
                $shop = \Keywordrush\AffiliateEgg\ShopManager::getInstance()->getItem($shortId);

                if ($shop)
                {
                    // Registered shop: an optional module override wins over the
                    // built-in search URL; empty falls back to AE's default.
                    $override    = (string) $this->config('search_uri');
                    $has_builtin = method_exists($shop, 'isSearchUriExists') && $shop->isSearchUriExists();

                    // Keyword search needs either a built-in search or an override.
                    if (!$is_autoupdate && $override === '' && !$has_builtin)
                    {
                        $this->search_notice = $this->buildSearchNotice('no_search_registered');
                        return array();
                    }

                    if (\version_compare('10.9.9', \Keywordrush\AffiliateEgg\Plugin::version(), '>'))
                        $product_urls = ParserManager::getInstance()->parseSearchCatalog($shortId, $keyword, $entries_per_page);
                    else
                        $product_urls = ParserManager::getInstance()->parseSearchCatalog($shortId, $keyword, $entries_per_page, $query_params, $override !== '' ? $override : null);
                }
                else
                {
                    // Custom domain: build the search URL from this module's own
                    // Search URL (CE-side). Empty means this module is direct-URL-only.
                    $search_uri = (string) $this->config('search_uri');
                    if ($search_uri === '')
                    {
                        if (!$is_autoupdate)
                            $this->search_notice = $this->buildSearchNotice('no_search_custom');
                        return array();
                    }
                    $search_url = AeSearchUrl::substitute($search_uri, $keyword);
                    $product_urls = ParserManager::getInstance()->parseCatalog($search_url, $entries_per_page, ParserManager::HTTP_ARG_ID_SEARCH);
                }

                if (!$product_urls || !is_array($product_urls))
                {
                    // Search ran but found nothing — often a JS-rendered search page.
                    if (!$is_autoupdate)
                        $this->search_notice = $this->buildSearchNotice('no_results', $keyword);
                    return array();
                }
            }
        }
        catch (\Exception $e)
        {
            throw new \Exception($this->buildSearchErrorMessage($e, $url_type), (int) $e->getCode());
        }

        // 3. Parse products
        $product_sleep = \Keywordrush\AffiliateEgg\GeneralConfig::getInstance()->option('product_sleep');
        $last_product_error = null;
        foreach ($product_urls as $key => $url)
        {
            try
            {
                $results[] = ParserManager::getInstance()->parseProduct($url);
            }
            catch (\Exception $e)
            {
                $last_product_error = $e;
                continue;
            }

            // sleep
            if ($product_sleep && $key < count($product_urls) - 1)
            {
                usleep($product_sleep);
            }
        }

        // Case 4: search found product URLs but none could be read. Surface why
        // (blocked / needs a custom parser) instead of a bare "no results".
        if (!$is_autoupdate && !$results && $last_product_error)
        {
            $count = count($product_urls);
            $lead = sprintf(
                _n('Found %d product, but it couldn\'t be read.', 'Found %d products, but none could be read.', $count, 'content-egg'),
                $count
            );
            throw new \Exception(
                $this->buildSearchErrorMessage($last_product_error, 'product', $lead),
                (int) $last_product_error->getCode()
            );
        }

        return $this->prepareResults($results);
    }

    /**
     * Allow the safe actionable link built by buildSearchErrorMessage() to
     * survive to the metabox (errors there are rendered via ng-bind-html).
     * Everything else is stripped. Only Affiliate Egg modules opt into this.
     */
    protected function formatErrorMessage($message)
    {
        return wp_kses($message, array(
            'a'      => array('href' => array(), 'target' => array(), 'rel' => array()),
            'strong' => array(),
            'small'  => array(),
            'br'     => array(),
        ));
    }

    /**
     * Turn a raw parse/fetch exception into a friendly, actionable hint for the
     * metabox search box. Reuses Affiliate Egg's own IngestClassifier +
     * Recommendations when available (feature-detected for version-mix safety),
     * so hints stay in sync with what Affiliate Egg shows in its own UI.
     */
    private function buildSearchErrorMessage(\Exception $e, $url_type, $lead = null)
    {
        $code = (int) $e->getCode();
        $message = trim((string) $e->getMessage());

        $has_hints = class_exists('\Keywordrush\AffiliateEgg\IngestClassifier')
            && class_exists('\Keywordrush\AffiliateEgg\Recommendations');

        // Timeout (case 7): clearer and more actionable than a generic "could not connect".
        if (in_array($code, array(408, 504), true) || preg_match('/timed out|timeout|curl error 28/i', $message))
        {
            $lead_html = ($lead !== null) ? esc_html($lead) : esc_html__('The store took too long to respond.', 'content-egg');
            $html = '<strong>' . $lead_html . '</strong> '
                . esc_html__('Try again, or enable a scraping service for faster, more reliable fetches.', 'content-egg');

            if ($has_hints && ($url = \Keywordrush\AffiliateEgg\Recommendations::actionUrl('blocked')))
                $html .= ' <a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">'
                    . esc_html__('Enable a scraping service', 'content-egg') . ' &rarr;</a>';

            if ($message !== '')
                $html .= '<br><small>' . esc_html(wp_strip_all_tags($message)) . '</small>';

            return $html;
        }

        // Older Affiliate Egg without the hint classes: keep prior behavior.
        if (!$has_hints)
        {
            if (in_array($code, array(503, 403)))
                $message .= '. For more information please refer to https://ce-docs.keywordrush.com/modules/affiliate-egg-integration#avoid-getting-blocked';

            $message = esc_html(wp_strip_all_tags($message));
            return ($lead !== null) ? '<strong>' . esc_html($lead) . '</strong> ' . $message : $message;
        }

        $category = \Keywordrush\AffiliateEgg\IngestClassifier::fromException($code, $message, $url_type);
        $label = \Keywordrush\AffiliateEgg\Recommendations::label($category);
        $hint = \Keywordrush\AffiliateEgg\Recommendations::text($category);
        $action_url = \Keywordrush\AffiliateEgg\Recommendations::actionUrl($category);

        // A caller-supplied lead (e.g. "Found N products, but none could be read.")
        // replaces the category label as the bold opener.
        $lead_html = ($lead !== null) ? esc_html($lead) : esc_html($label) . '.';
        $html = '<strong>' . $lead_html . '</strong> ' . esc_html($hint);

        if ($action_url)
        {
            $link_label = ($category === 'blocked')
                ? __('Enable a scraping service', 'content-egg')
                : __('Open extractor settings', 'content-egg');

            $html .= ' <a href="' . esc_url($action_url) . '" target="_blank" rel="noopener noreferrer">'
                . esc_html($link_label) . ' &rarr;</a>';
        }

        // Raw technical reason, de-emphasized, for support. Only worth showing
        // when it adds detail the friendly hint lacks (HTTP status, transport
        // error); for other categories the hint already says everything.
        if ($message !== '' && in_array($category, array('blocked', 'network'), true))
            $html .= '<br><small>' . esc_html(wp_strip_all_tags($message)) . '</small>';

        return $html;
    }

    /**
     * Build a soft, user-facing search notice (guidance, not an error) shown as
     * an info notice in the metabox. Returns safe HTML (rendered via ng-bind-html).
     */
    private function buildSearchNotice($key, $keyword = '')
    {
        switch ($key)
        {
            case 'wrong_domain':
                return '<strong>' . esc_html__('That URL is for a different store.', 'content-egg') . '</strong> '
                    . esc_html(sprintf(__('Enter a URL from %s, or a keyword to search.', 'content-egg'), $this->getShopHost()));

            case 'no_search_registered':
                return '<strong>' . esc_html__('This store doesn\'t support keyword search.', 'content-egg') . '</strong> '
                    . esc_html__('Enter a direct product or category URL instead.', 'content-egg');

            case 'no_search_custom':
                $settings_url = admin_url('admin.php?page=' . $this->getConfigInstance()->page_slug());
                return '<strong>' . esc_html__('Keyword search isn\'t set up for this store.', 'content-egg') . '</strong> '
                    . esc_html__('Add a Search URL in this module\'s settings to search by keyword — or enter a product or category URL.', 'content-egg')
                    . ' <a href="' . esc_url($settings_url) . '" target="_blank" rel="noopener noreferrer">'
                    . esc_html__('Open module settings', 'content-egg') . ' &rarr;</a>';

            case 'no_results':
                return '<strong>' . esc_html(sprintf(__('No products found for "%s".', 'content-egg'), $keyword)) . '</strong> '
                    . esc_html__('Some stores load search results with JavaScript, which can\'t be read here — try entering a direct product URL instead.', 'content-egg');
        }

        return '';
    }

    private function prepareResults($results)
    {
        $data = array();
        $deeplink = $this->config('deeplink');

        foreach ($results as $key => $r)
        {
            $content = new ContentProduct;
            $content->unique_id = md5($r['orig_url']);

            $content->orig_url = $r['orig_url'];
            $content->domain = TextHelper::getHostName($r['orig_url']);
            //$content->merchant = TemplateHelper::getNameFromDomain($content->domain);
            $content->img = $r['img'];
            if (!empty($r['orig_img_large']))
                $content->img_large = $r['orig_img_large'];

            $content->title = $r['title'];
            $content->description = $r['description'];
            $content->price = $r['price'];
            $content->priceOld = $r['old_price'];
            $content->currencyCode = $r['currency'];
            $content->currency = TextHelper::currencyTyping($content->currencyCode);
            $content->manufacturer = $r['manufacturer'];
            $content->availability = $r['in_stock'];

            if ($r['in_stock'])
            {
                $content->stock_status = ContentProduct::STOCK_STATUS_IN_STOCK;
            }
            else
            {
                $content->stock_status = ContentProduct::STOCK_STATUS_OUT_OF_STOCK;
            }

            $content->extra = new ExtraDataAE;

            if (isset($r['extra']['ratingDecimal']))
                $content->ratingDecimal = $r['extra']['ratingDecimal'];

            if (isset($r['extra']['rating']))
            {
                $content->rating = $r['extra']['rating'];
                if (!$content->ratingDecimal)
                    $content->rating = $content->rating;

                unset($r['extra']['rating']);
            }

            if (isset($r['extra']['features']))
            {
                foreach ($r['extra']['features'] as $f)
                {
                    $feature = array(
                        'name' => $f['name'],
                        'value' => $f['value'],
                    );

                    if (isset($f['group']))
                        $feature['group'] = $f['group'];

                    $content->features[] = $feature;
                }
                unset($r['extra']['features']);
            }
            if (isset($r['extra']['comments']))
            {
                $content->extra->comments = $r['extra']['comments'];
                unset($r['extra']['comments']);
            }
            if (isset($r['extra']['images']))
            {
                $content->images = $r['extra']['images'];
                $content->extra->images = $r['extra']['images'];
                unset($r['extra']['images']);
            }

            if (isset($r['extra']['category']))
            {
                $content->category = $r['extra']['category'];
                unset($r['extra']['category']);
            }

            if (isset($r['extra']['ratingCount']))
            {
                $content->reviewsCount = (int) $r['extra']['ratingCount'];
            }

            if (isset($r['extra']['categoryPath']))
            {
                $content->categoryPath = $r['extra']['categoryPath'];
                unset($r['extra']['categoryPath']);
            }

            if (isset($r['extra']['sku']))
            {
                $content->sku = $r['extra']['sku'];
                unset($r['extra']['sku']);
            }

            if (isset($r['extra']['gtin']))
            {
                $content->ean = $r['extra']['gtin'];
                unset($r['extra']['gtin']);
            }

            $content->extra->data = $r['extra'];

            // must be after extra field
            $content->url = LinkHandler::createAffUrl($r['orig_url'], $deeplink, (array) $content);

            $data[] = $content;
        }

        return $data;
    }

    public function doRequestItems(array $items)
    {
        $key = 0;
        $product_sleep = \Keywordrush\AffiliateEgg\GeneralConfig::getInstance()->option('product_update_sleep');
        foreach ($items as $i => $item)
        {
            if ($product_sleep && $key > 0)
            {
                usleep($product_sleep);
            }
            $key++;

            try
            {
                $r = ParserManager::getInstance()->parseProduct($item['orig_url']);
            }
            catch (\Exception $e)
            {
                if ($e->getCode() == 404 || $e->getCode() == 410)
                {
                    $items[$i]['stock_status'] = ContentProduct::STOCK_STATUS_OUT_OF_STOCK;
                    $items[$i]['availability'] = $items[$i]['stock_status'];
                }
                continue;
            }

            $items[$i]['price'] = $r['price'];
            $items[$i]['priceOld'] = $r['old_price'];
            $items[$i]['currencyCode'] = $r['currency'];
            $items[$i]['currency'] = TextHelper::currencyTyping($items[$i]['currencyCode']);
            $items[$i]['availability'] = $r['in_stock'];
            if ($r['in_stock'])
            {
                $items[$i]['stock_status'] = ContentProduct::STOCK_STATUS_IN_STOCK;
            }
            else
            {
                $items[$i]['stock_status'] = ContentProduct::STOCK_STATUS_OUT_OF_STOCK;
            }
            if (isset($r['rating']))
            {
                $items[$i]['rating'] = $r['rating'];
            }

            // update url if deeplink changed
            $items[$i]['url'] = LinkHandler::createAffUrl($r['orig_url'], $this->config('deeplink'), $item);

            // update image (amazon)
            if (!$this->config('save_img') && $r['img'])
            {
                $items[$i]['img'] = $r['img'];
            }

            $items[$i] = \apply_filters('cegg_ae_module_product_update', $items[$i], $r);
        }

        return $items;
    }

    public function presavePrepare($data, $post_id)
    {
        $data = parent::presavePrepare($data, $post_id);

        if ($post_id > 0 && $this->config('reviews_as_comments'))
        {
            // get reviews from module data
            $comments = ContentManager::getNormalizedReviews($data);
            if ($comments)
            {
                // save reviews as post comments
                ContentManager::saveReviewsAsComments($post_id, $comments);

                // remove reviews from module data
                $data = ContentManager::removeReviews($data);
            }
        }

        return $data;
    }

    public function viewDataPrepare($data)
    {
        $deeplink = $this->config('deeplink');
        foreach ($data as $key => $d)
        {
            $data[$key]['url'] = LinkHandler::createAffUrl($d['orig_url'], $deeplink, $d);
        }

        return parent::viewDataPrepare($data);
    }

    public function renderResults()
    {
        PluginAdmin::render('_metabox_results', array('module_id' => $this->getId()));
    }

    public function renderSearchResults()
    {
        PluginAdmin::render('_metabox_search_results', array('module_id' => $this->getId()));
    }
}
