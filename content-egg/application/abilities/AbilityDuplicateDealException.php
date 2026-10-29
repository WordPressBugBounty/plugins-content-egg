<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

/**
 * AbilityDuplicateDealException class file
 *
 * An active deal for the same offer already exists. Mapped to 409
 * cegg_duplicate_deal with data.deal_id - not cegg_conflict, whose "re-read and
 * retry" advice would make an agent create the deal again.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class AbilityDuplicateDealException extends \RuntimeException
{
    private $deal_id;

    public function __construct(int $deal_id, string $message = '')
    {
        $this->deal_id = $deal_id;

        if ($message === '')
        {
            $message = "An active deal for this offer already exists (post {$deal_id}). Update it with "
                . 'content-egg/update-deal, or pass deal.allow_duplicate: true to create another.';
        }

        parent::__construct($message);
    }

    public function dealId(): int
    {
        return $this->deal_id;
    }
}
