<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ai\AiProcessor;
use ContentEgg\application\components\feed\FeedImportPendingException;
use ContentEgg\application\Plugin;
use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\helpers\TextHelper;

use function ContentEgg\prnx;

/**
 * ModuleApi class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ModuleApi
{

    const API_BASE = '-module-api';

    public function __construct()
    {
        \add_action('wp_ajax_content-egg-module-api', array($this, 'addApiEntryModule'));
        \add_action('wp_ajax_content-egg-ai-api', array($this, 'addApiEntryAi'));
        \add_action('wp_ajax_content-egg-smart-groups-api', array($this, 'addApiEntrySmartGroups'));
    }

    public static function apiBase()
    {
        return Plugin::slug . self::API_BASE;
    }

    public function addApiEntryAi()
    {
        if (!\current_user_can('edit_posts'))
            throw new \Exception("Access denied.");

        $this->verifyNonce();

        if (empty($_POST['module']))
        {
            die("Module is undefined.");
        }

        @set_time_limit(240);

        $module_id = TextHelper::clear(sanitize_text_field(wp_unslash($_POST['module'])));
        $parser = ModuleManager::getInstance()->parserFactory($module_id);

        if (!$parser || !$parser->isActive())
            die("The module " . esc_html($parser->getId()) . " is inactive.");

        if (isset($_POST['params']))
            $params = wp_unslash($_POST['params']); // phpcs:ignore
        else
            die("AI params is undefined.");

        $params = json_decode($params, true);

        if (!$params)
            die("Error: 'ai_params' parameter cannot be empty.");

        if (!isset($params['data']) || !isset($params['title_method']) || !isset($params['description_method']))
            die("Error: Invalid Parameters");

        $title_method = TextHelper::clear(sanitize_text_field(wp_unslash($params['title_method'])));
        $description_method = TextHelper::clear(sanitize_text_field(wp_unslash($params['description_method'])));
        $items = $params['data'];

        try
        {
            $items = AiProcessor::applayAiItems($items, $title_method, $description_method);
        }
        catch (\Exception $e)
        {
            $this->formatJson(array('error' => $e->getMessage()));
        }

        $this->formatJson(array('results' => $items, 'error' => ''));
    }

    public function addApiEntrySmartGroups()
    {
        if (!\current_user_can('edit_posts'))
            throw new \Exception("Access denied.");

        $this->verifyNonce();

        @set_time_limit(240);
        if (isset($_POST['params']))
            $params = wp_unslash($_POST['params']); // phpcs:ignore
        else
            die("AI params is undefined.");

        $params = json_decode($params, true);

        if (!$params)
            die("Error: 'ai_params' parameter cannot be empty.");

        if (!isset($params['data']) || !isset($params['method']))
            die("Error: Invalid Parameters");

        $method = TextHelper::clear(sanitize_text_field(wp_unslash($params['method'])));
        $items = $params['data'];

        try
        {
            $items = AiProcessor::applaySmartGroups($items, $method);
        }
        catch (\Exception $e)
        {
            $this->formatJson(array('error' => $e->getMessage()));
        }

        $this->formatJson(array('results' => $items, 'error' => ''));
    }

    public function addApiEntryModule()
    {
        if (!\current_user_can('edit_posts'))
        {
            throw new \Exception("Access denied.");
        }

        $this->verifyNonce();

        if (empty($_POST['module']))
        {
            die("Module is undefined.");
        }

        $module_id = TextHelper::clear(sanitize_text_field(wp_unslash($_POST['module'])));
        $parser = ModuleManager::getInstance()->parserFactory($module_id);

        if (!$parser || !$parser->isActive())
        {
            die("Parser module " . esc_html($parser->getId()) . " is inactive.");
        }

        if (isset($_POST['query']))
            $query = wp_unslash($_POST['query']); // phpcs:ignore
        else
            $query = '';

        $query = json_decode($query, true);

        if (!$query)
        {
            die("Error: 'query' parameter cannot be empty.");
        }

        if (empty($query['keyword']))
        {
            die("Error: 'keyword' parameter cannot be empty.");
        }

        $keyword = ProductSearchService::prepareKeyword($query['keyword']);

        if (!$keyword)
        {
            die("Error: 'keyword' parameter cannot be empty.");
        }

        $query = ProductSearchService::applyParamMaps($parser, $query);

        try
        {
            $data = $parser->doMultipleRequests($keyword, $query);
            $data = ProductSearchService::formatItems($data);

            $notice = method_exists($parser, 'getSearchNotice') ? (string) $parser->getSearchNotice() : '';
            $this->formatJson(array('results' => $data, 'error' => '', 'notice' => $notice));
        }
        catch (FeedImportPendingException $e)
        {
            // Not an error: the feed catalog is still being imported in the
            // background. The metabox keeps its loading state and retries.
            $this->formatJson(array(
                'results' => array(),
                'error' => '',
                'notice' => $e->getMessage(),
                'feed_importing' => true,
            ));
        }
        catch (\Exception $e)
        {
            $this->formatJson(array('error' => $e->getMessage()));
        }
    }

    /**
     * Verify the metabox nonce. When the post editor is left open past the nonce
     * lifetime the token goes stale; instead of the raw die(-1) that surfaces a
     * cryptic blob in the metabox, return a clear "session expired" error the UI
     * can act on (reload prompt).
     */
    private function verifyNonce()
    {
        if (\check_ajax_referer('contentegg-metabox', '_contentegg_nonce', false))
            return;

        $this->formatJson(array(
            'error' => \esc_html__('Your session has expired. Please reload the page and try again.', 'content-egg'),
            'session_expired' => true,
        ));
        exit;
    }

    public function formatJson($data)
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data);
        \wp_die();
    }
}
