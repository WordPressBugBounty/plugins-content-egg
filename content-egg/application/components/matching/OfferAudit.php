<?php

namespace ContentEgg\application\components\matching;

defined('\ABSPATH') || exit;

/**
 * OfferAudit class file
 *
 * What needs attention on one page: findings from the stored state of the page. The
 * exchange rate comes in through the `rate` callable; this class makes no request. The caller (the audit-offers ability) assembles the context; this
 * class only reads it, so it is pure and table-testable.
 *
 * Without a passport the page is not curated: it reports `no_passport`, plus the two checks
 * that need nothing to compare with, `out_of_stock` and `price_outlier`. With a passport:
 *
 *  - per offer: `unverified` (no accept or reject decision), `rejected_attached` (a rejected
 *    offer is on the page), `fingerprint_changed` (accepted, but its title or GTINs are no
 *    longer what was accepted), `out_of_stock`, `non_new` (a condition the passport does not
 *    accept; an offer that does not say counts as new) and `price_outlier`;
 *  - per page: ONE `discovery_due` finding listing the GTIN-searching modules not searched for the
 *    page in `due_days` (only when the passport has a GTIN to search with; detail
 *    {count, modules: [{module_id, last_at?, mode?}]}, the last two left out for a module never
 *    searched) and `keyword_suspended` for each product module whose stored keyword is no longer
 *    searched.
 *
 * Rejected and out-of-stock offers are left out of the price check altogether: they do not move the
 * median and are not flagged as outliers (they already get `rejected_attached` / `out_of_stock`).
 * Rejections only count on a page with a passport, the only page where they are reported.
 *
 * Context ($ctx):
 *  - passport           ?array   ProductPassport::get(); null or [] = none.
 *  - decisions          array    OfferDecisions::all(): "<module_id>:<unique_id>" => entry.
 *  - discovery          array    DiscoveryLog::all(): module id => {at, mode, ...}.
 *  - offers             array[]  The attached product offers in LeanProduct::matchItem() shape
 *                                with `module_id` (the module they are attached under) and
 *                                `unique_id`. Optional `raw`: the stored item itself. The
 *                                decision's fingerprint is taken from the raw item (title and
 *                                the GTIN fields), so a changed offer is detected on the same
 *                                fields it was decided on; without `raw` the lean title and
 *                                GTINs stand in for it.
 *  - gtin_module_ids    string[] The active modules that can search by GTIN.
 *  - keyword_module_ids string[] The modules whose stored keyword the passport suspends.
 *  - now, due_days      int      The clock and the age after which a search is due again.
 *  - rate               ?callable function (string $from, string $to): float, 0 = unknown.
 *                                The only way another currency is converted; null = same
 *                                currency only.
 *
 * Finding: {type, module_id?, unique_id?, title?, detail?}; the types are TYPES. Offer findings
 * carry module_id, unique_id and title; `keyword_suspended` carries module_id; `no_passport` and
 * `discovery_due` carry neither (the latter names its modules in detail).
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 *
 */
class OfferAudit
{

	const NO_PASSPORT = 'no_passport';
	const UNVERIFIED = 'unverified';
	const FINGERPRINT_CHANGED = 'fingerprint_changed';
	const REJECTED_ATTACHED = 'rejected_attached';
	const OUT_OF_STOCK = 'out_of_stock';
	const PRICE_OUTLIER = 'price_outlier';
	const NON_NEW = 'non_new';
	const DISCOVERY_DUE = 'discovery_due';
	const KEYWORD_SUSPENDED = 'keyword_suspended';

	const TYPES = array(
		self::NO_PASSPORT,
		self::UNVERIFIED,
		self::FINGERPRINT_CHANGED,
		self::REJECTED_ATTACHED,
		self::OUT_OF_STOCK,
		self::PRICE_OUTLIER,
		self::NON_NEW,
		self::DISCOVERY_DUE,
		self::KEYWORD_SUSPENDED,
	);

	/**
	 * Priced offers (in stock, not rejected, in the most common currency) needed before any of them
	 * can be called an outlier: the median of two offers is only their mean, so each is always
	 * "in range".
	 */
	const MIN_OUTLIER_OFFERS = 3;

