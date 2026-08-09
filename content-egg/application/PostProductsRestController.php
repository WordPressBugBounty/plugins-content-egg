<?php

namespace ContentEgg\application;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\components\ai\AiProcessor;
use ContentEgg\application\components\ContentManager;
use ContentEgg\application\components\FeaturedImage;
use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\components\ParserModule;
use ContentEgg\application\components\ProductDataService;
use ContentEgg\application\components\UpdateSettingsService;
use ContentEgg\application\helpers\AdminHelper;
use ContentEgg\application\helpers\ClickStatsHelper;
use ContentEgg\application\helpers\TemplateHelper;
use ContentEgg\application\models\LinkClicksDailyModel;

/**
 * PostProductsRestController class file
 *
 * Write-through product data endpoints for the editor Product Manager UI.
 * All writes go through ProductDataService -> ContentManager::saveData().
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class PostProductsRestController extends \WP_REST_Controller
{
    protected $namespace = 'cegg/v1';

    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance == null)
            self::$instance = new self;

        return self::$instance;
    }

    private function __construct()
    {
    }

    public function init()
    {
        \add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes()
    {
        \register_rest_route(
            $this->namespace,
            '/posts/(?P<post_id>[\d]+)/products',
            array(
                array(
                    'methods' => \WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_items'),
                    'permission_callback' => array($this, 'permissions_check'),
                ),
                array(
                    'methods' => \WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'add_items'),
                    'permission_callback' => array($this, 'permissions_check'),
                    'args' => array(
                        'module_id' => array('type' => 'string', 'required' => true),
                        'items' => array('type' => 'array', 'required' => true),
                        'keyword' => array('type' => 'string', 'default' => ''),
                        'revision' => array('type' => 'string', 'default' => ''),
                    ),
                ),
                array(
                    'methods' => \WP_REST_Server::DELETABLE,
                    'callback' => array($this, 'delete_all'),
                    'permission_callback' => array($this, 'permissions_check'),
                    'args' => array(
                        'module_id' => array('type' => 'string', 'default' => ''),
                    ),
                ),
            )
        );

        \register_rest_route(
            $this->namespace,
            '/posts/(?P<post_id>[\d]+)/products/order',
            array(
                array(
                    'methods' => \WP_REST_Server::EDITABLE,
                    'callback' => array($this, 'reorder'),
                    'permission_callback' => array($this, 'permissions_check'),
                    'args' => array(
                        'order' => array('type' => 'array', 'default' => array()),
                    ),
                ),
            )
        );

        \register_rest_route(
            $this->namespace,
            '/posts/(?P<post_id>[\d]+)/products/bulk',
            array(
                array(
                    'methods' => \WP_REST_Server::EDITABLE,
                    'callback' => array($this, 'bulk_update'),
                    'permission_callback' => array($this, 'permissions_check'),
                    'args' => array(
                        'targets' => array('type' => 'array', 'default' => array()),
                        'fields' => array('type' => 'object', 'default' => array()),
                    ),
                ),
                array(
                    'methods' => \WP_REST_Server::DELETABLE,
                    'callback' => array($this, 'bulk_delete'),
                    'permission_callback' => array($this, 'permissions_check'),
                    'args' => array(
                        'targets' => array('type' => 'array', 'default' => array()),
                    ),
                ),
            )
        );

        \register_rest_route(
            $this->namespace,
            '/posts/(?P<post_id>[\d]+)/products/(?P<module_id>[a-zA-Z0-9_\-]+)/(?P<unique_id>[^/]+)',
            array(
                array(
                    'methods' => \WP_REST_Server::EDITABLE,
                    'callback' => array($this, 'update_item'),
                    'permission_callback' => array($this, 'permissions_check'),
                    'args' => array(
                        'fields' => array('type' => 'object', 'default' => array()),
                        'revision' => array('type' => 'string', 'default' => ''),
                    ),
                ),
                array(
                    'methods' => \WP_REST_Server::DELETABLE,
                    'callback' => array($this, 'delete_item'),
                    'permission_callback' => array($this, 'permissions_check'),
                    'args' => array(
                        'revision' => array('type' => 'string', 'default' => ''),
                    ),
                ),
            )
        );

        \register_rest_route(
            $this->namespace,
            '/posts/(?P<post_id>[\d]+)/refresh',
            array(
                array(
                    'methods' => \WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'refresh'),
                    'permission_callback' => array($this, 'permissions_check'),
                    'args' => array(
                        'type' => array('type' => 'string', 'required' => true),
                    ),
                ),
            )
        );

        \register_rest_route(
            $this->namespace,
            '/posts/(?P<post_id>[\d]+)/ai',
            array(
                array(
                    'methods' => \WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'ai'),
                    'permission_callback' => array($this, 'permissions_check'),
                    'args' => array(
                        'module_id' => array('type' => 'string', 'required' => true),
                        'items' => array('type' => 'array', 'required' => true),
                        'title_method' => array('type' => 'string', 'default' => ''),
                        'description_method' => array('type' => 'string', 'default' => ''),
                    ),
                ),
            )
        );

        \register_rest_route(
            $this->namespace,
            '/posts/(?P<post_id>[\d]+)/smart-groups',
            array(
                array(
                    'methods' => \WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'smart_groups'),
                    'permission_callback' => array($this, 'permissions_check'),
                    'args' => array(
                        'method' => array('type' => 'string', 'required' => true),
                        'targets' => array('type' => 'array', 'default' => array()),
                    ),
                ),
            )
        );

        \register_rest_route(
            $this->namespace,
            '/posts/(?P<post_id>[\d]+)/products/keyword-status',
            array(
                array(
                    'methods' => \WP_REST_Server::READABLE,
                    'callback' => array($this, 'keyword_status'),
                    'permission_callback' => array($this, 'permissions_check'),
                ),
            )
        );

        \register_rest_route(
            $this->namespace,
            '/posts/(?P<post_id>[\d]+)/update-settings',
            array(
                array(
                    'methods' => \WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_update_settings'),
                    'permission_callback' => array($this, 'permissions_check'),
                ),
                array(
                    'methods' => \WP_REST_Server::EDITABLE,
                    'callback' => array($this, 'put_update_settings'),
                    'permission_callback' => array($this, 'permissions_check'),
                    'args' => array(
                        'global_keyword' => array('type' => 'string', 'required' => false),
                        'modules' => array('type' => 'object', 'required' => false),
                        'family' => array('type' => 'string', 'required' => false),
                    ),
                ),
            )
        );

        \register_rest_route(
            $this->namespace,
            '/posts/(?P<post_id>[\d]+)/woo-flag',
            array(
                array(
                    'methods'             => \WP_REST_Server::EDITABLE,
                    'callback'            => array($this, 'set_woo_flag'),
                    'permission_callback' => array($this, 'permissions_check'),
                    'args'                => array(
                        'module_id' => array('type' => 'string', 'required' => true),
                        'unique_id' => array('type' => 'string', 'required' => true),
                        'field'     => array('type' => 'string', 'required' => true),
                        'value'     => array(
                            'type'              => 'boolean',
                            'required'          => true,
                            'sanitize_callback' => 'rest_sanitize_boolean',
                        ),
                    ),
                ),
            )
        );

        \register_rest_route(
            $this->namespace,
            '/posts/(?P<post_id>[\d]+)/featured-image',
            array(
                array(
                    'methods'             => \WP_REST_Server::CREATABLE,
                    'callback'            => array($this, 'set_featured_image'),
                    'permission_callback' => array($this, 'permissions_check'),
                    'args'                => array(
                        'module_id' => array('type' => 'string', 'required' => true),
                        'unique_id' => array('type' => 'string', 'required' => true),
                    ),
                ),
            )
        );
    }

    public function permissions_check($request)
    {
        $post_id = (int) $request['post_id'];

        if (!\get_post($post_id))
            return new \WP_Error('cegg_not_found', \__('Invalid post ID.', 'content-egg'), array('status' => 404));

        if (!in_array(\get_post_type($post_id), (array) GeneralConfig::getInstance()->option('post_types')))
            return new \WP_Error('cegg_invalid_post_type', \__('Content Egg is not enabled for this post type.', 'content-egg'), array('status' => 403));

        return \current_user_can('edit_post', $post_id);
    }

    /**
     * Force a stored media item (module_id + unique_id) as the post's featured
     * image, overriding the automatic selection. Reuses FeaturedImage::forceSet,
     * so it honors the user's local/external featured-image and save-to-server
     * settings. Returns the resulting attachment id (local) + thumbnail url.
     */
    public function set_featured_image($request)
    {
        $post_id = (int) $request['post_id'];
        $module_id = \sanitize_text_field($request['module_id']);
        $unique_id = \sanitize_text_field($request['unique_id']);

        if (!\post_type_supports(\get_post_type($post_id), 'thumbnail'))
            return new \WP_Error('cegg_no_thumbnail_support', \__('This post type does not support a featured image.', 'content-egg'), array('status' => 400));

        if (!$this->valid_module($module_id))
            return new \WP_Error('cegg_invalid_module', \__('Unknown or inactive module.', 'content-egg'), array('status' => 404));

        $item = null;
        foreach ((array) ContentManager::getData($post_id, $module_id) as $d)
        {
            if (isset($d['unique_id']) && (string) $d['unique_id'] === (string) $unique_id)
            {
                $item = $d;
                break;
            }
        }
        if (!$item)
            return new \WP_Error('cegg_item_not_found', \__('Media item not found on this post.', 'content-egg'), array('status' => 404));

        if (empty($item['img']) && empty($item['img_large']))
            return new \WP_Error('cegg_no_image', \__('This item has no image.', 'content-egg'), array('status' => 400));

        if (!FeaturedImage::forceSet($post_id, $item))
            return new \WP_Error('cegg_featured_failed', \__('Could not set the featured image.', 'content-egg'), array('status' => 500));

        $external = GeneralConfig::getInstance()->option('external_featured_images') != 'disabled';
        if ($external)
        {
            $thumbnail_url = !empty($item['img_large']) ? $item['img_large'] : $item['img'];
            $attachment_id = 0;
        }
        else
        {
            $attachment_id = (int) \get_post_thumbnail_id($post_id);
            $thumbnail_url = $attachment_id
                ? (\wp_get_attachment_image_url($attachment_id, 'medium') ?: (string) \wp_get_attachment_url($attachment_id))
                : '';
        }

        return \rest_ensure_response(array(
            'post_id' => $post_id,
            'external' => (bool) $external,
            'attachment_id' => (int) $attachment_id,
            'thumbnail_url' => (string) $thumbnail_url,
        ));
    }

    public function get_items($request)
    {
        return \rest_ensure_response($this->collect_products((int) $request['post_id']));
    }

    /**
     * Build the editor products payload for a post: per-module item lists (with
     * server-formatted prices), per-module revisions, and the distinct group
     * names. Shared by GET /products and the AI/Smart-Groups responses.
     */
    private function collect_products($post_id)
    {
        $by_module = array();
        $revisions = array();
        $groups = array();

        $statsEnabled = ClickStatsHelper::isEnabled();
        $clickIndex = $statsEnabled
            ? ProductDataService::indexClickAggregates(
                LinkClicksDailyModel::model()->aggregatesForPost($post_id)
            )
            : array();

        // Managed families (product + coupon): one store spans both, and the
        // client filters each family's roster by module type.
        foreach ($this->managed_module_ids() as $module_id)
        {
            $data = ContentManager::getData($post_id, $module_id);

            if (!is_array($data) || !$data)
                continue;

            $items = array_values($data);
            foreach ($items as &$item)
            {
                if (!is_array($item))
                    continue;

                $item['_clicks_30d'] = 0;
                $item['_clicks'] = 0;
                if ($statsEnabled && !empty($item['unique_id']))
                {
                    $ckey = $module_id . '|' . $item['unique_id'];
                    if (isset($clickIndex[$ckey]))
                    {
                        $item['_clicks_30d'] = $clickIndex[$ckey]['d30'];
                        $item['_clicks'] = $clickIndex[$ckey]['total'];
                    }
                }

                // Format prices with the canonical server helper — the same one
                // search results and front-end templates use — so the editor
                // shows prices identically to the site (symbol + i18n separators).
                $currency = $item['currencyCode'] ?? '';
                if (!empty($item['price']))
                    $item['_priceFormatted'] = TemplateHelper::formatPriceCurrency($item['price'], $currency);
                if (!empty($item['priceOld']))
                    $item['_priceOldFormatted'] = TemplateHelper::formatPriceCurrency($item['priceOld'], $currency);

                if (!empty($item['group']) && !in_array($item['group'], $groups, true))
                    $groups[] = $item['group'];
            }
            unset($item);

            $by_module[$module_id] = $items;
            $revisions[$module_id] = ProductDataService::revision($post_id, $module_id);
        }

        return array(
            'by_module' => (object) $by_module,
            'revisions' => (object) $revisions,
            'groups' => $groups,
        );
    }

    public function add_items($request)
    {
        $post_id = (int) $request['post_id'];
        $module_id = \sanitize_text_field($request['module_id']);

        // Auto-activate the manual-entry module (Offer) on first use: "Add
        // product" targets it, so adding a product to it is an explicit request
        // to use it. Scoped to non-searchable product modules — a searchable
        // module (Amazon, a feed) never auto-enables this way.
        $mm = ModuleManager::getInstance();
        if ($mm->moduleExists($module_id) && !$mm->isModuleActive($module_id))
        {
            $candidate = $mm->factory($module_id);
            if ($candidate->isParser()
                && in_array($candidate->getParserType(), self::MANAGED_TYPES, true)
                && !$candidate->isSearchable())
            {
                $mm->activateModule($module_id);
            }
        }

        if (!$this->valid_module($module_id))
            return new \WP_Error('cegg_invalid_module', \__('Unknown or inactive module.', 'content-egg'), array('status' => 404));

        $items = $request['items'];
        if (!is_array($items))
            $items = array();

        $items = array_values(array_filter($items, 'is_array'));

        if (!$items)
            return new \WP_Error('cegg_invalid_item', \__('Items must be a non-empty array of objects.', 'content-egg'), array('status' => 400));

        // Manual-entry modules (Offer) receive editor-authored items — sanitize
        // each through the same field whitelist as an edit so nothing unsafe is
        // stored. Search-added items come from parsers and are left untouched.
        $module = ModuleManager::factory($module_id);
        if (!$module->isSearchable())
        {
            // Editor-authored items pass through the module's family whitelist so
            // a manual coupon keeps code/dates and a manual product keeps price.
            $allowed = ProductDataService::whitelistForModule($module_id);
            $items = array_map(function ($item) use ($allowed)
            {
                $clean = ProductDataService::applyFieldUpdates(array(), $item, $allowed);
                // Preserve the identity keys applyFieldUpdates deliberately drops.
                if (!empty($item['unique_id']))
                    $clean['unique_id'] = \sanitize_text_field((string) $item['unique_id']);
                return $clean;
            }, $items);

            // Drop rows the client shouldn't have sent. A manual product needs a
            // title AND a URL — without a URL it can't produce an affiliate link
            // and is silently discarded deeper in the save path, so reject it
            // here with a clear error instead.
            $items = array_values(array_filter($items, function ($item)
            {
                return !empty($item['unique_id'])
                    && isset($item['title']) && $item['title'] !== ''
                    && (!empty($item['orig_url']) || !empty($item['url']));
            }));

            if (!$items)
                return new \WP_Error('cegg_invalid_item', \__('A product title and URL are required.', 'content-egg'), array('status' => 400));
        }

        $revision = (string) $request['revision'];
        if ($revision && $revision !== ProductDataService::revision($post_id, $module_id))
            return new \WP_Error('cegg_conflict', \__('Product data changed since it was loaded. Reload and try again.', 'content-egg'), array('status' => 409));

        $keyword = \sanitize_text_field((string) $request['keyword']);

        $result = ProductDataService::addItems($post_id, $module_id, $items, $keyword);

        return \rest_ensure_response(array(
            'items' => array_values($result['data']),
            'added' => $result['added'],
            'revision' => ProductDataService::revision($post_id, $module_id),
        ));
    }

    /**
     * Parser-type families this endpoint manages. Products and coupons share the
     * roster/edit engine (a coupon is a product with a different field set);
     * media is not managed here.
     */
    const MANAGED_TYPES = array(
        ParserModule::PARSER_TYPE_PRODUCT,
        ParserModule::PARSER_TYPE_COUPON,
        ParserModule::PARSER_TYPE_IMAGE,
        ParserModule::PARSER_TYPE_VIDEO,
    );

    private function valid_module($module_id)
    {
        // Managed families only (product or coupon); reject content/media modules.
        $mm = ModuleManager::getInstance();
        if (!$mm->moduleExists($module_id) || !$mm->isModuleActive($module_id))
            return false;

        $module = $mm->factory($module_id);
        return $module->isParser()
            && in_array($module->getParserType(), self::MANAGED_TYPES, true);
    }

    /** Active parser-module ids across every managed family (product + coupon). */
    private function managed_module_ids()
    {
        return ModuleManager::getInstance()->getParserModuleIdsByTypes(self::MANAGED_TYPES, true);
    }

    private function conflict($post_id, $module_id, $revision)
    {
        if ($revision && $revision !== ProductDataService::revision($post_id, $module_id))
            return new \WP_Error('cegg_conflict', \__('Product data changed since it was loaded. Reload and try again.', 'content-egg'), array('status' => 409));

        return null;
    }

    public function update_item($request)
    {
        $post_id = (int) $request['post_id'];
        $module_id = \sanitize_text_field($request['module_id']);
        // Decode the path segment: some setups deliver the route param still
        // percent-encoded, so a unique_id containing e.g. "|" (eBay: v1|123|0)
        // arrives as "v1%7C123%7C0" and never matches the stored id. Idempotent —
        // these ids carry no literal "%".
        $unique_id = \rawurldecode((string) $request['unique_id']);

        if (!$this->valid_module($module_id))
            return new \WP_Error('cegg_invalid_module', \__('Unknown or inactive module.', 'content-egg'), array('status' => 404));

        if ($conflict = $this->conflict($post_id, $module_id, (string) $request['revision']))
            return $conflict;

        $fields = is_array($request['fields']) ? $request['fields'] : array();

        // Edits are filtered by the module's family whitelist — coupon fields on
        // a coupon, product fields on a product; cross-family keys are dropped.
        $allowed = ProductDataService::whitelistForModule($module_id);
        $result = ProductDataService::updateItem($post_id, $module_id, $unique_id, $fields, $allowed);

        if (!$result['updated'])
            return new \WP_Error('cegg_not_found', \__('Product not found.', 'content-egg'), array('status' => 404));

        $item = null;
        foreach ($result['data'] as $row)
        {
            if ((string) ($row['unique_id'] ?? '') === $unique_id)
            {
                $item = $row;
                break;
            }
        }

        return \rest_ensure_response(array(
            'item' => $item,
            'items' => $result['data'],
            'revision' => ProductDataService::revision($post_id, $module_id),
        ));
    }

    public function delete_item($request)
    {
        $post_id = (int) $request['post_id'];
        $module_id = \sanitize_text_field($request['module_id']);
        // See update_item: decode the path param so percent-encoded ids (e.g. "|")
        // match the stored unique_id.
        $unique_id = \rawurldecode((string) $request['unique_id']);

        if (!$this->valid_module($module_id))
            return new \WP_Error('cegg_invalid_module', \__('Unknown or inactive module.', 'content-egg'), array('status' => 404));

        if ($conflict = $this->conflict($post_id, $module_id, (string) $request['revision']))
            return $conflict;

        $result = ProductDataService::removeItem($post_id, $module_id, $unique_id);

        return \rest_ensure_response(array(
            'items' => $result['data'],
            'revision' => ProductDataService::revision($post_id, $module_id),
        ));
    }

    public function delete_all($request)
    {
        $post_id = (int) $request['post_id'];
        $module_id = \sanitize_text_field((string) $request['module_id']);

        if ($module_id && !$this->valid_module($module_id))
            return new \WP_Error('cegg_invalid_module', \__('Unknown or inactive module.', 'content-egg'), array('status' => 404));

        ProductDataService::removeAll($post_id, $module_id);

        return \rest_ensure_response(array('deleted' => true));
    }

    public function bulk_delete($request)
    {
        $post_id = (int) $request['post_id'];
        $targets = $this->valid_targets($request['targets']);

        if ($targets)
            ProductDataService::removeItems($post_id, $targets);

        return \rest_ensure_response(array('ok' => true, 'removed' => count($targets)));
    }

    public function bulk_update($request)
    {
        $post_id = (int) $request['post_id'];
        $targets = $this->valid_targets($request['targets']);
        $fields = is_array($request['fields']) ? $request['fields'] : array();

        if ($targets && $fields)
            ProductDataService::updateItems($post_id, $targets, $fields);

        return \rest_ensure_response(array('ok' => true, 'updated' => count($targets)));
    }

    public function set_woo_flag($request)
    {
        $post_id = (int) $request['post_id'];

        if (\get_post_type($post_id) !== 'product')
            return new \WP_Error('cegg_not_woo', \__('WooCommerce sync is only available on product posts.', 'content-egg'), array('status' => 400));

        $module_id = (string) $request['module_id'];
        $unique_id = (string) $request['unique_id'];
        $field     = (string) $request['field'];
        $value     = (bool) $request['value'];

        if (!in_array($field, ProductDataService::WOO_FLAG_FIELDS, true))
            return new \WP_Error('cegg_invalid_field', \__('Invalid field.', 'content-egg'), array('status' => 400));

        if (!$this->valid_module($module_id))
            return new \WP_Error('cegg_not_found', \__('Unknown module.', 'content-egg'), array('status' => 404));

        ProductDataService::setExclusiveWooFlag($post_id, $module_id, $unique_id, $field, $value);

        // Re-sync the WooCommerce product right away so toggling the switch takes
        // effect immediately, instead of waiting for the next data save / update.
        // wooHandler re-evaluates the post's woo_sync/woo_attr flags (or the
        // automatic-sync fallback when a flag is turned off) and saves the product.
        // Guarded so a sync hiccup can never lose the flag change we just wrote.
        try
        {
            WooIntegrator::wooHandler(array(), $module_id, $post_id, true);
        }
        catch (\Throwable $e)
        {
        }

        return \rest_ensure_response($this->collect_products($post_id));
    }

    /**
     * Keep only well-formed targets on valid product modules — the same module
     * guard the single-item write endpoints apply, over a batch.
     */
    private function valid_targets($raw)
    {
        if (!is_array($raw))
            return array();

        $out = array();
        foreach ($raw as $t)
        {
            if (!is_array($t))
                continue;
            $mid = \sanitize_text_field((string) ($t['module_id'] ?? ''));
            $uid = (string) ($t['unique_id'] ?? '');
            if ($mid === '' || $uid === '' || !$this->valid_module($mid))
                continue;
            $out[] = array('module_id' => $mid, 'unique_id' => $uid);
        }

        return $out;
    }

    public function reorder($request)
    {
        $post_id = (int) $request['post_id'];
        $order = is_array($request['order']) ? $request['order'] : array();

        ProductDataService::reorder($post_id, $order);

        return \rest_ensure_response(array('ok' => true));
    }

    /**
     * Whether a listings (keyword) update would actually do anything: it runs
     * per affiliate module and pulls each module's autoupdate keyword (the
     * module-specific keyword, else the post's global autoupdate keyword). If no
     * module resolves a keyword there's nothing to update, so the UI disables the
     * action. Mirrors ContentManager::updateAllByKeyword()'s module iteration.
     */
    public function keyword_status($request)
    {
        $post_id = (int) $request['post_id'];

        $available = false;
        foreach (array_keys(ModuleManager::getInstance()->getAffiliteModulesList(true)) as $module_id)
        {
            if (ContentManager::getAutoupdateKeyword($post_id, $module_id))
            {
                $available = true;
                break;
            }
        }

        return \rest_ensure_response(array('available' => $available));
    }

    public function get_update_settings($request)
    {
        $family = $request->get_param('family');

        return \rest_ensure_response(
            UpdateSettingsService::getUpdateSettings(
                (int) $request['post_id'],
                $family ? \sanitize_text_field((string) $family) : null
            )
        );
    }

    public function put_update_settings($request)
    {
        $modules = $request->get_param('modules');
        $family = $request->get_param('family');

        return \rest_ensure_response(
            UpdateSettingsService::saveUpdateSettings(
                (int) $request['post_id'],
                $request->has_param('global_keyword') ? $request->get_param('global_keyword') : null,
                is_array($modules) ? $modules : array(),
                $family ? \sanitize_text_field((string) $family) : null
            )
        );
    }

    public function refresh($request)
    {
        $post_id = (int) $request['post_id'];
        $type = \sanitize_text_field((string) $request['type']);

        try
        {
            ProductDataService::refresh($post_id, $type);
        }
        catch (\InvalidArgumentException $e)
        {
            return new \WP_Error('cegg_invalid_request', $e->getMessage(), array('status' => 400));
        }

        return \rest_ensure_response(array('ok' => true));
    }

    public function ai($request)
    {
        $post_id = (int) $request['post_id'];
        $module_id = \sanitize_text_field($request['module_id']);

        if (!$this->valid_module($module_id))
            return new \WP_Error('cegg_invalid_module', \__('Unknown or inactive module.', 'content-egg'), array('status' => 404));

        if (!AdminHelper::isAiEnabled())
            return new \WP_Error('cegg_ai_disabled', \__('AI is not configured.', 'content-egg'), array('status' => 400));

        $title_method = \sanitize_text_field((string) $request['title_method']);
        $description_method = \sanitize_text_field((string) $request['description_method']);

        if ($title_method === '' && $description_method === '')
            return new \WP_Error('cegg_invalid_request', \__('No AI method specified.', 'content-egg'), array('status' => 400));

        $items = is_array($request['items']) ? array_values(array_filter($request['items'], 'is_array')) : array();
        if (!$items)
            return new \WP_Error('cegg_invalid_item', \__('Items must be a non-empty array of objects.', 'content-egg'), array('status' => 400));

        // Capture the pre-op stored values (title/subtitle/description) so undo
        // can restore exactly what was saved before this AI run.
        $stored = ContentManager::getData($post_id, $module_id);
        $stored = is_array($stored) ? $stored : array();
        $stored_by_id = array();
        foreach ($stored as $it)
        {
            if (is_array($it) && isset($it['unique_id']))
                $stored_by_id[(string) $it['unique_id']] = $it;
        }

        $previous = array();
        foreach ($items as $it)
        {
            $uid = (string) ($it['unique_id'] ?? '');
            if ($uid === '' || !isset($stored_by_id[$uid]))
                continue;
            $s = $stored_by_id[$uid];
            $previous[] = array(
                'module_id' => $module_id,
                'unique_id' => $uid,
                'fields' => array(
                    'title' => $s['title'] ?? '',
                    'subtitle' => $s['subtitle'] ?? '',
                    'description' => $s['description'] ?? '',
                ),
            );
        }

        try
        {
            $result = AiProcessor::applayAiItems($items, $title_method, $description_method);
        }
        catch (\Throwable $e)
        {
            return \rest_ensure_response(array(
                'items' => array(),
                'previous' => array(),
                'error' => \wp_strip_all_tags($e->getMessage()),
            ));
        }

        $saved = ProductDataService::applyItemFields($post_id, $module_id, $result, array('title', 'subtitle', 'description'));

        return \rest_ensure_response(array(
            'items' => array_values($saved),
            'previous' => $previous,
            'error' => '',
            'revision' => ProductDataService::revision($post_id, $module_id),
        ));
    }

    public function smart_groups($request)
    {
        $post_id = (int) $request['post_id'];

        if (!AdminHelper::isAiEnabled())
            return new \WP_Error('cegg_ai_disabled', \__('AI is not configured.', 'content-egg'), array('status' => 400));

        $method = \sanitize_text_field((string) $request['method']);
        if ($method === '')
            return new \WP_Error('cegg_invalid_request', \__('No Smart Groups method specified.', 'content-egg'), array('status' => 400));

        $targets = $this->valid_targets($request['targets']);
        if (!$targets)
            return new \WP_Error('cegg_invalid_item', \__('No products selected.', 'content-egg'), array('status' => 400));

        // Load only the targeted modules' stored items and keep only the
        // selected unique_ids, snapshotting each kept item's current group
        // for undo.
        $data = array();
        $previous = array();
        foreach (ProductDataService::groupTargetsByModule($targets) as $mid => $unique_ids)
        {
            $stored = ContentManager::getData($post_id, $mid);
            if (!is_array($stored) || !$stored)
                continue;

            $wanted = array_flip(array_map('strval', $unique_ids));
            $kept = array();
            foreach (array_values($stored) as $it)
            {
                if (!is_array($it) || !isset($it['unique_id']) || !isset($wanted[(string) $it['unique_id']]))
                    continue;

                $kept[] = $it;
                $previous[] = array(
                    'module_id' => $mid,
                    'unique_id' => (string) $it['unique_id'],
                    'fields' => array('group' => $it['group'] ?? ''),
                );
            }

            if ($kept)
                $data[$mid] = $kept;
        }

        if (!$data)
        {
            $payload = $this->collect_products($post_id);
            $payload['previous'] = array();
            $payload['error'] = '';
            return \rest_ensure_response($payload);
        }

        try
        {
            $result = AiProcessor::applaySmartGroups($data, $method);
        }
        catch (\Throwable $e)
        {
            return \rest_ensure_response(array(
                'previous' => array(),
                'error' => \wp_strip_all_tags($e->getMessage()),
            ));
        }

        foreach ($result as $mid => $mitems)
        {
            if (is_array($mitems))
                ProductDataService::applyItemFields($post_id, $mid, $mitems, array('group'));
        }

        $payload = $this->collect_products($post_id);
        $payload['previous'] = $previous;
        $payload['error'] = '';

        return \rest_ensure_response($payload);
    }
}
