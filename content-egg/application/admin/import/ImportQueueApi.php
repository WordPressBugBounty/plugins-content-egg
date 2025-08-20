<?php

namespace ContentEgg\application\admin\import;

use ContentEgg\application\helpers\PostHelper;
use ContentEgg\application\admin\import\ProductImportScheduler;
use ContentEgg\application\models\ImportQueueModel;
use ContentEgg\application\Plugin;

use function ContentEgg\prn;
use function ContentEgg\prnx;

defined('ABSPATH') || exit;

/**
 * ImportQueueApi - Handles AJAX requests to enqueue new import jobs.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2025 keywordrush.com
 */

class ImportQueueApi
{

    public static function init(): void
    {
        // Authenticated users
        add_action('wp_ajax_cegg_import_enqueue', [__CLASS__, 'handle_enqueue']);
    }

    /**
     * AJAX handler to enqueue an import job.
     * Expects POST:
     *  - nonce           (string)  'cegg_import' nonce field
     *  - preset_id       (int)
     *  - module_id       (string)
     *  - keyword         (string, optional)
     *  - payload         (JSON-encoded array, optional)
     *  - category_id     (int, optional)
     *  - scheduled_at    (string MySQL datetime, optional)
     */
    public static function handle_enqueue(): void
    {
        // Verify nonce
        if (empty($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cegg_import'))
        {
            wp_send_json_error(['message' => __('Invalid nonce', 'content-egg')], 400);
        }

        // Capability check
        if (!current_user_can('edit_posts'))
        {
            wp_send_json_error(['message' => __('Insufficient permissions', 'content-egg')], 403);
        }

        // Gather & sanitize inputs
        $preset_id    = isset($_POST['preset_id']) ? absint($_POST['preset_id']) : 0;
        $module_id    = isset($_POST['module_id']) ? sanitize_text_field($_POST['module_id']) : '';
        $keyword      = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
        $category_id  = isset($_POST['category_id']) ? absint($_POST['category_id']) : null;
        $scheduled_at = isset($_POST['scheduled_at'])
            ? sanitize_text_field($_POST['scheduled_at'])
            : null;

        // Decode payload if provided
        $payload = [];
        if (!empty($_POST['payload']))
        {
            $decoded = json_decode(stripslashes($_POST['payload']), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
            {
                $payload = $decoded;

                if (isset($payload['_descriptionText']))
                {
                    unset($payload['_descriptionText']);
                }
                if (isset($payload['_priceFormatted']))
                {
                    unset($payload['_priceFormatted']);
                }
                if (isset($payload['_priceOldFormatted']))
                {
                    unset($payload['_priceOldFormatted']);
                }
            }
        }

        // Basic validation
        if (!$preset_id || !$module_id)
        {
            $message = __('Missing required parameters', 'content-egg');
            if (Plugin::isDevEnvironment())
            {
                if (!$preset_id)
                {
                    $message .= ' | ' . 'Preset ID';
                }
                if (!$module_id)
                {
                    $message .= ' | ' . 'Module ID';
                }
            }
            wp_send_json_error(['message' => $message], 422);
        }

        $preset = PresetRepository::get($preset_id);
        if (!$preset)
        {
            wp_send_json_error(
                ['message' => __('Preset not found', 'content-egg')],
                404
            );
        }

        // If a post/product already exists with this unique_id, abort
        if (!empty($preset['avoid_duplicates']))
        {
            $unique_id = isset($payload['unique_id'])
                ? sanitize_text_field($payload['unique_id'])
                : '';

            if ('' !== $unique_id)
            {
                $existing_post_id = PostHelper::getPostIdByUniqueId($unique_id);
                if ($existing_post_id)
                {
                    wp_send_json_error(
                        [
                            'message'   => __('Product exists', 'content-egg'),
                            'unique_id' => $unique_id,
                            'post_id'   => $existing_post_id,
                        ],
                        409
                    );
                }

                $existing_job_id = ImportQueueModel::model()->findByUniqueId($unique_id);
                if ($existing_job_id)
                {
                    wp_send_json_error(
                        [
                            'message'   => __('Import job exists', 'content-egg'),
                            'unique_id' => $unique_id,
                            'import_job_id' => $existing_job_id,
                        ],
                        409
                    );
                }
            }
        }

        // Enqueue
        $queue = ImportQueueModel::model();
        $job_id = $queue->enqueue(
            $preset_id,
            $module_id,
            $payload,
            $keyword,
            $category_id,
            $scheduled_at
        );

        if ($job_id)
        {
            ProductImportScheduler::addScheduleEvent();
            wp_send_json_success(['job_id' => $job_id]);
        }
        else
        {
            wp_send_json_error(['message' => __('Failed to enqueue job', 'content-egg')], 500);
        }
    }
}
