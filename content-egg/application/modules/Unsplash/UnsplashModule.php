<?php

namespace ContentEgg\application\modules\Unsplash;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ParserModule;
use ContentEgg\application\libs\unsplash\UnsplashSearch;
use ContentEgg\application\components\Content;
use ContentEgg\application\admin\PluginAdmin;
use ContentEgg\application\admin\GeneralConfig;

/**
 * UnsplashModule class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class UnsplashModule extends ParserModule
{

	public function info()
	{
		return array(
			'name'          => 'Unsplash',
			'description'   => __('Search free high-resolution photos on unsplash.com', 'content-egg'),
			'api_agreement' => 'https://unsplash.com/api-terms',
		);
	}

	public function releaseVersion()
	{
		return '20.5.0';
	}

	public function releaseVersionFree()
	{
		return '11.2.0';
	}

	public function getParserType()
	{
		return self::PARSER_TYPE_IMAGE;
	}

	public function defaultTemplateName()
	{
		return 'data_image';
	}

	public function isFree()
	{
		return true;
	}

	public function doRequest($keyword, $query_params = array(), $is_autoupdate = false)
	{
		$options = array();

		if ($is_autoupdate)
		{
			$options['per_page'] = $this->config('entries_per_page_update');
		}
		else
		{
			$options['per_page'] = $this->config('entries_per_page');
		}

		$options['order_by'] = $this->config('order');

		if ($this->config('orientation') && $this->config('orientation') != 'all')
		{
			$options['orientation'] = $this->config('orientation');
		}

		if ($this->config('color'))
		{
			$options['color'] = $this->config('color');
		}

		if ($this->config('collections'))
		{
			$options['collections'] = $this->config('collections');
		}

		if ($this->config('safesearch'))
		{
			$options['content_filter'] = 'high';
		}

		$lang = GeneralConfig::getInstance()->option('lang');
		if ($lang)
		{
			$options['lang'] = $lang;
		}

		try
		{
			$api_client = new UnsplashSearch($this->config('key'));
			$results    = $api_client->search($keyword, $options);
		}
		catch (\Exception $e)
		{
			throw new \Exception(esc_html(wp_strip_all_tags($e->getMessage())));
		}

		if (!empty($results['errors']) && is_array($results['errors']))
		{
			throw new \Exception(esc_html(wp_strip_all_tags(implode(' ', $results['errors']))));
		}

		if (!isset($results['results']))
		{
			return array();
		}

		return $this->prepareResults($results['results']);
	}

	private function prepareResults($results)
	{
		$data = array();
		foreach ($results as $key => $r)
		{
			$content            = new Content;
			$content->unique_id = $r['id'];

			if (!empty($r['description']))
				$title = $r['description'];
			elseif (!empty($r['alt_description']))
				$title = $r['alt_description'];
			else
				$title = '';
			$content->title = strip_tags($title);

			$content->url = !empty($r['links']['html']) ? $r['links']['html'] : '';

			$size = $this->config('size');
			if (!empty($r['urls'][$size]))
				$content->img = $r['urls'][$size];
			elseif (!empty($r['urls']['regular']))
				$content->img = $r['urls']['regular'];
			else
				$content->img = '';

			$extra = new ExtraDataUnsplash;
			ExtraDataUnsplash::fillAttributes($extra, $r);

			if (!empty($r['user']['name']))
			{
				$extra->user   = $r['user']['name'];
				$extra->author = $r['user']['name'];
			}
			if (!empty($r['user']['username']))
				$extra->username = $r['user']['username'];
			if (!empty($r['user']['links']['html']))
				$extra->user_url = $r['user']['links']['html'];
			if (!empty($r['links']['html']))
			{
				$extra->photo_url = $r['links']['html'];
				$extra->source    = $r['links']['html'];
			}

			$content->extra = $extra;
			$data[]         = $content;
		}

		return $data;
	}

	public function renderResults()
	{
		PluginAdmin::render('_metabox_results', array('module_id' => $this->getId()));
	}

	public function renderSearchResults()
	{
		PluginAdmin::render('_metabox_search_results_images', array('module_id' => $this->getId()));
	}
}
