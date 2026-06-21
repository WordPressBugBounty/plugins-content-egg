<?php

namespace ContentEgg\application\modules\PexelsVideos;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ParserModule;
use ContentEgg\application\libs\pexels\PexelsClient;
use ContentEgg\application\components\Content;
use ContentEgg\application\admin\PluginAdmin;

/**
 * PexelsVideosModule class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class PexelsVideosModule extends ParserModule
{

	public function info()
	{
		return array(
			'name'          => 'Pexels Videos',
			'description'   => __('Search free stock videos on pexels.com', 'content-egg'),
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
		return self::PARSER_TYPE_VIDEO;
	}

	public function defaultTemplateName()
	{
		return 'data_video';
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

		if ($this->config('locale'))
		{
			$options['locale'] = $this->config('locale');
		}

		try
		{
			$api_client = new PexelsClient($this->config('key'));
			$results    = $api_client->searchVideos($keyword, $options);
		}
		catch (\Exception $e)
		{
			throw new \Exception(esc_html(wp_strip_all_tags($e->getMessage())));
		}

		if (!empty($results['error']))
		{
			throw new \Exception(esc_html(wp_strip_all_tags($results['error'])));
		}

		if (!isset($results['videos']))
		{
			return array();
		}

		return $this->prepareResults($results['videos']);
	}

	private function prepareResults($results)
	{
		$data = array();
		foreach ($results as $r)
		{
			$content            = new Content;
			$content->unique_id = $r['id'];
			$content->title     = !empty($r['url']) ? $this->titleFromUrl($r['url']) : '';
			$content->url       = !empty($r['url']) ? $r['url'] : '';
			$content->img       = !empty($r['image']) ? $r['image'] : '';

			$video_url = '';
			if (!empty($r['video_files']) && is_array($r['video_files']))
				$video_url = $this->pickVideoFile($r['video_files'], $this->config('quality'));

			$extra            = new ExtraDataPexelsVideos;
			$extra->video_url = $video_url;
			if (!empty($r['duration']))
				$extra->duration = $r['duration'];
			if (!empty($r['width']))
				$extra->width = $r['width'];
			if (!empty($r['height']))
				$extra->height = $r['height'];
			if (!empty($r['user']['name']))
			{
				$extra->user   = $r['user']['name'];
				$extra->author = $r['user']['name'];
			}
			if (!empty($r['user']['url']))
				$extra->user_url = $r['user']['url'];
			if (!empty($r['url']))
				$extra->source = $r['url'];

			$content->extra = $extra;
			$data[]         = $content;
		}

		return $data;
	}

	private function titleFromUrl($url)
	{
		$path = parse_url($url, PHP_URL_PATH);
		if (!$path)
			return '';

		$slug = basename(trim($path, '/'));
		$slug = preg_replace('/-\d+$/', '', $slug);
		$slug = trim(str_replace('-', ' ', $slug));
		if ($slug === '')
			return '';

		return ucfirst($slug);
	}

	private function pickVideoFile(array $files, $quality)
	{
		$mp4 = array();
		foreach ($files as $f)
		{
			if (!empty($f['file_type']) && $f['file_type'] === 'video/mp4' && !empty($f['link']))
				$mp4[] = $f;
		}

		if (!$mp4)
			return '';

		usort($mp4, function ($a, $b)
		{
			return ((int) $a['width']) <=> ((int) $b['width']);
		});

		if ($quality === 'sd')
			$chosen = $mp4[0];
		else
			$chosen = $mp4[count($mp4) - 1];

		return $chosen['link'];
	}

	public function renderResults()
	{
		PluginAdmin::render('_metabox_results', array('module_id' => $this->getId()));
	}

	public function renderSearchResults()
	{
		PluginAdmin::render('_metabox_search_results', array('module_id' => $this->getId()));
	}
}
