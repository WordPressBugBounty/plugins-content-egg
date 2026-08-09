<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

use ContentEgg\application\Plugin;
use ContentEgg\application\models\ProductModel;

/**
 * ReviewNotice class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ReviewNotice
{

	private static $instance = null;

	const MIN_PRODUCTS_TRIGGER = 30;
	const MIN_DAYS_TRIGGER = 7;
	const PRODUC_COUNT_TTL = 86400;

	// Key that suppresses the notice. Permanent dismiss writes it with no
	// expiration; "Maybe later" writes it with SNOOZE_DURATION so it returns.
	// Stored durably: a transient would be evicted by an object cache, turning
	// "dismiss forever" into "dismiss for an hour".
	const HIDE_TRANSIENT = 'cegg_hide_notice_review_products_trigger_v2';
	const SNOOZE_DURATION = 1209600; // 2 weeks

	public static function getInstance()
	{
		if (self::$instance == null)
		{
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function adminInit()
	{
		\add_action('admin_notices', array($this, 'displayNotice'));
		$this->hideNotice();
	}

	public function displayNotice()
	{

		if (!isset($_SERVER['REQUEST_URI']))
		{
			return;
		}

		if (!$this->isPluginScreen())
		{
			return;
		}

		if (DurableTransient::get(self::HIDE_TRANSIENT))
		{
			return;
		}

		$last_sync = ProductModel::model()->getLastSync();
		if (!$last_sync || time() - $last_sync > self::PRODUC_COUNT_TTL)
		{
			ProductModel::model()->maybeScanProducts();
		}

		$total = ProductModel::model()->count();
		if ($total < self::MIN_PRODUCTS_TRIGGER)
		{
			return;
		}

		if (!$this->enoughDaysPassed())
		{
			return;
		}

		$rate_url    = 'https://wordpress.org/support/plugin/' . Plugin::getSlug() . '/reviews/?filter=5#new-post';
		$contact_url = 'https://www.keywordrush.com/contact';
		$page_url    = \get_admin_url(\get_current_blog_id(), 'admin.php?page=content-egg-product');

		$snooze_url  = $this->actionUrl('snooze');
		$dismiss_url = $this->actionUrl('dismiss');

		$logo = \esc_url(\ContentEgg\PLUGIN_RES . '/img/logo.png');

		$this->addInlineCss();
		?>
		<div class="notice notice-success cegg-review-notice">
			<div class="cegg-rn-inner">
				<img src="<?php echo $logo; ?>" alt="<?php echo \esc_attr(Plugin::getName()); ?>" />
				<div class="cegg-rn-body">
					<h3 style="font-size:18px;font-weight:600;line-height:1.4;margin:0 0 10px;">
						<?php
						printf(
							/* translators: %1$s product count, %2$s plugin name */
							\esc_html__('You\'ve added %1$s products with %2$s 🎉', 'content-egg'),
							'<a href="' . \esc_url($page_url) . '">' . \esc_html(\number_format_i18n($total)) . '</a>',
							\esc_html(Plugin::getName())
						);
						?>
					</h3>
					<p style="font-size:15px;line-height:1.6;margin:0 0 14px;max-width:680px;">
						<?php echo \esc_html__('Hi, I\'m Serg, the solo developer behind Content Egg. If the plugin has helped your site, please leave a quick WordPress.org review — it really helps keep the project alive.', 'content-egg'); ?>
					</p>
					<div class="cegg-rn-actions">
						<a class="button button-primary cegg-rn-rate" target="_blank" rel="noopener" href="<?php echo \esc_url($rate_url); ?>">
							<span class="cegg-rn-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</span>
							<?php echo \esc_html__('Leave a 5-star review', 'content-egg'); ?>
						</a>
						<a class="button" href="<?php echo \esc_url($snooze_url); ?>"><?php echo \esc_html__('Maybe later', 'content-egg'); ?></a>
						<a class="cegg-rn-support" target="_blank" rel="noopener" href="<?php echo \esc_url($contact_url); ?>"><?php echo \esc_html__('Something not working?', 'content-egg'); ?></a>
					</div>
				</div>
			</div>
			<a class="cegg-rn-dismiss" href="<?php echo \esc_url($dismiss_url); ?>"><?php echo \esc_html__('Don\'t show this again', 'content-egg'); ?></a>
		</div>
		<?php
	}

	private function enoughDaysPassed()
	{
		$activation = (int) \get_option(Plugin::slug . '_first_activation_date', 0);

		// Missing date (installs predating the option) -> treat as eligible so we don't block the ask.
		if (!$activation)
		{
			return true;
		}

		return (time() - $activation) >= (self::MIN_DAYS_TRIGGER * 86400);
	}

	private function isPluginScreen()
	{
		if (empty($_GET['page']))
		{
			return false;
		}

		// CE admin pages are admin.php?page=content-egg* (and hidden options.php?page=content-egg* submenus).
		return (strpos(\sanitize_key($_GET['page']), Plugin::slug) === 0);
	}

	private function actionUrl($action)
	{
		return \add_query_arg(array(
			'cegg_hide_notice'   => $action,
			'_cegg_notice_nonce' => \wp_create_nonce('hide_notice'),
		), \esc_url_raw(\wp_unslash($_SERVER['REQUEST_URI'])));
	}

	public function hideNotice()
	{
		if (!isset($_SERVER['REQUEST_URI']))
		{
			return;
		}

		if (!isset($_GET['cegg_hide_notice']))
		{
			return;
		}

		if (!isset($_GET['_cegg_notice_nonce']) || !\wp_verify_nonce(\sanitize_key($_GET['_cegg_notice_nonce']), 'hide_notice'))
		{
			return;
		}

		$action = \sanitize_text_field(\wp_unslash($_GET['cegg_hide_notice']));

		if (!in_array($action, array('snooze', 'dismiss'), true))
		{
			return;
		}

		// dismiss = forever (expiration 0), snooze = come back after SNOOZE_DURATION.
		$expiration = ($action === 'snooze') ? self::SNOOZE_DURATION : 0;
		DurableTransient::set(self::HIDE_TRANSIENT, time(), $expiration);

		\wp_safe_redirect(\remove_query_arg(array(
			'cegg_hide_notice',
			'_cegg_notice_nonce'
		), \esc_url_raw(\wp_unslash($_SERVER['REQUEST_URI']))));
		exit;
	}

	public function addInlineCss()
	{
		?>
		<style>
			.cegg-review-notice { position: relative; padding: 16px 18px; border-left-color: #00a32a; }
			.cegg-review-notice .cegg-rn-inner { display: flex; align-items: flex-start; gap: 16px; padding-right: 180px; }
			.cegg-review-notice img { width: 48px; height: auto; flex: 0 0 auto; margin-top: 2px; }
			.cegg-review-notice .cegg-rn-body { min-width: 0; }
			.cegg-review-notice h3 { margin: 0 0 10px; font-size: 18px; font-weight: 600; line-height: 1.4; }
			.cegg-review-notice h3 a { text-decoration: none; }
			.cegg-review-notice p { margin: 0 0 14px; max-width: 680px; font-size: 15px; line-height: 1.6; }
			.cegg-review-notice .cegg-rn-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
			.cegg-review-notice .cegg-rn-rate.button-primary { background: #00a32a; border-color: #008a20; box-shadow: none; text-shadow: none; }
			.cegg-review-notice .cegg-rn-rate.button-primary:hover { background: #008a20; border-color: #007017; }
			.cegg-review-notice .cegg-rn-stars { color: #ffb900; letter-spacing: 1px; margin-right: 6px; }
			.cegg-review-notice .cegg-rn-support { font-size: 13px; }
			.cegg-review-notice .cegg-rn-dismiss { position: absolute; top: 12px; right: 14px; font-size: 13px; text-decoration: none; }
			@media (max-width: 782px) {
				.cegg-review-notice .cegg-rn-inner { padding-right: 0; }
				.cegg-review-notice .cegg-rn-dismiss { position: static; display: inline-block; margin: 10px 0 0 64px; }
			}
		</style>
		<?php
	}
}
