<?php

namespace ContentEgg\application\admin;

defined('\ABSPATH') || exit;

use ContentEgg\application\models\AutoblogModel;

use function ContentEgg\prnx;

/**
 * PrefillLogTable class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2025 keywordrush.com
 */
class PrefillLogTable extends MyListTable
{
    const per_page = 50;

    function default_orderby()
    {
        return 'updated_at';
    }

    function default_order()
    {
        return 'asc';
    }

    function get_columns()
    {
        $columns = array_merge(
            array(
                'post_id' => AutoblogModel::model()->getAttributeLabel('post_id'),
                'status' => AutoblogModel::model()->getAttributeLabel('status'),
                'log' => AutoblogModel::model()->getAttributeLabel('log'),
                'updated_at' => AutoblogModel::model()->getAttributeLabel('updated_at'),
            )
        );
        return $columns;
    }

    function get_bulk_actions()
    {
        return array();
    }

    function column_post_id($item)
    {
        $post_id = (int) $item['post_id'];
        $post = get_post($post_id);

        if (!$post)
        {
            return sprintf('<span class="text-muted">#%d</span>', $post_id);
        }

        $title = get_the_title($post_id);
        $edit_link = get_edit_post_link($post_id);
        $view_link = get_permalink($post_id);

        return sprintf(
            '<a href="%s" target="_blank">%s</a><br><small><a href="%s" target="_blank" class="text-muted">#%d</a></small>',
            esc_url($edit_link),
            esc_html($title ?: __('(no title)', 'content-egg')),
            esc_url($view_link),
            $post_id
        );
    }

    function column_status($item)
    {
        $status = strtolower($item['status']);
        $label = ucfirst($status);

        $class = match ($status)
        {
            'done'       => 'bg-success',
            'failed'     => 'bg-danger',
            'pending'    => 'bg-secondary',
            default      => 'bg-secondary',
        };

        return sprintf(
            '<span class="badge %s">%s</span>',
            esc_attr('badge ' . $class),
            esc_html($label)
        );
    }

    function column_log($item)
    {
        $log_content = '';

        if (!empty($item['log']))
        {
            $log_content .= '<div class="cegg-log">' . wp_kses(
                $item['log'],
                ['br' => [], 'em' => [], 'strong' => [], 'b' => [], 'code' => []]
            ) . '</div>';
        }

        if (!empty($item['processing_time']))
        {
            $processing_time = round((float) $item['processing_time']);

            if ($processing_time <= 0)
            {
                $display_time = '< 1s';
            }
            else
            {
                $display_time = $processing_time . 's';
            }

            $log_content .= sprintf(
                '<div class="cegg-log-meta small text-muted mt-1">%s %s</div>',
                esc_html__('Time:', 'content-egg'),
                esc_html($display_time)
            );
        }

        return $log_content ?: '-';
    }

    function column_updated_at($item)
    {
        return $this->view_column_datetime($item, 'updated_at');
    }

    function column_processing_time($item)
    {
        if (empty($item['processing_time']))
        {
            return '<span class="text-muted">—</span>';
        }

        $time = (float) $item['processing_time'];

        if ($time < 0.001)
        {
            // Edge case: very tiny values
            return '<span class="text-muted">&lt;1ms</span>';
        }

        $formatted_time = sprintf('%.3f', $time);

        return sprintf(
            '<span class="badge bg-secondary">%s s</span>',
            esc_html($formatted_time)
        );
    }

    function get_sortable_columns()
    {
        $sortable_columns = array(
            'post_id' => array('post_id', true),
            'status' => array('status', true),
            'updated_at' => array('updated_at', true),
        );

        return $sortable_columns;
    }
}
