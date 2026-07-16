<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

use ContentEgg\application\helpers\ImageHelper;
use ContentEgg\application\helpers\TextHelper;
use ContentEgg\application\Plugin;

/**
 * ParserModule abstract class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
abstract class ParserModule extends Module
{

    const PARSER_TYPE_CONTENT = 'CONTENT';
    const PARSER_TYPE_PRODUCT = 'PRODUCT';
    const PARSER_TYPE_COUPON = 'COUPON';
    const PARSER_TYPE_IMAGE = 'IMAGE';
    const PARSER_TYPE_VIDEO = 'VIDEO';
    const PARSER_TYPE_OTHER = 'OTHER';

    abstract public function doRequest($keyword, $query_params = array(), $is_autoupdate = false);

    abstract public function getParserType();

    public function isActive()
    {
        if ($this->is_active === null)
        {
            if ($this->getConfigInstance()->option('is_active'))
            {
                $this->is_active = true;
            }
            else
            {
                $this->is_active = false;
            }
        }

        return $this->is_active;
    }

    final public function isParser()
    {
        return true;
    }

    public function isUrlSearchAllowed()
    {
        return false;
    }

    public function presavePrepare($data, $post_id)
    {
        global $post;
        $data = parent::presavePrepare($data, $post_id);

        // do not save images for revisions & search results
        if (($post && wp_is_post_revision($post_id)) || $post_id < 0)
        {
            return $data;
        }

        $old_data = ContentManager::getData($post_id, $this->getId());

        foreach ($data as $key => $item)
        {
            // fill domain
            if (empty($item['domain']))
            {
                if (!empty($item['orig_url']))
                {
                    $url = $item['orig_url'];
                }
                elseif (!empty($item['img']))
                {
                    $url = $item['img'];
                }
                else
                {
                    $url = $item['url'];
                }

                if ($url)
                {
                    $domain = TextHelper::getHostName($url);
                    if (!in_array($domain, array('buscape.com.br', 'avlws.com')))
                    {
                        $data[$key]['domain'] = $item['domain'] = $domain;
                    }
                }
            }
            // save img
            if ($this->config('save_img') && !wp_is_post_revision($post_id))
            {
                // The metabox round-trips img_file, so a non-empty img_file here means this item
                // already has a locally saved image. If its img no longer matches the saved one,
                // the user edited or removed the image URL in the metabox and we must honor that
                // instead of restoring the previously saved value.
                $old_img = isset($old_data[$key]['img']) ? $old_data[$key]['img'] : '';
                $user_changed_img = (!empty($item['img_file']) && $item['img'] !== $old_img);
                if ($user_changed_img)
                {
                    // drop the stale local reference so a new URL is downloaded and a removed image stays removed
                    $item['img_file'] = '';
                }

                // check old_data also. need for fix behavior with "preview changes" button and by keyword update
                if (!$user_changed_img && isset($old_data[$key]) && !empty($old_data[$key]['img_file']) && file_exists(ImageHelper::getFullImgPath($old_data[$key]['img_file'])))
                {
                    // image exists
                    $item['img'] = $old_data[$key]['img'];
                    $item['img_file'] = $old_data[$key]['img_file'];
                }
                elseif ($item['img'] && empty($item['img_file']))
                {
                    $local_img_name = ImageHelper::saveImgLocaly($item['img'], $item['title']);
                    if ($local_img_name)
                    {
                        $uploads = \wp_upload_dir();
                        $item['img'] = $uploads['url'] . '/' . $local_img_name;
                        $item['img_file'] = ltrim(trailingslashit($uploads['subdir']), '\/') . $local_img_name;
                    }
                }
                $data[$key] = $item;
            }
        }

        return $data;
    }

    public static function getFullImgPath($img_path)
    {
        // Delegate to the hardened, traversal-safe resolver.
        return ImageHelper::getFullImgPath($img_path);
    }

    public function defaultTemplateName()
    {
        return 'data_simple';
    }

    public function viewDataPrepare($data)
    {
        if (!is_array($data) || empty($data))
        {
            return $data;
        }

        return $data;
    }

    public function getAccessToken($force = false)
    {
        $transient_name = Plugin::slug() . '-' . $this->getId() . '-access_token';
        $token = \get_transient($transient_name);

        if (!$token || $force)
        {
            list($token, $expires_in) = $this->requestAccessToken();

            \set_transient($transient_name, $token, (int) $expires_in);
        }

        return $token;
    }

    public function isFeedParser()
    {
        if ($this->getIdStatic() == ModuleManager::FEED_MODULES_PREFIX)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    public function doMultipleRequests($keyword, $query_params = array(), $is_autoupdate = false)
    {
        list($keywords, $groups) = ContentManager::prepareMultipleKeywords($keyword);

        $this->search_notice = null;
        $results = array();
        foreach ($keywords as $i => $keyword)
        {
            if ($i && $this->getId() == 'Amazon' && \apply_filters('cegg_amazon_one_request_per_second', true))
                sleep(1);

            try
            {
                $data = $this->doRequest($keyword, $query_params, $is_autoupdate);
            }
            catch (\Exception $e)
            {
                $kcount     = count($keywords);
                $code       = (int) $e->getCode();
                $retryable  = [408, 425, 429, 500, 502, 503, 504]; // timeouts, rate limit, transient 5xx

                // If multiple keywords AND error is retryable → skip to next keyword
                if ($kcount > 1 && in_array($code, $retryable, true))
                {
                    continue;
                }

                // Otherwise, rethrow (single keyword, or non-retryable error)
                throw new \RuntimeException(
                    $this->formatErrorMessage($e->getMessage()),
                    (int) ($code ?: 0)
                );
            }

            if (!empty($groups[$i]))
            {
                foreach ($data as $key => $d)
                {
                    $data[$key]->group = $groups[$i];
                }
            }

            foreach ($data as $key => $d)
            {
                $data[$key]->module_id = $this->getId();
            }

            $results = array_merge($results, $data);
        }

        $results = self::filterDuplicateItems($results);

        return $results;
    }

    /**
     * Sanitize an exception message before it is shown in the metabox search
     * results (rendered via ng-bind-html). Default: plain text, all tags
     * stripped. Modules may override to allow safe inline HTML — e.g. an
     * actionable link in an error hint.
     */
    protected function formatErrorMessage($message)
    {
        return esc_html(wp_strip_all_tags($message));
    }

    protected $search_notice = null;

    /**
     * A soft, user-facing notice about the last interactive search — guidance
     * rather than a failure (e.g. "no results, try a direct URL"). Shown as an
     * info notice in the metabox, separate from thrown errors; null when none.
     */
    public function getSearchNotice()
    {
        return $this->search_notice;
    }

    private static function filterDuplicateItems(array $items)
    {
        $results = array();

        foreach ($items as $item)
        {
            $dup = false;
            foreach ($results as $result)
            {
                if ($item->unique_id == $result->unique_id)
                {
                    $dup = true;
                    break;
                }
            }

            if (!$dup)
            {
                $results[] = $item;
            }
        }

        return $results;
    }
}
