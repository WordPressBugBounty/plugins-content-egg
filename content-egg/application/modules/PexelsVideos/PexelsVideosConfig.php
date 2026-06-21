<?php

namespace ContentEgg\application\modules\PexelsVideos;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ParserModuleConfig;
use ContentEgg\application\modules\Pexels\PexelsConfig;

/**
 * PexelsVideosConfig class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class PexelsVideosConfig extends ParserModuleConfig
{

	public function options()
	{
		$options = array(
			'key'                     => array(
				'title'       => 'API Key <span class="cegg_required">*</span>',
				'description' => __('API Key for the Pexels API. Register <a href="https://www.pexels.com/api/">here</a> to get one.', 'content-egg'),
				'callback'    => array($this, 'render_password'),
				'default'     => '',
				'validator'   => array(
					'trim',
					array(
						'call'    => array('\ContentEgg\application\helpers\FormValidator', 'required'),
						'when'    => 'is_active',
						'message' => __('The "API Key" can not be empty', 'content-egg'),
					),
				),
				'section'     => 'default',
			),
			'entries_per_page'        => array(
				'title'       => __('Results', 'content-egg'),
				'description' => __('Specify the number of results to display for a single search query.', 'content-egg'),
				'callback'    => array($this, 'render_input'),
				'default'     => 15,
				'validator'   => array(
					'trim',
					'absint',
					array(
						'call'    => array('\ContentEgg\application\helpers\FormValidator', 'less_than_equal_to'),
						'arg'     => 80,
						'message' => __('Field "Results" can not be more than 80.', 'content-egg'),
					),
				),
				'section'     => 'default',
			),
			'entries_per_page_update' => array(
				'title'       => __('Results for autoupdates ', 'content-egg'),
				'description' => __('Maximum number of results returned for keyword autoupdates and other automatic searches.', 'content-egg'),
				'callback'    => array($this, 'render_input'),
				'default'     => 5,
				'validator'   => array(
					'trim',
					'absint',
					array(
						'call'    => array('\ContentEgg\application\helpers\FormValidator', 'less_than_equal_to'),
						'arg'     => 80,
						'message' => __('Field "Results for autoupdates" can not be more than 80.', 'content-egg'),
					),
				),
				'section'     => 'default',
			),
			'quality'                 => array(
				'title'            => __('Video quality', 'content-egg'),
				'description'      => 'HD selects the highest-resolution file, SD the lowest.',
				'callback'         => array($this, 'render_dropdown'),
				'dropdown_options' => array(
					'hd' => 'HD',
					'sd' => 'SD',
				),
				'default'          => 'hd',
				'section'          => 'default',
			),
			'orientation'             => array(
				'title'            => __('Orientation', 'content-egg'),
				'description'      => 'Filter by video orientation.',
				'callback'         => array($this, 'render_dropdown'),
				'dropdown_options' => array(
					'all'       => __('All', 'content-egg'),
					'landscape' => 'Landscape',
					'portrait'  => 'Portrait',
					'square'    => 'Square',
				),
				'default'          => 'all',
				'section'          => 'default',
				'metaboxInit'      => true,
			),
			'min_size'                => array(
				'title'            => __('Minimum size', 'content-egg'),
				'description'      => 'Minimum video size.',
				'callback'         => array($this, 'render_dropdown'),
				'dropdown_options' => array(
					''       => __('Any', 'content-egg'),
					'large'  => 'Large (4K)',
					'medium' => 'Medium (Full HD)',
					'small'  => 'Small (HD)',
				),
				'default'          => '',
				'section'          => 'default',
				'metaboxInit'      => true,
			),
			'locale'                  => array(
				'title'            => __('Locale', 'content-egg'),
				'description'      => 'The locale of the search you are performing.',
				'callback'         => array($this, 'render_dropdown'),
				'dropdown_options' => PexelsConfig::localeOptions(),
				'default'          => '',
				'section'          => 'default',
				'metaboxInit'      => true,
			),
			'save_img'                => array(
				'title'       => __('Save images', 'content-egg'),
				'description' => __('Save the video poster image to your server. The video file itself is always hotlinked from Pexels.', 'content-egg'),
				'callback'    => array($this, 'render_checkbox'),
				'default'     => true,
				'section'     => 'default',
			),
		);

		$options = array_merge(parent::options(), $options);
		return self::moveRequiredUp($options);
	}
}
