<?php

namespace ContentEgg\application\modules\Pexels;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ParserModuleConfig;

/**
 * PexelsConfig class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class PexelsConfig extends ParserModuleConfig
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
				'default'     => 20,
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
			'size'                    => array(
				'title'            => __('Image size', 'content-egg'),
				'callback'         => array($this, 'render_dropdown'),
				'dropdown_options' => array(
					'medium'   => __('Small', 'content-egg'),
					'large'    => __('Medium', 'content-egg'),
					'original' => __('Large', 'content-egg'),
				),
				'default'          => 'original',
				'section'          => 'default',
			),
			'orientation'             => array(
				'title'            => __('Orientation', 'content-egg'),
				'description'      => 'Filter by photo orientation.',
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
				'description'      => 'Minimum photo size.',
				'callback'         => array($this, 'render_dropdown'),
				'dropdown_options' => array(
					''       => __('Any', 'content-egg'),
					'large'  => 'Large (24MP)',
					'medium' => 'Medium (12MP)',
					'small'  => 'Small (4MP)',
				),
				'default'          => '',
				'section'          => 'default',
				'metaboxInit'      => true,
			),
			'color'                   => array(
				'title'            => __('Color', 'content-egg'),
				'description'      => 'Filter results by color.',
				'callback'         => array($this, 'render_dropdown'),
				'dropdown_options' => array(
					''          => __('All', 'content-egg'),
					'red'       => 'Red',
					'orange'    => 'Orange',
					'yellow'    => 'Yellow',
					'green'     => 'Green',
					'turquoise' => 'Turquoise',
					'blue'      => 'Blue',
					'violet'    => 'Violet',
					'pink'      => 'Pink',
					'brown'     => 'Brown',
					'black'     => 'Black',
					'gray'      => 'Gray',
					'white'     => 'White',
				),
				'default'          => '',
				'section'          => 'default',
				'metaboxInit'      => true,
			),
			'locale'                  => array(
				'title'            => __('Locale', 'content-egg'),
				'description'      => 'The locale of the search you are performing.',
				'callback'         => array($this, 'render_dropdown'),
				'dropdown_options' => self::localeOptions(),
				'default'          => '',
				'section'          => 'default',
				'metaboxInit'      => true,
			),
			'save_img'                => array(
				'title'       => __('Save images', 'content-egg'),
				'description' => __('Save images to your server.', 'content-egg'),
				'callback'    => array($this, 'render_checkbox'),
				'default'     => true,
				'section'     => 'default',
			),
		);

		$options = array_merge(parent::options(), $options);
		return self::moveRequiredUp($options);
	}

	public static function localeOptions()
	{
		$locales = array(
			'en-US', 'pt-BR', 'es-ES', 'ca-ES', 'de-DE', 'it-IT', 'fr-FR', 'sv-SE',
			'id-ID', 'pl-PL', 'ja-JP', 'zh-TW', 'zh-CN', 'ko-KR', 'th-TH', 'nl-NL',
			'hu-HU', 'vi-VN', 'cs-CZ', 'da-DK', 'fi-FI', 'uk-UA', 'el-GR', 'ro-RO',
			'nb-NO', 'sk-SK', 'tr-TR', 'ru-RU',
		);
		$options = array('' => __('None', 'content-egg'));
		foreach ($locales as $l)
		{
			$options[$l] = $l;
		}

		return $options;
	}
}