	// stock_status of an offer that is out of stock (LeanProduct::matchItem()).
	const STOCK_OUT = -1;

	/**
	 * The findings for one page, in a fixed order: no_passport, then each offer in turn
	 * (decision, stock, condition, price), then the modules (discovery, keywords).
	 *
	 * @param array $ctx See the class comment.
	 * @return array[] List of {type, module_id?, unique_id?, title?, detail?}.
	 */
	public static function findings(array $ctx): array
	{
		$passport = (isset($ctx['passport']) && is_array($ctx['passport']) && $ctx['passport']) ? $ctx['passport'] : null;
		$decisions = (isset($ctx['decisions']) && is_array($ctx['decisions'])) ? $ctx['decisions'] : array();
		$rate = (isset($ctx['rate']) && is_callable($ctx['rate'])) ? $ctx['rate'] : null;
		$offers = self::offers($ctx);

		$findings = array();
		if ($passport === null)
		{
			$findings[] = self::finding(self::NO_PASSPORT);
		}

		// The decision of each offer; only a page with a passport has decisions that matter.
		$entries = array();
		$left_out = array();
		foreach ($offers as $i => $offer)
		{
			$entries[$i] = null;
			if ($passport !== null)
			{
				$key = OfferDecisions::key($offer['module_id'], $offer['unique_id']);
				$entries[$i] = (isset($decisions[$key]) && is_array($decisions[$key])) ? $decisions[$key] : null;
			}

			$rejected = $entries[$i] !== null && isset($entries[$i]['status']) && $entries[$i]['status'] === OfferDecisions::STATUS_REJECTED;
			if ($rejected || $offer['stock_status'] === self::STOCK_OUT)
			{
				$left_out[$i] = true;
			}
		}

		$outliers = self::priceOutliers($offers, $left_out, $rate);

		foreach ($offers as $i => $offer)
		{
			if ($passport !== null)
			{
				$findings = array_merge($findings, self::decisionFindings($offer, $entries[$i]));
			}

			if ($offer['stock_status'] === self::STOCK_OUT)
			{
				$findings[] = self::offerFinding(self::OUT_OF_STOCK, $offer);
			}

			if ($passport !== null)
			{
				$condition = OfferMatcher::conditionSignal($passport, $offer);
				if (!$condition['allowed'])
				{
					$findings[] = self::offerFinding(self::NON_NEW, $offer, array('condition' => $condition['value']));
				}
			}

			if (isset($outliers[$i]))
			{
				$findings[] = self::offerFinding(self::PRICE_OUTLIER, $offer, $outliers[$i]);
			}
		}

		if ($passport !== null)
		{
			$findings = array_merge($findings, self::moduleFindings($passport, $ctx));
		}

		return $findings;
	}

	// ---------------------------------------------------------------- offers

	/**
	 * unverified, rejected_attached or fingerprint_changed for one offer.
	 *
	 * @param array      $offer A row of offers().
	 * @param array|null $entry The offer's decision, null = none.
	 */
	private static function decisionFindings(array $offer, ?array $entry): array
	{
		$status = ($entry !== null && isset($entry['status']) && is_string($entry['status'])) ? $entry['status'] : '';

		if ($status === OfferDecisions::STATUS_REJECTED)
		{
			return array(self::offerFinding(self::REJECTED_ATTACHED, $offer));
		}

		if ($status !== OfferDecisions::STATUS_ACCEPTED)
		{
			return array(self::offerFinding(self::UNVERIFIED, $offer));
		}

		if (!OfferDecisions::fingerprintChanged($entry, $offer['item']))
		{
			return array();
		}

		$was = null;
		if (isset($entry['fp']) && is_array($entry['fp']) && isset($entry['fp']['title']) && isset($entry['fp']['gtins']) && is_array($entry['fp']['gtins']))
		{
			$was = array('title' => (string) $entry['fp']['title'], 'gtins' => array_values(array_map('strval', $entry['fp']['gtins'])));
		}

		return array(self::offerFinding(self::FINGERPRINT_CHANGED, $offer, array(
			'was' => $was,
			'now' => OfferDecisions::fingerprint($offer['item']),
		)));
	}

