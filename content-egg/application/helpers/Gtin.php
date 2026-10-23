<?php

namespace ContentEgg\application\helpers;

defined('\ABSPATH') || exit;

/**
 * Gtin class file
 *
 * Canonical GTIN handling: every valid EAN-8 / UPC-A / EAN-13 / GTIN-14 /
 * ISBN-10 is reduced to a checksum-verified 14-digit GTIN so that the same
 * product code written in different forms compares equal.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 *
 */
class Gtin
{

	/**
	 * Canonical 14-digit GTIN, or '' when the input is not a valid code.
	 *
	 * Accepts 8/12/13/14 digits (spaces and hyphens ignored) and ISBN-10
	 * (converted to its 978... EAN-13). Rejects bad checksums and all-zero codes.
	 */
	public static function normalize(string $raw): string
	{
		$code = str_replace(array(' ', '-'), '', $raw);

		if (!preg_match('/[1-9]/', $code))
		{
			return '';
		}

		if (preg_match('/^\d{9}[\dXx]$/', $code))
		{
			if (!self::isIsbn10Valid($code))
			{
				return '';
			}
			$code = '978' . substr($code, 0, 9);
			$code .= self::checkDigit($code);
		}

		if (!preg_match('/^(\d{8}|\d{12,14})$/', $code))
		{
			return '';
		}

		if (self::checkDigit(substr($code, 0, -1)) !== $code[strlen($code) - 1])
		{
			return '';
		}

		return str_pad($code, 14, '0', STR_PAD_LEFT);
	}

	public static function isValid(string $raw): bool
	{
		return self::normalize($raw) !== '';
	}

	/**
	 * EAN-13 form of a GTIN-14 (13 digits), or '' when the GTIN-14 does not start with 0.
	 */
	public static function toEan13(string $gtin14): string
	{
		if (strlen($gtin14) !== 14 || $gtin14[0] !== '0')
		{
			return '';
		}

		return substr($gtin14, 1);
	}

	/**
	 * Human-facing form: the EAN-13 when there is one, else the GTIN-14.
	 */
	public static function display(string $gtin14): string
	{
		$ean13 = self::toEan13($gtin14);

		return $ean13 !== '' ? $ean13 : $gtin14;
	}

	/**
	 * Why a GTIN-14 cannot identify one product, or '' when it can.
	 *
	 * - 'restricted': prefixes GS1 reserves for in-store use (EAN-13 02, 04, 2;
	 *   EAN-8 starting 0 or 2). Every retailer prints its own, so the same code
	 *   on two shops is two different products.
	 * - 'coupon': coupon and refund-receipt prefixes (EAN-13 05, 98, 99).
	 * - 'placeholder': made-up fillers such as 1234567890128 or a repeated digit.
	 *
	 * Takes the GTIN-14 from normalize(); anything else gives ''.
	 */
	public static function issue(string $gtin14): string
	{
		if (!preg_match('/^\d{14}$/', $gtin14))
		{
			return '';
		}

		$ean13 = self::toEan13($gtin14);
		if ($ean13 !== '')
		{
			$body = substr($ean13, 0, 12);
			if (preg_match('/^(\d)\1{11}$/', $body) || $body === '123456789012' || $body === '012345678901')
			{
				return 'placeholder';
			}
		}

		if (strpos($gtin14, '000000') === 0)
		{
			$first = $gtin14[6];

			return ($first === '0' || $first === '2') ? 'restricted' : '';
		}

		if ($ean13 === '')
		{
			return '';
		}

		if (in_array(substr($ean13, 0, 2), array('02', '04'), true) || $ean13[0] === '2')
		{
			return 'restricted';
		}
		if (in_array(substr($ean13, 0, 2), array('05', '98', '99'), true))
		{
			return 'coupon';
		}

		return '';
	}

	/**
	 * Unique display GTINs of an item, from ean, upc, isbn, extra.EANs[],
	 * extra.UPCs[], extra.gtin and extra.data.gtin (in that order).
	 */
	public static function fromItem(array $item): array
	{
		$candidates = array();
		foreach (array('ean', 'upc', 'isbn') as $key)
		{
			$candidates[] = isset($item[$key]) ? $item[$key] : null;
		}

		$extra = (isset($item['extra']) && is_array($item['extra'])) ? $item['extra'] : array();
		foreach (array('EANs', 'UPCs') as $key)
		{
			if (isset($extra[$key]) && is_array($extra[$key]))
			{
				$candidates = array_merge($candidates, array_values($extra[$key]));
			}
		}
		$candidates[] = isset($extra['gtin']) ? $extra['gtin'] : null;
		if (isset($extra['data']) && is_array($extra['data']))
		{
			$candidates[] = isset($extra['data']['gtin']) ? $extra['data']['gtin'] : null;
		}

		$result = array();
		foreach ($candidates as $candidate)
		{
			if (!is_scalar($candidate))
			{
				continue;
			}
			$gtin14 = self::normalize((string) $candidate);
			if ($gtin14 === '')
			{
				continue;
			}
			$display = self::display($gtin14);
			if (!in_array($display, $result, true))
			{
				$result[] = $display;
			}
		}

		return $result;
	}

	/**
	 * Mod-10 check digit (weights 3/1 from the right) for a code without its check digit.
	 */
	private static function checkDigit(string $payload): string
	{
		$sum = 0;
		$weight = 3;
		for ($i = strlen($payload) - 1; $i >= 0; $i--)
		{
			$sum += (int) $payload[$i] * $weight;
			$weight = 4 - $weight;
		}

		return (string) ((10 - $sum % 10) % 10);
	}

	private static function isIsbn10Valid(string $isbn): bool
	{
		$sum = 0;
		for ($i = 0; $i < 10; $i++)
		{
			$char = $isbn[$i];
			$value = ($char === 'X' || $char === 'x') ? 10 : (int) $char;
			$sum += $value * (10 - $i);
		}

		return $sum % 11 === 0;
	}
}
