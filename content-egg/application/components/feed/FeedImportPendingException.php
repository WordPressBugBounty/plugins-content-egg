<?php

namespace ContentEgg\application\components\feed;

defined('\ABSPATH') || exit;

/**
 * FeedImportPendingException class file
 *
 * Thrown by a feed module when a search cannot be answered yet because the
 * catalog is empty and its first import is still running (or scheduled) in
 * the background. The metabox API translates it into a retryable
 * "feed_importing" response instead of a hard error.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class FeedImportPendingException extends \Exception
{
}
