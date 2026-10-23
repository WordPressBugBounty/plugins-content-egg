<?php

namespace ContentEgg\application\deals;

defined('\ABSPATH') || exit;

/**
 * DealStateRestController class file
 *
 * The editor's Deal panel reads and changes a deal through here, so every
 * change goes through DealService and its reset rules.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class DealStateRestController
{
    const NAMESPACE = 'cegg/v1';

    private static $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null)
            self::$instance = new self;

        return self::$instance;
    }

    public function init(): void
    {
        \add_action('rest_api_init', array($this, 'registerRoutes'));
    }

    public function registerRoutes(): void
    {
        \register_rest_route(self::NAMESPACE, '/deals/(?P<id>\d+)/state', array(
            array(
                'methods' => \WP_REST_Server::READABLE,
                'callback' => array($this, 'get'),
                'permission_callback' => array($this, 'canEdit'),
            ),
            array(
                'methods' => \WP_REST_Server::CREATABLE,
                'callback' => array($this, 'update'),
                'permission_callback' => array($this, 'canEdit'),
                'args' => array(
                    'action' => array('type' => 'string', 'required' => true, 'enum' => array('pin', 'expire', 'activate', 'ends', 'coupon')),
                    'module_id' => array('type' => 'string'),
                    'unique_id' => array('type' => 'string'),
                    'value' => array('type' => 'string'),
                    'code' => array('type' => 'string'),
                    'note' => array('type' => 'string'),
                ),
            ),
        ));
    }

    public function canEdit(\WP_REST_Request $request): bool
    {
        $id = (int) $request['id'];

        return \get_post_type($id) === DealPostType::POST_TYPE && \current_user_can('edit_post', $id);
    }

    public function get(\WP_REST_Request $request)
    {
        return \rest_ensure_response(self::state((int) $request['id']));
    }

    public function update(\WP_REST_Request $request)
    {
        $id = (int) $request['id'];

        switch ((string) $request['action'])
        {
            case 'pin':
                if (!DealService::pin($id, (string) $request['module_id'], (string) $request['unique_id']))
                    return new \WP_Error('cegg_no_item', __('That product is not in this deal.', 'content-egg'), array('status' => 404));
                break;
            case 'expire':
                DealService::markExpired($id);
                break;
            case 'activate':
                DealService::markActive($id);
                break;
            case 'ends':
                $value = trim((string) $request['value']);
                if ($value === '')
                {
                    DealService::setEnds($id, 0);
                    break;
                }
                try
                {
                    DealService::setEnds($id, (new \DateTimeImmutable($value, \wp_timezone()))->getTimestamp());
                }
                catch (\Exception $e)
                {
                    return new \WP_Error('cegg_bad_date', __('That end date could not be read.', 'content-egg'), array('status' => 400));
                }
                break;
            case 'coupon':
                DealService::setCoupon($id, (string) $request['code'], (string) $request['note']);
                break;
        }

        return \rest_ensure_response(self::state($id));
    }

    public static function state(int $id): array
    {
        $pin = DealService::getPin($id);
        $items = array();
        foreach (DealService::itemsByModule($id) as $module_id => $list)
        {
            foreach ($list as $key => $item)
            {
                if (!is_array($item))
                    continue;

                $unique_id = (string) ($item['unique_id'] ?? $key);

                // Only priced products can be the Main offer - not coupons,
                // images or videos. The current pin stays listed so the select
                // still shows it after its price is gone.
                $is_pin = $pin && $pin['module_id'] === (string) $module_id && $pin['unique_id'] === $unique_id;
                if ((float) ($item['price'] ?? 0) <= 0 && !$is_pin)
                    continue;

                $items[] = array(
                    'module_id' => (string) $module_id,
                    'unique_id' => $unique_id,
                    'title' => \wp_strip_all_tags((string) ($item['title'] ?? '')),
                    'price' => (float) ($item['price'] ?? 0),
                    'currency' => (string) ($item['currencyCode'] ?? ''),
                    'merchant' => (string) (($item['merchant'] ?? '') ?: ($item['domain'] ?? '')),
                );
            }
        }

        $ends = (int) \get_post_meta($id, DealPostType::META_ENDS, true);
        $reason = (string) \get_post_meta($id, DealPostType::META_REASON, true);

        return array(
            'items' => $items,
            'pin' => $pin,
            'status' => (string) \get_post_meta($id, DealPostType::META_STATUS, true),
            'reason' => $reason,
            'reason_label' => $reason !== '' ? DealView::reasonLabel($reason) : '',
            'ends' => $ends ? \wp_date('Y-m-d\TH:i', $ends) : '',
            // The panel header: what a visitor sees, formatted server-side.
            'price_text' => DealView::price((float) \get_post_meta($id, DealPostType::META_PRICE, true), (string) \get_post_meta($id, DealPostType::META_CURRENCY, true)),
            'discount' => (int) \get_post_meta($id, DealPostType::META_DISCOUNT, true),
            'score' => (int) \get_post_meta($id, DealPostType::META_SCORE, true),
            'votes_up' => (int) \get_post_meta($id, DealPostType::META_VOTES_UP, true),
            'votes_down' => (int) \get_post_meta($id, DealPostType::META_VOTES_DOWN, true),
            'coupon_code' => (string) \get_post_meta($id, DealPostType::META_COUPON_CODE, true),
            'coupon_note' => (string) \get_post_meta($id, DealPostType::META_COUPON_NOTE, true),
        );
    }
}
