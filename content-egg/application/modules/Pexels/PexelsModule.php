<?php

namespace ContentEgg\application\modules\Pexels;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ParserModule;
use ContentEgg\application\libs\pexels\PexelsClient;
use ContentEgg\application\components\Content;
use ContentEgg\application\admin\PluginAdmin;

/**
 * PexelsModule class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class PexelsModule extends ParserModule
{

	public function info()
	{
		return array(
			'name'          => 'Pexels',
			'description'   => __('Search free stock photos on pexels.com', 'content-egg'),
			'api_agreement' => 'https://www.pexels.com/api/',
			'docs_uri'      => 'https://ce-docs.keywordrush.com/modules/content/pexels',
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

		if ($this->config('orientation') && $this->config('orientation') != 'all')
		{
			$options['orientation'] = $this->config('orientation');
		}

		if ($this->config('min_size'))
		{
			$options['size'] = $this->config('min_size');
		}

		if ($this->config('color'))
		{
			$options['color'] = $this->config('color');
		}

		if ($this->config('locale'))
		{
			$options['locale'] = $this->config('locale');
		}

		try
		{
			$api_client = new PexelsClient($this->config('key'));
			$results    = $api_client->searchPhotos($keyword, $options);
		}
		catch (\Exception $e)
		{
			throw new \Exception(esc_html(wp_strip_all_tags($e->getMessage())));
		}

		if (!empty($results['error']))
		{
			throw new \Exception(esc_html(wp_strip_all_tags($results['error'])));
		}

		if (!isset($results['photos']))
		{
			return array();
		}

		return $this->prepareResults($results['photos']);
	}

	private function prepareResults($results)
	{
		$data = array();
		foreach ($results as $r)
		{
			$content            = new Content;
			$content->unique_id = $r['id'];
			$content->title     = !empty($r['alt']) ? strip_tags($r['alt']) : '';
			$content->url       = !empty($r['url']) ? $r['url'] : '';

			$size = $this->config('size');
			if (!empty($r['src'][$size]))
				$content->img = $r['src'][$size];
			elseif (!empty($r['src']['large']))
				$content->img = $r['src']['large'];
			else
				$content->img = '';

			$extra = new ExtraDataPexels;
			ExtraDataPexels::fillAttributes($extra, $r);

			if (!empty($r['photographer']))
				$extra->author = $r['photographer'];
			if (!empty($r['url']))
				$extra->source = $r['url'];

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
