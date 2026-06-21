<?php

namespace ContentEgg\application\modules\Unsplash;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ParserModuleConfig;

/**
 * UnsplashConfig class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class UnsplashConfig extends ParserModuleConfig
{

	public function options()
	{
		$options = array(
			'key'                     => array(
				'title'       => 'Access Key <span class="cegg_required">*</span>',
				'description' => __('Access Key for the Unsplash API. Register an application <a href="https://unsplash.com/developers">here</a> to get one.', 'content-egg'),
				'callback'    => array($this, 'render_password'),
				'default'     => '',
				'validator'   => array(
					'trim',
					array(
						'call'    => array('\ContentEgg\application\helpers\FormValidator', 'required'),
						'when'    => 'is_active',
						'message' => __('The "Access Key" can not be empty', 'content-egg'),
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
						'arg'     => 30,
						'message' => __('Field "Results" can not be more than 30.', 'content-egg'),
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
						'arg'     => 30,
						'message' => __('Field "Results for autoupdates" can not be more than 30.', 'content-egg'),
					),
				),
				'section'     => 'default',
			),
			'size'                    => array(
				'title'            => __('Image size', 'content-egg'),
				'callback'         => array($this, 'render_dropdown'),
				'dropdown_options' => array(
					'small'   => __('Small', 'content-egg'),
					'regular' => __('Medium', 'content-egg'),
					'full'    => __('Large', 'content-egg'),
				),
				'default'          => 'regular',
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
					'squarish'  => 'Squarish',
				),
				'default'          => 'all',
				'section'          => 'default',
				'metaboxInit'      => true,
			),
			'color'                   => array(
				'title'            => __('Color', 'content-egg'),
				'description'      => 'Filter results by color.',
				'callback'         => array($this, 'render_dropdown'),
				'dropdown_options' => array(
					''                => __('All', 'content-egg'),
					'black_and_white' => 'Black and white',
					'black'           => 'Black',
					'white'           => 'White',
					'yellow'          => 'Yellow',
					'orange'          => 'Orange',
					'red'             => 'Red',
					'purple'          => 'Purple',
					'magenta'         => 'Magenta',
					'green'           => 'Green',
					'teal'            => 'Teal',
					'blue'            => 'Blue',
				),
				'default'          => '',
				'section'          => 'default',
				'metaboxInit'      => true,
			),
			'order'                   => array(
				'title'            => __('Sorting', 'content-egg'),
				'description'      => 'How the results should be ordered.',
				'callback'         => array($this, 'render_dropdown'),
				'dropdown_options' => array(
					'relevant' => 'Relevant',
					'latest'   => 'Latest',
				),
				'default'          => 'relevant',
				'section'          => 'default',
				'metaboxInit'      => true,
			),
			'collections'             => array(
				'title'       => __('Collections', 'content-egg'),
				'description' => __('Optional. Comma-separated public collection IDs to narrow the search.', 'content-egg'),
				'callback'    => array($this, 'render_input'),
				'default'     => '',
				'validator'   => array('trim'),
				'section'     => 'default',
			),
			'safesearch'              => array(
				'title'       => __('Safe search', 'content-egg'),
				'description' => 'Limit results to content suitable for all audiences.',
				'callback'    => array($this, 'render_checkbox'),
				'default'     => false,
				'section'     => 'default',
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
}
