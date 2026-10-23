<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\AffiliateFeedParserModule;
use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\components\ParserModule;

/**
 * ListModulesAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class ListModulesAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/list-modules';
    }

    public function label(): string
    {
        return __('List Modules', 'content-egg');
    }

    public function description(): string
    {
        return 'Lists Content Egg modules: id, name, whether it is active, type '
            . '(product/coupon/image/video/other), whether it is a feed instance (is_feed), '
            . 'whether it is an Affiliate Egg integration shop (is_ae), and display priority. '
            . 'An is_ae module is a store connected via the Affiliate Egg plugin, e.g. through '
            . 'content-egg/connect-shop. '
            . 'Module families: product modules — including feed modules (is_feed=true) and '
            . 'Affiliate Egg shop modules (is_ae=true), which share the product workflow; '
            . 'coupon modules; and media modules (image + video). Use module_id with the matching '
            . 'search ability (content-egg/search-products / search-coupons / search-images / '
            . 'search-videos), content-egg/get-module-settings and content-egg/get-feed-status.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => 'object',
            'default' => array(),
            'properties' => array(
                'only_active' => array(
                    'type' => 'boolean',
                    'default' => false,
                    'description' => 'Return only activated modules.',
                ),
                'type' => array(
                    'type' => 'string',
                    'enum' => array('product', 'coupon', 'image', 'video', 'other'),
                    'description' => 'Filter by module type.',
                ),
            ),
            'additionalProperties' => false,
        );
    }

    public function outputSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'count' => array('type' => 'integer'),
                'modules' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'module_id' => array('type' => 'string'),
                            'name' => array('type' => 'string'),
                            'active' => array('type' => 'boolean'),
                            'type' => array('type' => 'string'),
                            'is_feed' => array('type' => 'boolean'),
                            'is_ae' => array('type' => 'boolean'),
                            'priority' => array('type' => 'integer'),
                            'capabilities' => self::capabilitiesSchema(),
                        ),
                    ),
                ),
            ),
        );
    }

    private static function capabilitiesSchema(): array
    {
        return array(
            'type' => 'object',
            'description' => 'What the module can do: how it can be searched, whether it compares prices '
                . 'across merchants, what its search costs.',
            'properties' => array(
                'searchable' => array('type' => 'boolean'),
                'search_by' => array(
                    'type' => 'array',
                    'items' => array('type' => 'string', 'enum' => array('keyword', 'gtin', 'url', 'native_id')),
                    'description' => 'Query kinds the module accepts in search.',
                ),
                'native_id_hint' => array(
                    'type' => 'string',
                    'description' => 'What a native id search takes (e.g. ASIN); empty when not supported.',
                ),
                'refresh_by_id' => array('type' => 'boolean'),
                'multi_merchant' => array('type' => 'boolean', 'description' => 'Results span several merchants.'),
                'marketplace' => array(
                    'type' => 'boolean',
                    'description' => 'One product comes back as many listings from different sellers (eBay). '
                        . 'A search returns ' . ModuleCapabilities::MARKETPLACE_DEFAULT_LIMIT . ' results unless you set a limit.',
                ),
                'price_comparison' => array(
                    'type' => 'boolean',
                    'description' => 'Offers for the same product from many shops.',
                ),
                'returns_gtin' => array('type' => 'boolean', 'description' => 'Items carry a GTIN/EAN.'),
                'merchant_domains' => array('type' => 'boolean', 'description' => 'Items expose the merchant domain.'),
                'cost' => array('type' => 'string', 'enum' => array('local', 'api', 'scrape')),
                'shop_domain' => array(
                    'type' => 'string',
                    'description' => 'Affiliate Egg shop modules: the shop\'s domain; a URL search takes product URLs from it. Empty for other modules.',
                ),
                'requests_per_search' => array(
                    'type' => array('string', 'null'),
                    'description' => 'Outbound requests one search makes, when known (scrapers).',
                ),
                'filters' => array(
                    'type' => 'array',
                    'items' => array('type' => 'string'),
                    'description' => 'Filter keys accepted by the search filters parameter.',
                ),
                'locales' => array('type' => 'array', 'items' => array('type' => 'string')),
                'default_locale' => array('type' => 'string'),
            ),
        );
    }

    public function checkPermission($input = null): bool
    {
        return \current_user_can('edit_posts');
    }

    public function execute(array $input): array
    {
        $manager = ModuleManager::getInstance();
        $only_active = !empty($input['only_active']);
        $type_filter = (string) ($input['type'] ?? '');

        $modules = array();
        foreach ((array) $manager->getModulesIdList(false) as $module_id)
        {
            try
            {
                $parser = ModuleManager::parserFactory($module_id);
            }
            catch (\Exception $e)
            {
                continue;
            }

            if (!$parser || !method_exists($parser, 'getParserType'))
            {
                continue;
            }

            $active = (bool) $manager->isModuleActive($module_id);
            if ($only_active && !$active)
            {
                continue;
            }

            $type = $this->typeName((string) $parser->getParserType());
            if ($type_filter && $type !== $type_filter)
            {
                continue;
            }

            $is_feed = $parser instanceof AffiliateFeedParserModule;

            $modules[] = array(
                'module_id' => (string) $module_id,
                'name' => (string) $parser->getName(),
                'active' => $active,
                'type' => $type,
                'is_feed' => $is_feed,
                'is_ae' => strpos((string) $module_id, ModuleManager::AE_MODULES_PREFIX . '__') === 0,
                'priority' => (int) $parser->config('priority'),
                'capabilities' => ModuleCapabilities::forModule($parser, (string) $module_id, $is_feed),
            );
        }

        usort($modules, static function (array $a, array $b)
        {
            if ($a['active'] !== $b['active'])
            {
                return $a['active'] ? -1 : 1;
            }
            if ($a['priority'] !== $b['priority'])
            {
                return $a['priority'] <=> $b['priority'];
            }

            return strcmp($a['module_id'], $b['module_id']);
        });

        return array(
            'count' => count($modules),
            'modules' => $modules,
        );
    }

    private function typeName(string $parser_type): string
    {
        switch ($parser_type)
        {
            case ParserModule::PARSER_TYPE_PRODUCT:
                return 'product';
            case ParserModule::PARSER_TYPE_COUPON:
                return 'coupon';
            case ParserModule::PARSER_TYPE_IMAGE:
                return 'image';
            case ParserModule::PARSER_TYPE_VIDEO:
                return 'video';
            default:
                return 'other';
        }
    }
}
