<?php

namespace ContentEgg\application\modules\Pexels;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ExtraData;

/**
 * ExtraDataPexels class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ExtraDataPexels extends ExtraData
{

	public $photographer;
	public $photographer_url;
	public $photographer_id;
	public $avg_color;
	public $alt;
	public $width;
	public $height;
}
