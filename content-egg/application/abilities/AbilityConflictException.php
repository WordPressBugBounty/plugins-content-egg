<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

/**
 * AbilityConflictException class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class AbilityConflictException extends \RuntimeException
{
    private $current_revision;

    public function __construct(string $current_revision, string $message = '')
    {
        $this->current_revision = $current_revision;

        if ($message === '')
        {
            $message = 'Product data changed since it was loaded. '
                . 'Re-read it with content-egg/get-post-products and retry with the new revision.';
        }

        parent::__construct($message);
    }

    public function currentRevision(): string
    {
        return $this->current_revision;
    }
}
