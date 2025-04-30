<?php

namespace ContentEgg\application\admin;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\models\PrefillQueueModel;
use ContentEgg\application\Plugin;
use ContentEgg\application\ProductPrefillScheduler;

use function ContentEgg\prn;
use function ContentEgg\prnx;

/**
 * ProductPrefillController class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2025 keywordrush.com
 */
class ProductPrefillController
{
    const slug = 'content-egg-product-prefill';

    const PREVIEW_POST_LIMIT = 50;

    public function __construct()
    {
        \add_action('admin_menu', array($this, 'add_admin_menu'));
    }

    public function add_admin_menu()
    {
        \add_submenu_page(
            Plugin::slug,
            __('Product Prefill', 'content-egg') . ' &lsaquo; Content Egg',
            __('Prefill', 'content-egg') . ' <span class="update-plugins count-1"><span class="update-count">New</span></span>',
            'publish_posts',
            self::slug,
            array($this, 'handleAction')
        );
    }

    public function handleAction()
    {
        \wp_enqueue_style('cegg-bootstrap5-full');

        $action = $_GET['action'] ?? '';

        $valid_actions = [
            'prefill_config' => [
                'method' => 'actionPrefillConfig',
                'nonce_action' => 'prefill_config_nonce',
                'nonce_key' => '_wpnonce',
                'method_type' => 'get',
            ],
            'prefill_start' => [
                'method' => 'actionPrefillStart',
                'nonce_action' => 'prefill_start_nonce',
                'nonce_key' => '_wpnonce',
                'method_type' => 'post',
            ],
            'prefill_status' => [
                'method' => 'actionPrefillStatus',
            ],
            'prefill_stop' => [
                'method' => 'actionPrefillStop',
                'nonce_action' => 'cegg_prefill_stop',
                'nonce_key' => 'cegg_prefill_stop_nonce',
                'method_type' => 'post',
            ],
            'prefill_restart' => [
                'method' => 'actionPrefillRestart',
                'nonce_action' => 'cegg_prefill_restart',
                'nonce_key' => 'cegg_prefill_restart_nonce',
                'method_type' => 'post',
            ],
            'prefill_run_once' => [
                'method' => 'actionPrefillRunOnce',
                'nonce_action' => !Plugin::isDevEnvironment() ? 'cegg_prefill_run_once' : '',
                'nonce_key' => 'cegg_prefill_run_once_nonce',
                'method_type' => 'post',
            ],
            'prefill_restart_failed' => [
                'method' => 'actionPrefillRestartFailed',
                'nonce_action' => 'cegg_prefill_restart_failed',
                'nonce_key' => 'cegg_prefill_restart_failed_nonce',
                'method_type' => 'post',
            ],
        ];

        if (isset($valid_actions[$action]))
        {
            $config = $valid_actions[$action];

            if (!empty($config['nonce_action']))
            {
                $source = ($config['method_type'] ?? 'get') === 'post' ? $_POST : $_GET;
                if (empty($source[$config['nonce_key']]) || !wp_verify_nonce($source[$config['nonce_key']], $config['nonce_action']))
                {
                    wp_die(esc_html__('Security check failed.', 'content-egg'));
                }
            }

            $this->{$config['method']}();
            return;
        }

        if (PrefillQueueModel::model()->count())
        {
            $this->actionPrefillStatus();
            return;
        }

        $this->actionPostSelector();
    }

    public function actionPostSelector()
    {
        PluginAdmin::getInstance()->render('prefill_post_selector');
    }

