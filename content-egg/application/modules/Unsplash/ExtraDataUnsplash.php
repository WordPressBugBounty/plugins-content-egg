<?php

namespace ContentEgg\application\modules\Unsplash;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\ExtraData;

/**
 * ExtraDataUnsplash class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class ExtraDataUnsplash extends ExtraData
{

	public $description;
	public $alt_description;
	public $width;
	public $height;
	public $color;
	public $blur_hash;
	public $user;
	public $username;
	public $user_url;
	public $photo_url;
}
