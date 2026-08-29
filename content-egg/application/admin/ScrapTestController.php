<?php

namespace ContentEgg\application\admin;

defined('\ABSPATH') || exit;

/**
 * ScrapTestController class file
 *
 * Credential check for scraping services on the module settings screen.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ScrapTestController
{
    const STATUS_URL = 'https://api.brightdata.com/status';
    const ZONES_URL  = 'https://api.brightdata.com/zone/get_active_zones';

    const NONCE_ACTION = 'cegg_brightdata_test';

    public function __construct()
    {
        \add_action('wp_ajax_cegg_brightdata_test', array($this, 'ajaxTest'));
        \add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));
    }

    public function enqueueAssets($hook_suffix)
    {
        if (!\current_user_can('manage_options'))
        {
            return;
        }

        if (empty($_GET['page']))
        {
            return;
        }

        $page = \sanitize_text_field(\wp_unslash($_GET['page']));

        if (strpos($page, 'content-egg-modules--AmazonNoApi') !== 0)
        {
            return;
        }

        \wp_enqueue_script('jquery');

        \wp_localize_script('jquery', 'ceggBrightdataTest', array(
            'nonce'    => \wp_create_nonce(self::NONCE_ACTION),
            'checking' => __('Checking…', 'content-egg'),
            'failed'   => __('Request failed.', 'content-egg'),
            'zones'    => __('Zones:', 'content-egg'),
        ));

        \wp_add_inline_script('jquery', $this->inlineScript());
    }

    protected function inlineScript()
    {
        return <<<'JS'
(function ($) {
    function esc(t) { return $('<div>').text(t == null ? '' : t).html(); }

    $(document).on('click', '#cegg-brightdata-test', function () {
        var $b = $(this), $out = $('#cegg-brightdata-test-result');
        $b.prop('disabled', true);
        $out.text(ceggBrightdataTest.checking);

        $.post(ajaxurl, {
            action: 'cegg_brightdata_test',
            nonce: ceggBrightdataTest.nonce,
            token: $('#brightdata_token').val()
        }).done(function (r) {
            if (!r || !r.success) {
                $out.text((r && r.data && r.data.message) ? r.data.message : 'Error');
                return;
            }
            var html = '';
            $.each(r.data.lines || [], function (i, l) { html += '<div>' + esc(l) + '</div>'; });
            if ((r.data.zones || []).length) {
                html += '<div>' + esc(ceggBrightdataTest.zones) + '</div>';
                $.each(r.data.zones, function (i, z) {
                    html += '<div><a href="#" class="cegg-bd-zone" data-zone="' + esc(z.name) + '">'
                         + esc(z.name) + '</a> <em>' + esc(z.type) + '</em></div>';
                });
            }
            $out.html(html);
        }).fail(function () {
            $out.text(ceggBrightdataTest.failed);
        }).always(function () {
            $b.prop('disabled', false);
        });
    });

    $(document).on('click', '.cegg-bd-zone', function (e) {
        e.preventDefault();
        $('#brightdata_zone').val($(this).data('zone'));
    });
})(jQuery);
JS;
    }

    public function ajaxTest()
    {
        if (!\current_user_can('manage_options'))
        {
            \wp_send_json_error(array('message' => __('Access denied.', 'content-egg')), 403);
        }

        \check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $token = isset($_POST['token']) ? \sanitize_text_field(\wp_unslash($_POST['token'])) : '';

        if ($token === '')
        {
            \wp_send_json_error(array('message' => __('Enter an API key first.', 'content-egg')));
        }

        $args = array(
            'timeout' => 30,
            'headers' => array('Authorization' => 'Bearer ' . $token),
        );

        $status = $this->getJson(self::STATUS_URL, $args);

        if (isset($status['error']))
        {
            \wp_send_json_error(array('message' => $status['error']));
        }

        $lines = array();

        if (!empty($status['customer']))
        {
            /* translators: 1: Bright Data customer id, 2: account status */
            $lines[] = sprintf(
                __('Account %1$s — %2$s', 'content-egg'),
                $status['customer'],
                isset($status['status']) ? $status['status'] : ''
            );
        }

        if (empty($status['can_make_requests']))
        {
            $reason = !empty($status['auth_fail_reason']) ? $status['auth_fail_reason'] : __('unknown', 'content-egg');
            /* translators: %s: reason reported by Bright Data */
            $lines[] = sprintf(__('Requests blocked: %s', 'content-egg'), $reason);
        }

        $zones = $this->getJson(self::ZONES_URL, $args);
        $names = array();

        if (is_array($zones) && !isset($zones['error']))
        {
            foreach ($zones as $zone)
            {
                if (!empty($zone['name']))
                {
                    $names[] = array(
                        'name' => $zone['name'],
                        'type' => isset($zone['type']) ? $zone['type'] : '',
                    );
                }
            }
        }

        if (!$names)
        {
            $lines[] = __('No zones on this account. Create a Web Unlocker zone in the Bright Data control panel.', 'content-egg');
        }

        \wp_send_json_success(array(
            'ok'    => !empty($status['can_make_requests']) && $names,
            'lines' => $lines,
            'zones' => $names,
        ));
    }

    /**
     * @return array Decoded body, or array('error' => message).
     */
    protected function getJson($url, array $args)
    {
        $response = \wp_remote_get($url, $args);

        if (\is_wp_error($response))
        {
            return array('error' => $response->get_error_message());
        }

        $code = (int) \wp_remote_retrieve_response_code($response);
        $body = \wp_remote_retrieve_body($response);

        if ($code !== 200)
        {
            // Bright Data returns plain-text errors; bound them.
            $excerpt = \sanitize_text_field(trim($body));
            $excerpt = function_exists('mb_substr') ? mb_substr($excerpt, 0, 200) : substr($excerpt, 0, 200);

            return array('error' => $code . ': ' . $excerpt);
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : array('error' => __('Unexpected response.', 'content-egg'));
    }
}