    public function actionPrefillConfig()
    {
        $post_type      = isset($_GET['_post_type']) && $_GET['_post_type'] ? sanitize_text_field($_GET['_post_type']) : '';
        $post_status    = isset($_GET['post_status']) && $_GET['post_status'] ? sanitize_text_field($_GET['post_status']) : '';
        $category       = isset($_GET['category']) && $_GET['category'] ? intval($_GET['category']) : 0;
        $category_product = isset($_GET['category_product']) && $_GET['category_product'] ? intval($_GET['category_product']) : 0;
        $author         = isset($_GET['author']) && $_GET['author'] ? intval($_GET['author']) : 0;
        $date_from      = isset($_GET['date_from']) && $_GET['date_from'] ? sanitize_text_field($_GET['date_from']) : '';
        $date_to        = isset($_GET['date_to']) && $_GET['date_to'] ? sanitize_text_field($_GET['date_to']) : '';
        $keywords       = isset($_GET['keywords']) && $_GET['keywords'] ? sanitize_text_field(wp_unslash($_GET['keywords'])) : '';
        $post__in       = isset($_GET['post__in']) && $_GET['post__in'] ? array_map('intval', explode(',', $_GET['post__in'])) : array();
        $post__not_in   = isset($_GET['post__not_in']) && $_GET['post__not_in'] ? array_map('intval', explode(',', $_GET['post__not_in'])) : array();
        $post_limit     = isset($_GET['post_limit']) && $_GET['post_limit'] ? intval($_GET['post_limit']) : 0;
        $offset         = isset($_GET['offset']) && $_GET['offset'] ? intval($_GET['offset']) : 0;
        $ce_filter      = isset($_GET['ce_filter']) && $_GET['ce_filter'] ? sanitize_text_field($_GET['ce_filter']) : '';

        $args = array(
            'post_type'      => $post_type ? $post_type : 'any',
            'post_status'    => $post_status ? $post_status : 'any',
            'offset'         => $offset,
        );

        if ($post_type == 'post' && $category)
        {
            $args['cat'] = $category;
        }
        elseif ($post_type === 'product' && $category_product)
        {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $category_product,
                ),
            );
        }

        if ($author)
        {
            $args['author'] = $author;
        }

        $date_query = array();
        if ($date_from)
        {
            $date_query['after'] = $date_from;
        }
        if ($date_to)
        {
            $date_query['before'] = $date_to;
        }
        if (!empty($date_query))
        {
            $date_query['inclusive'] = true;
            $args['date_query'] = array($date_query);
        }

        if ($keywords)
        {
            $args['s'] = $keywords;
        }

        if (!empty($post__in))
        {
            $args['post__in'] = $post__in;
        }
        if (!empty($post__not_in))
        {
            $args['post__not_in'] = $post__not_in;
        }

        if ($ce_filter === 'with_data' || $ce_filter === 'without_data')
        {
            self::applyCeggMetaWhereFilter($ce_filter);
        }

        // First, get total count
        $count_args = $args;
        $count_args['posts_per_page'] = 1;
        $count_args['fields'] = 'ids';
        $count_args['no_found_rows'] = false;

        $count_query = new \WP_Query($count_args);
        $total_posts = $count_query->found_posts;
        if ($post_limit)
        {
            $total_posts = min($total_posts, $post_limit);
        }

        // Now, query again to actually fetch up to 50 posts for listing
        $list_args = $args;
        $list_args['posts_per_page'] = min($total_posts, self::PREVIEW_POST_LIMIT);
        $list_args['no_found_rows'] = true;

        $query = new \WP_Query($list_args);
        $selected_posts = $query->posts;

        // Now, fitch all post IDs
        $list_args['fields'] = 'ids';
        $list_args['no_found_rows'] = true;
        $list_args['posts_per_page'] = $post_limit ?: 10000;

        $post_ids = get_posts($list_args);

        if ($ce_filter === 'with_data' || $ce_filter === 'without_data')
        {
            \remove_filter('posts_where', array(__CLASS__, 'applyCeggMetaWhereFilter'));
        }

        $transient_key = 'cegg_prefill_ids_' . get_current_user_id() . '_' . wp_generate_password(8, false);
        $transient_expiration = 60 * 60;
        \set_transient($transient_key, $post_ids, $transient_expiration);

        $has_ai_api_key = GeneralConfig::getInstance()->option('system_ai_key');

        PluginAdmin::getInstance()->render('prefill_config', array(
            'total_posts'    => $total_posts,
            'selected_posts' => $selected_posts,
            'prefill_transient_key' => $transient_key,
            'post_type' => $post_type,
            'has_ai_api_key' => $has_ai_api_key,
        ));
    }

    public function actionPrefillStart()
    {
        $queue_model = \ContentEgg\application\models\PrefillQueueModel::model();

        $pending = $queue_model->countPending();
        if ($pending > 0)
        {
            wp_die(__('A prefill task is already running or pending. Please wait until it finishes.', 'content-egg'));
        }

        $transient_key = sanitize_text_field($_POST['prefill_transient'] ?? '');
        $post_ids = get_transient($transient_key);
        delete_transient($transient_key);

        if (!is_array($post_ids))
        {
            wp_die(__('Post queue expired or invalid.', 'content-egg'));
        }

        // Save config to transient
        $config = $this->parsePrefillConfig();
        $config_key = 'cegg_prefill_config_' . get_current_user_id() . '_' . wp_generate_password(8, false);
        set_transient($config_key, $config, 7 * DAY_IN_SECONDS);

        foreach ($post_ids as $post_id)
        {
            $queue_model->addToQueue((int)$post_id, $config_key);
        }

        ProductPrefillScheduler::addScheduleEvent();

        $redirect_url = \admin_url('admin.php?page=content-egg-product-prefill');
        $redirect_url = AdminNotice::add2Url($redirect_url, 'prefill_started', 'success');

        \wp_redirect($redirect_url);

        exit;
    }

    protected function parsePrefillConfig(): array
    {
        $config = [];

        $config['modules'] = isset($_POST['modules']) ? array_map('sanitize_text_field', (array) $_POST['modules']) : [];

        $config['keyword_source'] = sanitize_text_field($_POST['keyword_source'] ?? 'post_title');
        $config['meta_field_name'] = sanitize_text_field(wp_unslash($_POST['meta_field_name']) ?? '');
        $config['source_module_gtin'] = sanitize_text_field($_POST['source_module_gtin'] ?? '');
        $config['source_module_title'] = sanitize_text_field($_POST['source_module_title'] ?? '');

        $valid_behaviors = ['skip_module', 'skip_post', 'replace'];
        $config['existing_module_behavior'] = in_array($_POST['existing_module_behavior'] ?? '', $valid_behaviors, true)
            ? sanitize_text_field($_POST['existing_module_behavior'])
            : 'skip_module';

        $config['max_products_total'] = isset($_POST['max_products_total']) ? (int) $_POST['max_products_total'] : 0;
        $config['max_products_per_module'] = isset($_POST['max_products_per_module']) ? (int) $_POST['max_products_per_module'] : 0;
        $config['product_group'] = sanitize_text_field(wp_unslash($_POST['product_group'] ?? ''));
        $config['ai_relevance_check'] = !empty($_POST['ai_relevance_check']) ? 1 : 0;

        $config['shortcode_blocks'] = [];
        if (!empty($_POST['shortcode_blocks']) && is_array($_POST['shortcode_blocks']))
        {
            $shortcode_blocks = isset($_POST['shortcode_blocks']) ? wp_unslash($_POST['shortcode_blocks']) : array();
            foreach ($shortcode_blocks as $block)
            {
                $position = sanitize_text_field($block['position'] ?? '');
                $code = trim(wp_kses_post($block['code'] ?? ''));

                if ($position !== 'disabled' && $code !== '')
                {
                    $config['shortcode_blocks'][] = [
                        'position' => $position,
                        'code'     => $code,
                    ];
                }
            }
        }

        $config['custom_fields'] = [];
        if (!empty($_POST['custom_fields']) && is_array($_POST['custom_fields']))
        {
            $custom_fields = wp_unslash($_POST['custom_fields']);
            foreach ($custom_fields as $field)
            {
                $key = sanitize_key($field['key'] ?? '');
                $value = sanitize_text_field($field['value'] ?? '');

                if ($key !== '' && $value !== '')
                {
                    $config['custom_fields'][] = [
                        'key'   => $key,
                        'value' => $value,
                    ];
                }
            }
        }

        return $config;
    }

    public static function applyCeggMetaWhereFilter($mode = 'with_data')
    {
        if (!in_array($mode, ['with_data', 'without_data'], true))
        {
            return;
        }

        add_filter('posts_where', function ($where) use ($mode)
        {
            global $wpdb;

            $module_ids = ModuleManager::getInstance()->getModulesIdList(true);
            if (empty($module_ids))
            {
                return $where;
            }

            $meta_keys = array_map(function ($module_id)
            {
                return '_cegg_last_update_' . $module_id;
            }, $module_ids);

            $meta_keys_sql = implode("','", array_map('esc_sql', $meta_keys));

            $subquery = "SELECT 1 FROM {$wpdb->postmeta}
                     WHERE {$wpdb->postmeta}.post_id = {$wpdb->posts}.ID
                     AND {$wpdb->postmeta}.meta_key IN ('$meta_keys_sql')";

            if ($mode === 'with_data')
            {
                $where .= " AND EXISTS ($subquery)";
            }
            elseif ($mode === 'without_data')
            {
                $where .= " AND NOT EXISTS ($subquery)";
            }

            return $where;
        });
    }

    public function actionPrefillStatus()
    {
        $queue = \ContentEgg\application\models\PrefillQueueModel::model();

        $stats         = [
            'pending'    => $queue->countByStatus('pending'),
            'done'       => $queue->countByStatus('done'),
            'failed'     => $queue->countByStatus('failed'),
        ];

        $last_updated  = $queue->getLastUpdatedAt();

        if ($queue->isInProgress())
        {
            ProductPrefillScheduler::addScheduleEvent();
        }

        $table = new PrefillLogTable($queue);
        $table->prepare_items();

        $is_cron_enabled = !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON;
        $is_in_progress  = $queue->isInProgress();

        $stuck_threshold_minutes = 15;
        $last_updated_timestamp = strtotime($last_updated ?? '');
        $now = current_time('timestamp');
        $is_possibly_stuck = $is_in_progress && $last_updated_timestamp && ($now - $last_updated_timestamp) > ($stuck_threshold_minutes * MINUTE_IN_SECONDS);

        $failed_tasks_count = PrefillQueueModel::model()->countFailed();

        PluginAdmin::getInstance()->render('prefill_status', [
            'stats'           => $stats,
            'last_updated'    => $last_updated,
            'table'           => $table,
            'is_in_progress'  => $is_in_progress,
            'is_cron_enabled' => $is_cron_enabled,
            'is_possibly_stuck' => $is_possibly_stuck,
            'failed_tasks_count' => $failed_tasks_count,
        ]);
    }

    public function actionPrefillStop()
    {
        $queue = \ContentEgg\application\models\PrefillQueueModel::model();
        $queue->clearPending();

        ProductPrefillScheduler::clearScheduleEvents();

        $redirect_url = \admin_url('admin.php?page=content-egg-product-prefill');
        $redirect_url = AdminNotice::add2Url($redirect_url, 'prefill_stopped', 'success');

        \wp_redirect($redirect_url);
        exit;
    }

    public function actionPrefillRestart()
    {
        $queue = \ContentEgg\application\models\PrefillQueueModel::model();
        $queue->clearQueue();

        ProductPrefillScheduler::clearScheduleEvents();

        \wp_redirect(admin_url('admin.php?page=content-egg-product-prefill'));
        exit;
    }

    public function actionPrefillRunOnce()
    {
        if (!Plugin::isDevEnvironment())
        {
            wp_die('This action is only allowed in a development environment.');
        }

        $service = new \ContentEgg\application\components\ProductPrefillService();
        $service->processBatch(1);

        wp_redirect(admin_url('admin.php?page=content-egg-product-prefill'));
        exit;
    }

    public function actionPrefillRestartFailed()
    {
        $queue = \ContentEgg\application\models\PrefillQueueModel::model();

        $queue->restartFailed();

        ProductPrefillScheduler::addScheduleEvent();

        wp_redirect(admin_url('admin.php?page=content-egg-product-prefill&action=prefill_status'));
        exit;
    }
}
