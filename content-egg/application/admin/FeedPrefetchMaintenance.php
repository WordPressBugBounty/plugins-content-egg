<?php

namespace ContentEgg\application\admin;

use ContentEgg\application\components\AffiliateFeedParserModule;
use ContentEgg\application\components\ModuleManager;

defined('\ABSPATH') || exit;

/**
 * FeedPrefetchMaintenance class file
 *
 * The feed setup wizard downloads a ZIP archive once during analysis and
 * hands it to the module for reuse by the real import
 * (AffiliateFeedParserModule::storePrefetchedArchive()). If the wizard is
 * abandoned before that import runs, the prefetched archive is normally
 * cleared the next time that slot's wizard page loads or the module is
 * destroyed — this daily sweep is the backstop for a slot nobody revisits.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class FeedPrefetchMaintenance
{
    public static function garbageCollect(): void
    {
        foreach (ModuleManager::getInstance()->getModules() as $module)
        {
            if (!$module instanceof AffiliateFeedParserModule)
            {
                continue;
            }

            $module->clearStalePrefetchedArchive();
        }
    }
}
