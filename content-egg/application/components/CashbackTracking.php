<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || exit;

/**
 * CashbackTracking class file
 *
 * Turning a product link into one Cashback Tracker can credit.
 *
 * Deliberately the same two steps, in the same order, as Cashback Tracker's own
 * WooCommerce integration (WooTracking::generateTrackingLink): stamp a link that
 * is already a network deeplink, and otherwise build a deeplink for a shop it
 * knows. Anything else is returned untouched.
 *
 * Step two is what makes the integration useful at all. Content Egg's feed and
 * scraper modules mostly emit a plain merchant URL - an AliExpress product link
 * is a bare aliexpress.com URL - and step one can do nothing with those, so on
 * its own it fires almost nowhere.
 *
 * Every entry point is guarded: this file loads on sites with no Cashback
 * Tracker at all, and on sites running an older one.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class CashbackTracking
{
    /**
     * Admitad is reachable as a network AND listed as an advertiser, so a link
     * to admitad.com would otherwise be wrapped in an Admitad deeplink pointing
     * back at Admitad. Cashback Tracker skips it for the same reason.
     */
    const SKIP_DOMAINS = array('admitad.com');

    /** domain => advertiser|false. One block can hold ten rows of one shop. */
    private static $advertisers = array();

    public static function isActive()
    {
        return class_exists('\CashbackTracker\application\Plugin')
            && class_exists('\CashbackTracker\application\components\DeeplinkGenerator');
    }

    /**
     * The link to send the reader to, tracked where that is possible.
     *
     * Never throws and never returns empty: a link that cannot be tracked is
     * still a link that has to work.
     */
    public static function trackingUrl($url)
    {
        $url = trim((string) $url);

        if ($url === '' || !self::isActive())
            return $url;

        $generator = '\CashbackTracker\application\components\DeeplinkGenerator';

        try
        {
            // Already a deeplink of a network Cashback Tracker knows: add the
            // member's subid and stop.
            //
            // Stopping is the point. Cashback Tracker's WooCommerce version
            // tests whether the URL CHANGED, which for a signed-out visitor it
            // never does - maybeAddTracking() returns it untouched - so that
            // version falls through and can wrap an existing deeplink inside a
            // second one. Asking whether it IS a deeplink answers the question
            // that was actually being asked.
            if ($generator::getModuleIfTrackableUrl($url))
                return (string) $generator::maybeAddTracking($url);

            if (!$domain = self::domainOf($url))
                return $url;

            if (in_array($domain, self::SKIP_DOMAINS, true))
                return $url;

            if (!$advertiser = self::advertiserFor($domain))
                return $url;

            $deeplink = $generator::generateTrackingLink($advertiser['module_id'], $advertiser['id'], $url);

            return $deeplink ? (string) $deeplink : $url;
        }
        catch (\Throwable $e)
        {
            // A network the operator has since removed, an advertiser row with
            // no deeplink template - both are ordinary, and neither is worth a
            // fatal on a page of products.
            return $url;
        }
    }

    /**
     * Cashback Tracker matches its stored domain as an exact string, with no
     * normalizing on either side - s.click.aliexpress.com and aliexpress.com
     * are two different shops to it. So the host has to be reduced the way IT
     * reduces one, using its own helper rather than ours.
     */
    private static function domainOf($url)
    {
        if (!class_exists('\CashbackTracker\application\helpers\TextHelper'))
            return '';

        $helper = '\CashbackTracker\application\helpers\TextHelper';

        return (string) $helper::getHostName($url);
    }

    private static function advertiserFor($domain)
    {
        if (array_key_exists($domain, self::$advertisers))
            return self::$advertisers[$domain];

        if (!class_exists('\CashbackTracker\application\components\AdvertiserManager'))
            return self::$advertisers[$domain] = false;

        $manager = '\CashbackTracker\application\components\AdvertiserManager';
        $advertiser = $manager::getInstance()->findAdvertiserByDomain($domain);

        if (!$advertiser || empty($advertiser['module_id']) || empty($advertiser['id']))
            return self::$advertisers[$domain] = false;

        return self::$advertisers[$domain] = $advertiser;
    }

    public static function resetCache()
    {
        self::$advertisers = array();
    }
}
