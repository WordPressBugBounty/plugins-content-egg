<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

/**
 * AbilityRateLimitException class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class AbilityRateLimitException extends \RuntimeException
{
    private $retry_after;

    public function __construct(int $retry_after)
    {
        $this->retry_after = $retry_after;
        parent::__construct('Rate limit exceeded. Retry in ' . $retry_after . ' seconds.');
    }

    public function retryAfter(): int
    {
        return $this->retry_after;
    }
}