	/**
	 * The offers of the context as the audit reads them. 'item' is what the decision's
	 * fingerprint is compared with: the raw stored item, else one rebuilt from the lean
	 * title and GTINs.
	 *
	 * @return array[]
	 */
	private static function offers(array $ctx): array
	{
		$offers = array();
		if (!isset($ctx['offers']) || !is_array($ctx['offers']))
		{
			return $offers;
		}

		foreach ($ctx['offers'] as $offer)
		{
			if (!is_array($offer))
			{
				continue;
			}

			$title = self::text($offer, 'title');
			$gtins = (isset($offer['gtins']) && is_array($offer['gtins'])) ? $offer['gtins'] : array();
			$price = (isset($offer['price']) && is_numeric($offer['price']) && (float) $offer['price'] > 0) ? (float) $offer['price'] : null;

			$offers[] = array(
				'module_id' => self::text($offer, 'module_id'),
				'unique_id' => self::text($offer, 'unique_id'),
				'title' => $title,
				'condition' => self::text($offer, 'condition'),
				'stock_status' => isset($offer['stock_status']) && is_numeric($offer['stock_status']) ? (int) $offer['stock_status'] : 0,
				'price' => $price,
				'currency' => strtoupper(self::text($offer, 'currency')),
				'item' => (isset($offer['raw']) && is_array($offer['raw']))
					? $offer['raw']
					: array('title' => $title, 'extra' => array('EANs' => array_values($gtins))),
			);
		}

		return $offers;
	}

	/**
	 * The offers whose price is far from the usual one, by index in $offers: the median is taken
	 * over the priced offers in the most common currency (the first seen wins a tie) and only
	 * when there are MIN_OUTLIER_OFFERS of them. An offer in another currency is compared when
	 * $rate knows the rate, else left out; an offer with no currency cannot be compared at all.
	 * The offers in $left_out (rejected, out of stock) are neither in the pool nor flagged.
	 *
	 * @param array[]       $offers   offers()
	 * @param array         $left_out index => true
	 * @param callable|null $rate
	 * @return array index => {ratio, median, currency}
	 */
	private static function priceOutliers(array $offers, array $left_out, ?callable $rate): array
	{
		$counts = array();
		foreach ($offers as $i => $offer)
		{
			if (!isset($left_out[$i]) && $offer['price'] !== null && $offer['currency'] !== '')
			{
				$counts[$offer['currency']] = (isset($counts[$offer['currency']]) ? $counts[$offer['currency']] : 0) + 1;
			}
		}

		// arsort() is not stable before PHP 8: walk the counts so the first currency seen wins a tie.
		$target = '';
		$best = 0;
		foreach ($counts as $currency => $count)
		{
			if ($count > $best)
			{
				$target = (string) $currency;
				$best = $count;
			}
		}
		if ($best < self::MIN_OUTLIER_OFFERS)
		{
			return array();
		}

		$prices = array();
		foreach ($offers as $i => $offer)
		{
			if (!isset($left_out[$i]) && $offer['price'] !== null && $offer['currency'] === $target)
			{
				$prices[] = $offer['price'];
			}
		}
		$median = OfferMatcher::median($prices);
		if ($median === null || $median <= 0)
		{
			return array();
		}

		$outliers = array();
		foreach ($offers as $i => $offer)
		{
			if (isset($left_out[$i]) || $offer['price'] === null || $offer['currency'] === '')
			{
				continue;
			}

			$price = $offer['price'];
			if ($offer['currency'] !== $target)
			{
				$factor = $rate === null ? 0.0 : (float) call_user_func($rate, $offer['currency'], $target);
				if (!($factor > 0) || !is_finite($factor))
				{
					continue;
				}
				$price *= $factor;
			}

			$ratio = $price / $median;
			if (OfferMatcher::isPriceOutlier($ratio))
			{
				// The test is exact; only the reported figures are rounded.
				$outliers[$i] = array('ratio' => round($ratio, 3), 'median' => round($median, 2), 'currency' => $target);
			}
		}

		return $outliers;
	}

