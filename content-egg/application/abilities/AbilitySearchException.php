<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

/**
 * AbilitySearchException class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Raised when a module's own search/API call fails (invalid or missing API
 * key, quota/rate limit at the network, network error). Distinct from an
 * internal bug: the registrar surfaces the real reason to the agent so the
 * user can fix the module's configuration, instead of a generic 500.
 */
class AbilitySearchException extends \RuntimeException
{
    public function __construct(string $module_id, string $reason)
    {
        $reason = trim($reason);
        if ($reason === '')
        {
            $reason = 'the module returned an error with no message.';
        }

        parent::__construct(
            "Search through the '{$module_id}' module failed: {$reason} "
                . "Check the module's settings and API credentials in Content Egg, or adjust the "
                . 'request (for example the limit), then try again.'
        );
    }
}
