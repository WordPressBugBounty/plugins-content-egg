<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

/**
 * SetPostStatusAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Change a post's status: draft, pending (submit for review), publish, future
 * (schedule) or private. Scheduling (future) requires a future date (date_gmt in
 * UTC or date in site time). Publishing/private require the post type's native
 * publish capability; the rest require edit access to the post. Outward-facing,
 * so it is marked destructive (confirm before calling).
 */
final class SetPostStatusAbility extends AbilityBase
{
    const STATUSES = array('draft', 'pending', 'publish', 'future', 'private');

    public function name(): string
    {
        return 'content-egg/set-post-status';
    }

    public function label(): string
    {
        return __('Set Post Status', 'content-egg');
    }

    public function description(): string
    {
        return "Changes a post's status: draft, pending (submit for review), publish, future "
            . '(schedule) or private. For future, pass date_gmt (UTC) or date (site time), '
            . 'ISO 8601, and it must be in the future. Publishing goes live immediately and is '
            . 'hard to undo — confirm with the user first. Requires the publish capability for '
            . 'publish/private, edit access otherwise. Returns the new status, scheduled time '
            . 'and permalink.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => array('object', 'null'),
            'properties' => array(
                'post_id' => array('type' => 'integer', 'minimum' => 1),
                'status' => array('type' => 'string', 'enum' => self::STATUSES),
                'date_gmt' => array(
                    'type' => 'string',
                    'description' => 'Schedule time in UTC (ISO 8601). Required for status "future" if date is omitted.',
                ),
                'date' => array(
                    'type' => 'string',
                    'description' => 'Schedule time in the site timezone (ISO 8601). Alternative to date_gmt for "future".',
                ),
            ),
            'required' => array('post_id', 'status'),
            'additionalProperties' => false,
        );
    }

    public function outputSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'post_id' => array('type' => 'integer'),
                'status' => array('type' => 'string'),
                'date_gmt' => array('type' => 'string'),
                'permalink' => array('type' => 'string'),
                'edit_url' => array('type' => 'string'),
            ),
        );
    }

    public function annotations(): array
    {
        // Outward-facing (publish/schedule): destructive + confirm-first. idempotent
        // is false so core routes it POST (destructive && idempotent => DELETE).
        return array('readonly' => false, 'destructive' => true, 'idempotent' => false);
    }

    public function checkPermission($input = null): bool
    {
        if (!PostScope::canEditPost($input))
        {
            return false;
        }

        // Going to a live state (publish/private) or scheduling one (future) needs
        // the post type's publish cap — future is a deferred publish (wp-cron makes
        // it live with no further check), so it must clear the same gate.
        $status = is_array($input) ? (string) ($input['status'] ?? '') : '';
        if (!PostScope::isPublishingStatus($status))
        {
            return true;
        }

        $post_id = is_array($input) ? (int) ($input['post_id'] ?? 0) : 0;
        $post = $post_id ? \get_post($post_id) : null;
        $pto = $post ? \get_post_type_object($post->post_type) : null;
        $cap = $pto ? (string) $pto->cap->publish_posts : 'publish_posts';

        return \current_user_can($cap, $post_id);
    }

    public function execute(array $input): array
    {
        $post = PostScope::requirePost($input);
        $post_id = (int) $post->ID;

        $status = (string) ($input['status'] ?? '');
        if (!in_array($status, self::STATUSES, true))
        {
            throw new AbilityInputException(
                "status must be one of: " . implode(', ', self::STATUSES) . '.'
            );
        }

        $update = array('ID' => $post_id, 'post_status' => $status);

        if ($status === 'future')
        {
            list($post_date, $post_date_gmt) = $this->resolveScheduleDate($input);
            $update['post_date'] = $post_date;
            $update['post_date_gmt'] = $post_date_gmt;
            // WP only keeps 'future' if the date is ahead of "now"; edit_date lets
            // wp_update_post accept the explicit date instead of bumping to now.
            $update['edit_date'] = true;
        }

        $result = \wp_update_post($update, true);
        if (\is_wp_error($result))
        {
            throw new AbilityInputException('Could not update the post: ' . $result->get_error_message());
        }

        $saved = \get_post($post_id);

        return array(
            'post_id' => $post_id,
            'status' => (string) $saved->post_status,
            'date_gmt' => (string) $saved->post_date_gmt,
            'permalink' => (string) \get_permalink($post_id),
            'edit_url' => (string) \get_edit_post_link($post_id, 'raw'),
        );
    }

    /**
     * Turn the caller's date_gmt (UTC) or date (site time) into the
     * (post_date, post_date_gmt) pair wp_update_post expects, and require it to
     * be in the future. An explicit offset/Z in the string wins over the assumed
     * zone. Throws AbilityInputException on a missing/invalid/past date.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveScheduleDate(array $input): array
    {
        $date_gmt = trim((string) ($input['date_gmt'] ?? ''));
        $date = trim((string) ($input['date'] ?? ''));

        if ($date_gmt === '' && $date === '')
        {
            throw new AbilityInputException(
                "Scheduling (status \"future\") requires 'date_gmt' (UTC) or 'date' (site time), ISO 8601."
            );
        }

        try
        {
            if ($date_gmt !== '')
            {
                $dt = new \DateTime($date_gmt, new \DateTimeZone('UTC'));
                $post_date_gmt = $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                $post_date = \get_date_from_gmt($post_date_gmt);
            }
            else
            {
                $dt = new \DateTime($date, \wp_timezone());
                $post_date = $dt->format('Y-m-d H:i:s');
                $post_date_gmt = \get_gmt_from_date($post_date);
            }
        }
        catch (\Exception $e)
        {
            throw new AbilityInputException('The schedule date is not a valid ISO 8601 date/time.');
        }

        if ($dt->getTimestamp() <= time())
        {
            throw new AbilityInputException('The schedule date must be in the future.');
        }

        return array($post_date, $post_date_gmt);
    }
}
