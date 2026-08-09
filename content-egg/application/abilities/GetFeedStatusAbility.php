<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\AffiliateFeedParserModule;
use ContentEgg\application\components\ModuleManager;

/**
 * GetFeedStatusAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
final class GetFeedStatusAbility extends AbilityBase
{
    public function name(): string
    {
        return 'content-egg/get-feed-status';
    }

    public function label(): string
    {
        return __('Get Feed Status', 'content-egg');
    }

    public function description(): string
    {
        return 'Returns the state of one feed module instance: import progress (feed '
            . 'imports run asynchronously — poll this ability, do not wait), imported '
            . 'product count and a catalog summary when available. Feed module ids look '
            . 'like "Feed__1" and are listed by content-egg/list-modules with is_feed=true.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => array('object', 'null'),
            'properties' => array(
                'module_id' => array(
                    'type' => 'string',
                    'description' => 'Feed module id, e.g. "Feed__1".',
                ),
            ),
            'required' => array('module_id'),
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
                'active' => array('type' => 'boolean'),
                'import' => array('type' => 'object', 'description' => 'Import status fields: state, rows_read, inserted, skipped, bytes_done, bytes_total, started_at, heartbeat_at, error.'),
                'product_count' => array('type' => 'integer'),
                'catalog_summary' => array(
                    'type' => array('object', 'null'),
                    'description' => 'Aggregated catalog facts (categories, price ranges) when available.',
                ),
            ),
        );
    }

    public function checkPermission($input = null): bool
    {
        return \current_user_can('edit_posts');
    }

    public function execute(array $input): array
    {
        $module_id = trim((string) ($input['module_id'] ?? ''));

        $parser = null;
        try
        {
            $parser = ModuleManager::parserFactory($module_id);
        }
        catch (\Exception $e)
        {
            // fall through to the input error below
        }

        if (!$parser || !($parser instanceof AffiliateFeedParserModule))
        {
            throw new AbilityInputException(
                "'{$module_id}' is not a feed module. Feed module ids look like 'Feed__1'; "
                    . 'call content-egg/list-modules and filter is_feed=true.'
            );
        }

        $summary = null;
        if (class_exists('\ContentEgg\application\modules\Feed\CatalogSummary'))
        {
            $summary = \ContentEgg\application\modules\Feed\CatalogSummary::get($module_id);
        }

        return array(
            'module_id' => $module_id,
            'feed_name' => (string) $parser->config('feed_name'),
            'active' => (bool) ModuleManager::getInstance()->isModuleActive($module_id),
            'import' => (array) $parser->importStatus()->get(),
            'product_count' => (int) $parser->getProductCount(),
            'catalog_summary' => $summary,
        );
    }
}
