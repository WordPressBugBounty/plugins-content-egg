<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\components\ContentManager;
use ContentEgg\application\components\ModuleManager;

/**
 * FindPostsAbility class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Find existing posts to operate on with the other post-scoped abilities
 * (get-post-blocks, add-products-to-post, set-featured-image, ...). Read-only.
 * Scoped by default to the post types Content Egg is enabled on, and filterable
 * by keyword, status, type, id and — Content Egg specific — the presence of a
 * given product module's data.
 */
final class FindPostsAbility extends AbilityBase
{
    const DEFAULT_LIMIT = 10;
    const MAX_LIMIT = 50;

    public function name(): string
    {
        return 'content-egg/find-posts';
    }

    public function label(): string
    {
        return __('Find Posts', 'content-egg');
    }

    public function description(): string
    {
        return 'Finds existing posts to edit with the other abilities (get-post-blocks, '
            . 'add-products-to-post, set-featured-image, set-post-status). Read-only. '
            . 'Filter by search (title/keyword), id, status (draft/pending/publish/future/'
            . 'private), post_type, has_module (only posts that already carry that product '
            . "module's data), and limit. Defaults to the post types Content Egg is enabled "
            . 'on. Returns id, title, status, type and edit_url for each match.';
    }

    public function inputSchema(): array
    {
        return array(
            'type' => array('object', 'null'),
            'properties' => array(
                'search' => array(
                    'type' => 'string',
                    'description' => 'Keyword matched against post title and content.',
                ),
                'id' => array(
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => 'Look up a single post by id.',
                ),
                'status' => array(
                    'type' => 'string',
                    'enum' => array('any', 'draft', 'pending', 'publish', 'future', 'private'),
                    'default' => 'any',
                ),
                'post_type' => array(
                    'type' => 'string',
                    'description' => 'Restrict to one post type (defaults to Content Egg-enabled types).',
                ),
                'has_module' => array(
                    'type' => 'string',
                    'description' => 'Only posts that already have this product module\'s data attached.',
                ),
                'limit' => array(
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => self::MAX_LIMIT,
                    'default' => self::DEFAULT_LIMIT,
                ),
            ),
            'additionalProperties' => false,
        );
    }

    public function outputSchema(): array
    {
        return array(
            'type' => 'object',
            'properties' => array(
                'count' => array('type' => 'integer'),
                'posts' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'properties' => array(
                            'id' => array('type' => 'integer'),
                            'title' => array('type' => 'string'),
                            'status' => array('type' => 'string'),
                            'type' => array('type' => 'string'),
                            'edit_url' => array('type' => 'string'),
                        ),
                    ),
                ),
            ),
        );
    }

    public function checkPermission($input = null): bool
    {
        return \current_user_can('edit_posts');
    }

    public function execute(array $input): array
    {
        $limit = min(self::MAX_LIMIT, max(1, (int) ($input['limit'] ?? self::DEFAULT_LIMIT)));

        $cegg_types = array_values((array) GeneralConfig::getInstance()->option('post_types'));
        if (!$cegg_types)
        {
            $cegg_types = array('post', 'page');
        }

        // post_type: an explicit override must be a real, CE-scoped type.
        $post_type = trim((string) ($input['post_type'] ?? ''));
        if ($post_type !== '')
        {
            if (!in_array($post_type, $cegg_types, true))
            {
                throw new AbilityInputException(
                    "post_type '{$post_type}' is not a Content Egg-enabled type. Enabled: "
                        . implode(', ', $cegg_types) . '.'
                );
            }
            $types = array($post_type);
        }
        else
        {
            $types = $cegg_types;
        }

        $status = (string) ($input['status'] ?? 'any');
        $post_status = $status === 'any'
            ? array('publish', 'future', 'draft', 'pending', 'private')
            : array($status);

        $args = array(
            'post_type' => $types,
            'post_status' => $post_status,
            // Restrict to posts the caller can edit. Without 'perm', WP_Query
            // returns every requested status (draft/pending/future/private)
            // site-wide regardless of author, disclosing other authors' unpublished
            // content (and, via 's', a keyword oracle over it). 'editable' scopes
            // non-public statuses to the caller's own posts unless they hold
            // edit_others_posts; the per-result edit_post check below is the
            // authoritative backstop for mapped/custom capabilities.
            'perm' => 'editable',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
        );

        if (!empty($input['id']))
        {
            $args['p'] = (int) $input['id'];
        }

        $search = trim((string) ($input['search'] ?? ''));
        if ($search !== '')
        {
            $args['s'] = $search;
        }

        $has_module = trim((string) ($input['has_module'] ?? ''));
        if ($has_module !== '')
        {
            if (!ModuleManager::getInstance()->moduleExists($has_module))
            {
                throw new AbilityInputException(
                    "Unknown module '{$has_module}'. Call content-egg/list-modules for available module ids."
                );
            }
            $args['meta_query'] = array(
                array(
                    'key' => ContentManager::META_PREFIX_DATA . $has_module,
                    'compare' => 'EXISTS',
                ),
            );
        }

        $query = new \WP_Query($args);

        $posts = array();
        foreach ($query->posts as $post)
        {
            // Authoritative per-object gate: the ability's purpose is to surface
            // posts the caller can edit, so never leak a post (title/id/status) the
            // caller has no edit rights on — covers CPT capability mappings and the
            // generic-cap fallback WP_Query uses when several post types are queried.
            if (!\current_user_can('edit_post', $post->ID))
            {
                continue;
            }

            $posts[] = array(
                'id' => (int) $post->ID,
                // get_the_title() runs the `the_title` filter, which entity-encodes.
                // Decode so agent-facing JSON matches get-post-products, which
                // returns the same title as plain text.
                'title' => \html_entity_decode((string) \get_the_title($post), ENT_QUOTES, 'UTF-8'),
                'status' => (string) $post->post_status,
                'type' => (string) $post->post_type,
                'edit_url' => (string) \get_edit_post_link($post->ID, 'raw'),
            );
        }

        return array(
            'count' => count($posts),
            'posts' => $posts,
        );
    }
}
