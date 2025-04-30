<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

/**
 * ParserModuleConfig abstract class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2025 keywordrush.com
 */
abstract class AffiliateParserModuleConfig extends ParserModuleConfig
{

	public function options()
	{
		$options = array();

		if ($this->getModuleInstance()->isItemsUpdateAvailable())
		{
			$options['ttl_items'] = array(
				'title'       => __('Price Update', 'content-egg'),
				'description' => __("Set the interval in seconds for updating prices. Use '0' to disable price updates.", 'content-egg'),
				'callback'    => array($this, 'render_input'),
				'default'     => 259200,
				'validator'   => array(
					'trim',
					'absint',
				),
				'section'     => 'default',
			);
		}

		$options['ttl'] = array(
			'title'       => __('Update by Keyword', 'content-egg'),
			'description' => __('Cache lifetime in seconds. After this period, products will be updated if a keyword is set for updating. Set to \'0\' to disable updates.', 'content-egg'),
			'callback'    => array($this, 'render_input'),
			'default'     => 604800, // 7 days in seconds
			'validator'   => array(
				'trim',
				'absint',
			),
			'section'     => 'default',
		);

		$options['update_mode'] = array(
			'title'            => __('Update Mode', 'content-egg'),
			'description'      => __('Choose how product updates are triggered.', 'content-egg'),
			'callback'         => array($this, 'render_dropdown'),
			'dropdown_options' => array(
				'visit'      => __('Page View', 'content-egg'),
				'cron'       => __('Cron Job', 'content-egg'),
				'visit_cron' => __('Page View + Cron Job', 'content-egg'),
			),
			'default'          => 'visit',
		);

		return
			array_merge(
				parent::options(),
				$options
			);
	}
}
