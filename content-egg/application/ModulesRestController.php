<?php

namespace ContentEgg\application;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\feed\FeedImportPendingException;
use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\components\ModuleTemplateManager;
use ContentEgg\application\components\ParserModule;
use ContentEgg\application\components\ProductSearchService;

/**
 * ModulesRestController class file
 *
 * Editor-facing module catalog + product search (Product Manager UI).
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ModulesRestController extends \WP_REST_Controller
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
            '/modules',
            array(
                array(
                    'methods' => \WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_modules'),
                    'permission_callback' => array($this, 'permissions_check'),
                    'args' => array(
                        // Comma-separated PARSER_TYPE_* families. Defaults to
                        // PRODUCT so the existing product manager is unchanged.
                        'types' => array('type' => 'string', 'default' => ParserModule::PARSER_TYPE_PRODUCT),
                        // Edited post id, so the content_egg_metabox_modules filter
                        // can hide/show modules per-post (legacy $post parity).
                        'post_id' => array('type' => 'integer', 'default' => 0),
                    ),
                ),
            )
        );

        \register_rest_route(
            $this->namespace,
            '/search',
            array(
                array(
                    'methods' => \WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'search'),
                    'permission_callback' => array($this, 'permissions_check'),
                    'args' => array(
                        'module_id' => array('type' => 'string', 'required' => true),
                        'keyword' => array('type' => 'string', 'required' => true),
                        'filters' => array('type' => 'object', 'default' => array()),
                        'post_id' => array('type' => 'integer', 'default' => 0),
                    ),
                ),
            )
        );
    }

    public function permissions_check()
    {
        return \current_user_can('edit_posts');
    }

    /**
     * Parser-type families the editor manager can manage today. Products and
     * coupons share the same roster/search/edit engine; media is not managed
     * here (search-and-insert picker, separate flow).
     */
    const MANAGED_TYPES = array(
        ParserModule::PARSER_TYPE_PRODUCT,
        ParserModule::PARSER_TYPE_COUPON,
        ParserModule::PARSER_TYPE_IMAGE,
        ParserModule::PARSER_TYPE_VIDEO,
    );

    /**
     * Resolve the requested `types` param to a validated, non-empty list of
     * managed parser families. Unknown/blank values fall back to PRODUCT so the
     * default request behaves exactly as before.
     */
    private function resolveTypes($raw)
    {
        $requested = array_filter(array_map('trim', explode(',', (string) $raw)));
        $types = array_values(array_intersect($requested, self::MANAGED_TYPES));
        if (!$types)
            $types = array(ParserModule::PARSER_TYPE_PRODUCT);

        return $types;
    }

    /** True when $module_id is an active parser module of a managed family. */
    private function isManagedParserModule($module_id)
    {
        $mm = ModuleManager::getInstance();
        if (!$mm->moduleExists($module_id) || !$mm->isModuleActive($module_id))
            return false;

        $module = $mm->factory($module_id);
        return $module->isParser()
            && in_array($module->getParserType(), self::MANAGED_TYPES, true);
    }

    /**
     * A module's render templates as [{ id, name, isDefault }] (short ids), for
     * the coupon Shortcode builder's per-module template picker. The module's
     * defaultTemplateName() is flagged so the UI can preselect it.
     */
    private static function moduleTemplates($module)
    {
        $default = $module->defaultTemplateName();
        $list = ModuleTemplateManager::getInstance($module->getId())
            ->getTemplatesList(true, false);

        $out = array();
        foreach ($list as $id => $name)
        {
            if ($id === 'customizable')
                continue;

            $is_custom = ModuleTemplateManager::isCustomTemplate($id);
            if ($is_custom)
                $name = preg_replace('/\s*\[[^\]]*\]\s*$/u', '', $name);

            $out[] = array(
                'id'        => $id,
                'name'      => $name,
                'isDefault' => ($id === $default),
                'isCustom'  => (bool) $is_custom,
            );
        }

        return $out;
    }

    public function get_modules($request)
    {
        $result = array();

        // Parser modules for the requested families (default PRODUCT). Products
        // and coupons share this catalog; the UI scopes by the `types` it asks for.
        $types = $this->resolveTypes($request['types']);
        foreach (ModuleManager::getInstance()->getParserModulesByTypes($types, true) as $module)
        {
            $result[] = array(
                'id' => $module->getId(),
                'label' => $module->getName(),
                'parser_type' => $module->getParserType(),
                'is_affiliate' => (bool) $module->isAffiliateParser(),
                // Manual-entry modules (Offer) can't search — the Search tab
                // hides them; they're added via "Add product" instead.
                'searchable' => (bool) $module->isSearchable(),
                'priority' => (int) $module->getConfigInstance()->option('priority'),
                'url_search' => (bool) $module->isUrlSearchAllowed(),
                'gtin_search' => (bool) $module->isGtinSearchAllowed(),
                // Legacy per-module search placeholder override
                // (content_egg_keyword_input_placeholder). Only surfaced when a
                // filter actually customized it; empty otherwise so the UI keeps
                // its capability-derived hint. Honored client-side when a single
                // module is targeted (the shared search box can't show a
                // per-module hint for a mixed selection).
                'keyword_placeholder' => self::keywordPlaceholderOverride($module),
                // Price filtering is only actually applied when the module maps
                // min/max to its own query params (getPriceParamMap non-empty).
                'price_filter' => (bool) (\method_exists($module, 'getPriceParamMap')
                    ? $module->getPriceParamMap() : array()),
                'search_filters' => $module->getSearchFilters(),
                // Coupon modules have no block — they render via
                // [content-egg module=X template=Y], so the coupon Shortcode tab
                // needs each module's own template list. Products build their
                // shortcode from the shared block templates, so skip it there.
                'templates' => ($module->getParserType() === ParserModule::PARSER_TYPE_COUPON)
                    ? self::moduleTemplates($module)
                    : array(),
            );
        }

        // Sort by priority (0 = highest). Tie-break: affiliate modules first,
        // so the UI can preselect / limit the list by importance.
        \usort($result, function ($a, $b)
        {
            return array($a['priority'], !$a['is_affiliate'])
                <=> array($b['priority'], !$b['is_affiliate']);
        });

        $result = $this->applyModuleVisibilityFilter($result, (int) $request['post_id']);

        return \rest_ensure_response($result);
    }

    /**
     * Legacy per-module search-input placeholder (content_egg_keyword_input_placeholder).
     * Returns the filtered string only when a filter actually changed the default,
     * else '' so the client keeps its own capability-derived placeholder.
     */
    private static function keywordPlaceholderOverride($module)
    {
        $default = $module->isUrlSearchAllowed()
            ? \__('Keyword or Product URL', 'content-egg')
            : \__('Keyword to search', 'content-egg');

        $placeholder = \apply_filters('content_egg_keyword_input_placeholder', $default, $module->getId());

        return ($placeholder !== $default) ? (string) $placeholder : '';
    }

    /**
     * Legacy metabox module-visibility filter (content_egg_metabox_modules), ported
     * to the Product Manager. The callback receives the active module id list for the
     * requested family plus the edited post id, and the edited post is set as the
     * global $post so existing per-post ($post-aware) callbacks keep working.
     *
     * NOTE: the endpoint is queried per family (PRODUCT/COUPON/IMAGE/VIDEO), so the
     * filter runs once per family with only that family's ids — an allowlist-style
     * callback should stay family-aware, not assume the full mixed module list.
     */
    private function applyModuleVisibilityFilter(array $result, $post_id)
    {
        $module_ids = array_column($result, 'id');

        $prev_post = isset($GLOBALS['post']) ? $GLOBALS['post'] : null;
        if ($post_id && ($post = \get_post($post_id)))
            $GLOBALS['post'] = $post;

        $filtered_ids = \apply_filters('content_egg_metabox_modules', $module_ids, (int) $post_id);

        $GLOBALS['post'] = $prev_post;

        if ($filtered_ids === $module_ids)
            return $result;

        $allowed = array_map('strval', (array) $filtered_ids);
        return array_values(array_filter($result, function ($m) use ($allowed)
        {
            return in_array((string) $m['id'], $allowed, true);
        }));
    }

    public function search($request)
    {
        $module_id = \sanitize_text_field($request['module_id']);

        // Managed parser modules only (product or coupon) — matches the module
        // catalog this UI shows for either family.
        if (!$this->isManagedParserModule($module_id))
            return new \WP_Error('cegg_invalid_module', \__('Unknown or inactive module.', 'content-egg'), array('status' => 404));

        $filters = is_array($request['filters']) ? $request['filters'] : array();

        $module = ModuleManager::factory($module_id);

        // Manual-entry modules have no search backend — reject rather than run an
        // empty doRequest() (the UI already hides them from the Search tab).
        if (!$module->isSearchable())
            return new \WP_Error('cegg_not_searchable', \__('This module does not support search.', 'content-egg'), array('status' => 400));
        $allowed_keys = array_column($module->getSearchFilters(), 'key');

        $query = ProductSearchService::filterQueryKeys($filters, $allowed_keys);
        $query['keyword'] = (string) $request['keyword'];

        try
        {
            $result = ProductSearchService::search($module_id, $query);

            return \rest_ensure_response(array(
                'results' => $result['results'],
                'error' => '',
                'notice' => $result['notice'],
            ));
        }
        catch (FeedImportPendingException $e)
        {
            // Not an error: the feed catalog is still being imported.
            return \rest_ensure_response(array(
                'results' => array(),
                'error' => '',
                'notice' => $e->getMessage(),
                'feed_importing' => true,
            ));
        }
        catch (\InvalidArgumentException $e)
        {
            return new \WP_Error('cegg_invalid_request', $e->getMessage(), array('status' => 400));
        }
        catch (\Throwable $e)
        {
            // Parser/API failures use the admin-ajax envelope: HTTP 200 + error field.
            return \rest_ensure_response(array(
                'results' => array(),
                'error' => $e->getMessage(),
                'notice' => '',
            ));
        }
    }
}