	// ---------------------------------------------------------------- modules

	/**
	 * One discovery_due finding naming the due GTIN modules (only when the passport has a GTIN to
	 * search with, and only when some module is due) and a keyword_suspended per module.
	 *
	 * @return array[]
	 */
	private static function moduleFindings(array $passport, array $ctx): array
	{
		$findings = array();

		if (self::hasGtin($passport))
		{
			$discovery = (isset($ctx['discovery']) && is_array($ctx['discovery'])) ? $ctx['discovery'] : array();
			$now = isset($ctx['now']) ? (int) $ctx['now'] : 0;
			$due_days = isset($ctx['due_days']) ? (int) $ctx['due_days'] : DiscoveryLog::DUE_DAYS;

			$due = array();
			foreach (self::moduleIds($ctx, 'gtin_module_ids') as $module_id)
			{
				$entry = (isset($discovery[$module_id]) && is_array($discovery[$module_id])) ? $discovery[$module_id] : null;
				// A search by keyword does not count: it was not by the passport's GTIN.
				if (!DiscoveryLog::isIdentifierSearchDue($entry, $now, $due_days))
				{
					continue;
				}

				// A module never searched has neither a time nor a mode to report.
				$row = array('module_id' => $module_id);
				if ($entry !== null && isset($entry['at']) && is_numeric($entry['at']))
				{
					$row['last_at'] = (int) $entry['at'];
				}
				if ($entry !== null && isset($entry['mode']) && is_string($entry['mode']) && $entry['mode'] !== '')
				{
					$row['mode'] = $entry['mode'];
				}
				$due[] = $row;
			}

			if ($due)
			{
				$findings[] = self::finding(self::DISCOVERY_DUE, '', '', '', array('count' => count($due), 'modules' => $due));
			}
		}

		foreach (self::moduleIds($ctx, 'keyword_module_ids') as $module_id)
		{
			$findings[] = self::finding(self::KEYWORD_SUSPENDED, $module_id);
		}

		return $findings;
	}

	/**
	 * Whether the passport holds at least one GTIN to search with.
	 */
	private static function hasGtin(array $passport): bool
	{
		if (!isset($passport['gtins']) || !is_array($passport['gtins']))
		{
			return false;
		}

		foreach ($passport['gtins'] as $row)
		{
			$code = is_array($row) ? (isset($row['code']) ? $row['code'] : '') : $row;
			if (is_scalar($code) && trim((string) $code) !== '')
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * The distinct, non-empty module ids of a list in the context, in order.
	 *
	 * @return string[]
	 */
	private static function moduleIds(array $ctx, string $key): array
	{
		$ids = array();
		if (!isset($ctx[$key]) || !is_array($ctx[$key]))
		{
			return $ids;
		}

		foreach ($ctx[$key] as $id)
		{
			if (is_scalar($id) && (string) $id !== '' && !in_array((string) $id, $ids, true))
			{
				$ids[] = (string) $id;
			}
		}

		return $ids;
	}

	// ---------------------------------------------------------------- helpers

	private static function offerFinding(string $type, array $offer, array $detail = array()): array
	{
		return self::finding($type, $offer['module_id'], $offer['unique_id'], $offer['title'], $detail);
	}

	/**
	 * A finding; module_id, unique_id, title and detail are left out when empty.
	 */
	private static function finding(string $type, string $module_id = '', string $unique_id = '', string $title = '', array $detail = array()): array
	{
		$finding = array('type' => $type);
		if ($module_id !== '')
		{
			$finding['module_id'] = $module_id;
		}
		if ($unique_id !== '')
		{
			$finding['unique_id'] = $unique_id;
		}
		if ($title !== '')
		{
			$finding['title'] = $title;
		}
		if ($detail)
		{
			$finding['detail'] = $detail;
		}

		return $finding;
	}

	private static function text(array $source, string $key): string
	{
		return (isset($source[$key]) && is_scalar($source[$key])) ? trim((string) $source[$key]) : '';
	}
}
