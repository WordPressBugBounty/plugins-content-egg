<?php

namespace ContentEgg\application\helpers;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\components\ModuleManager;
use ContentEgg\application\components\ShopStore;



defined('\ABSPATH') || exit;

class LogoHelper
{
    public const MERCHANT_LOGO_DIR = 'ce-logos';

    // Logos the plugin downloaded itself, [file name => logo_source it came
    // from], so a Logo Source change replaces them and keeps files placed in
    // ce-logos by hand.
    public const DOWNLOADS_OPTION = 'cegg_logo_downloads';

    // A failed download leaves a blank marker; after this many days the logo
    // is tried again.
    public const BLANK_RETRY_DAYS = 7;

    /**
     * Default keyless source: the site's own icon via Google's favicon service.
     * Replaces Clearbit, which no longer serves images.
     */
    private const FAVICON_TEMPLATE = 'https://t2.gstatic.com/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&url=https://%s&size=128';

    private static array $logos = [];

    /**
     * The shop's logo: the Offer module's own logo, an admin custom logo, the
     * cached file, or one downloaded from the chosen source (website icon by
     * default), falling back to the website icon when that source has none.
     * $colorMode is kept for existing callers; the plugin no longer ships its
     * own light/dark logos, so it has no effect.
     */
    public static function getMerchantLogoUrl(array $item, $blankOnError = false, $colorMode = 'light')
    {

        // Respect module‑level config that disables large logos
        if (!empty($item['module_id']) && self::isLargeLogoDisabled($item['module_id']))
        {
            return $blankOnError ? self::getBlankImg() : false;
        }

        $hotlinking = (string) GeneralConfig::getInstance()->option('logo_hotlinking') === 'enabled';

        // Resolve preferred remote source (CDN / explicit link). A hot-linked
        // logo is shown as is, so it asks the service for its placeholder;
        // a downloaded one asks for a 404 instead, so a shop the service does
        // not know falls back to its website icon.
        $remoteUrl = self::resolveRemoteLogoUrl($item, !$hotlinking);
        // No usable domain (Sovrn gives only a shop name for shops it has no
        // domain for): the logo the module supplied is better than none.
        if ($remoteUrl === '' && self::hostOf($item) === '' && !empty($item['logo']) && filter_var($item['logo'], FILTER_VALIDATE_URL))
        {
            $remoteUrl = (string) $item['logo'];
        }
        if ($remoteUrl === '')
        {
            return $blankOnError ? self::getBlankImg() : false;
        }

        // Always run through the local‑cache resolver to honour precedence list.
        return self::getMerchantImageUrl(
            $item,
            '', // no filename prefix
            $remoteUrl,
            $blankOnError,
            $hotlinking,
            self::faviconUrlFor(self::hostOf($item))
        );
    }

    /** Determine if the module disables large logos. */
    private static function isLargeLogoDisabled($moduleId)
    {
        $parser = ModuleManager::getInstance()->parserFactory($moduleId);
        if (!$parser->getConfigInstance()->option_exists('show_large_logos'))
        {
            return false;
        }
        return !(bool) filter_var($parser->config('show_large_logos'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Public accessor for the provider-resolved remote logo URL.
     *
     * Returns the configured provider's logo URL (website icon / Brandfetch /
     * Logo.dev) for $item['domain'], or '' when no domain is available.
     * Exposed for external callers (e.g. Too Much Niche) that download and
     * store the logo themselves; resolution/config logic stays here (DRY).
     */
    public static function getRemoteLogoUrl(array $item): string
    {
        return (string) self::resolveRemoteLogoUrl($item);
    }

    /** Build remote URL based on chosen provider or explicit logo. */
    private static function resolveRemoteLogoUrl(array $item, bool $missing_404 = false)
    {
        // A per-shop override wins outright. Until the Shops screen existed
        // there was no way to set one, and the default provider (Clearbit) no
        // longer returns images - so a broken logo had no manual fix at all.
        if (!empty($item['domain']))
        {
            $shop = ShopStore::get($item['domain']);

            if ($shop && $shop['logo'] !== '' && filter_var($shop['logo'], FILTER_VALIDATE_URL))
                return $shop['logo'];
        }

        // Need domain for provider‑based logo.
        if (empty($item['domain']))
        {
            return '';
        }

        $host = self::hostOf($item);
        if ($host === '')
        {
            return '';
        }

        $config = GeneralConfig::getInstance();

        return self::remoteLogoUrlFor(
            $host,
            (string) $config->option('logo_source'),
            (string) $config->option('brandfetch_client_id'),
            (string) $config->option('logodev_key'),
            $missing_404
        );
    }

    /** The host part of an item's domain, or ''. */
    private static function hostOf(array $item): string
    {
        if (empty($item['domain']))
        {
            return '';
        }
        $host = parse_url((string) $item['domain'], PHP_URL_HOST);
        if ($host === false || $host === null)
        {
            $host = preg_replace('#^https?://#', '', (string) $item['domain']);
        }

        return (is_string($host) && self::isHostname($host)) ? $host : '';
    }

    /** "homedepot.com" is a host name; "The Home Depot" (a shop name some modules leave in domain) is not. Pure. */
    public static function isHostname(string $host): bool
    {
        return (bool) preg_match('/^(?:[\p{L}\p{N}](?:[\p{L}\p{N}-]{0,61}[\p{L}\p{N}])?\.)+\p{L}[\p{L}\p{N}-]{1,62}$/iu', $host);
    }

    /** The shop's website icon (Google's favicon service), or '' without a host. Pure. */
    public static function faviconUrlFor(string $host): string
    {
        return $host === '' ? '' : sprintf(self::FAVICON_TEMPLATE, urlencode($host));
    }

    /**
     * The URLs a download tries, in order: the chosen source, then the website
     * icon, so a source that does not work (no key, an unknown shop, an
     * error) still gives the shop a logo. Pure.
     *
     * @return string[]
     */
    public static function downloadCandidates(string $primary, string $fallback): array
    {
        return array_values(array_unique(array_filter(array($primary, $fallback), 'strlen')));
    }

    /**
     * Provider + host -> remote logo URL. Pure: no options, no WordPress.
     *
     * 'clearbit' (stored by older versions, the service no longer serves
     * images), an empty value and anything unknown all resolve to the
     * keyless website icon, and so does a keyed provider without its key
     * (Brandfetch then redirects to its guidelines, Logo.dev answers 401).
     *
     * $missing_404: ask the service for a 404 when it has no logo for the shop
     * instead of its letter placeholder, so a download can fall back to the
     * website icon. Hot-linked logos keep the placeholder.
     */
    public static function remoteLogoUrlFor(string $host, string $provider, string $brandfetch_cid, string $logodev_key, bool $missing_404 = false): string
    {
        $brandfetch_cid = trim($brandfetch_cid);
        $logodev_key = trim($logodev_key);

        if ($provider === 'brandfetch' && $brandfetch_cid !== '')
        {
            return sprintf('https://cdn.brandfetch.io/%s/fallback/%s/h/200/w/200?c=%s', urlencode($host), $missing_404 ? '404' : 'lettermark', urlencode($brandfetch_cid));
        }
        if ($provider === 'logodev' && $logodev_key !== '')
        {
            return sprintf('https://img.logo.dev/%s?token=%s&size=200&format=webp&fallback=%s', urlencode($host), urlencode($logodev_key), $missing_404 ? '404' : 'monogram');
        }

        return self::faviconUrlFor($host);
    }

    /** Return blank placeholder path. */
    private static function getBlankImg()
    {
        return \ContentEgg\PLUGIN_RES . '/img/blank.gif';
    }

    /**
     * Core resolver that enforces precedence list.
     */
    private static function getMerchantImageUrl(
        array $item,
        $prefix,
        $remoteUrl,
        $blankOnError = true,
        $hotlinking = false,
        string $fallbackUrl = ''
    )
    {

        /* ------------------------------------------------------------------
         * 1. Offer‑module specific logo
         * ------------------------------------------------------------------ */
        if (!$prefix)
        {
            if (!empty($item['logo']) && $item['module_id'] === 'Offer')
            {
                return $item['logo'];
            }
        }

        /* ------------------------------------------------------------------
         * 2. Admin custom logo override
         * ------------------------------------------------------------------ */
        if (!$prefix)
        {
            if (isset($item['domain']) && ($custom = self::getCustomLogo($item['domain'])))
            {
                return $custom;
            }
        }

        /* ------------------------------------------------------------------
         * 3. Already‑cached local logo (uploads cache). The plugin ships no
         *    logos of its own: every shop gets one from the same source, so
         *    all logos have the same format.
         * ------------------------------------------------------------------ */

        $fileName = self::buildFileName($item, $prefix);

        if ($fileName !== '')
        {
            // Uploads cache
            $logoDir = self::getMerchantLogoDir();
            if ($logoDir)
            {
                $uploads  = wp_upload_dir();
                $logoPath = $logoDir . DIRECTORY_SEPARATOR . $fileName;
                if (file_exists($logoPath))
                {
                    if (!self::isStaleBlankMarker($logoPath, time()))
                    {
                        return $uploads['baseurl'] . '/' . self::MERCHANT_LOGO_DIR . '/' . $fileName;
                    }
                    // An old failed download: try again (downloads never overwrite).
                    @unlink($logoPath);
                }
            }
        }

        /* ------------------------------------------------------------------
         * 4. CDN hot‑link (if enabled)
         * ------------------------------------------------------------------ */
        if ($hotlinking)
        {
            return $remoteUrl;
        }

        /* ------------------------------------------------------------------
         * 5. Download & cache (hot‑linking disabled)
         * ------------------------------------------------------------------ */
        if ($fileName === '')
        {
            return $blankOnError ? self::getBlankImg() : false;
        }

        $logoDir = $logoDir ?? self::getMerchantLogoDir();
        if (!$logoDir)
        {
            return $blankOnError ? self::getBlankImg() : false;
        }

        $uploads = wp_upload_dir();
        $logoUrl = $uploads['baseurl'] . '/' . self::MERCHANT_LOGO_DIR . '/' . $fileName;

        // Attempt download: the chosen source, then the website icon.
        foreach (self::downloadCandidates((string) $remoteUrl, $fallbackUrl) as $url)
        {
            if (ImageHelper::downloadImg($url, $logoDir, $fileName, '', true))
            {
                if (!$prefix)
                {
                    self::recordDownload($fileName, self::currentSource());
                }

                return $logoUrl;
            }
        }

        // Mark failure to avoid repeated attempts
        @copy(\ContentEgg\PLUGIN_PATH . 'res/img/blank.gif', $logoDir . DIRECTORY_SEPARATOR . $fileName);

        return $blankOnError ? self::getBlankImg() : false;
    }

    /** Generate deterministic filename for caching. */
    private static function buildFileName(array $item, $prefix = '')
    {
        $ext = 'png';
        if (!empty($item['domain']) && self::hostOf($item) !== '')
        {
            return $prefix . str_replace('.', '-', $item['domain']) . '.' . $ext;
        }
        if (!empty($item['logo']))
        {
            return $prefix . md5($item['logo']) . '.' . $ext;
        }
        // A shop name in domain: the name the download saves it under (no spaces).
        if (!empty($item['domain']))
        {
            return $prefix . \sanitize_file_name(str_replace('.', '-', $item['domain'])) . '.' . $ext;
        }
        return '';
    }

    /** Fetch admin‑configured custom logo */
    public static function getCustomLogo($domain)
    {
        if (self::$logos === [])
        {
            $stored = (array) GeneralConfig::getInstance()->option('logos');
            foreach ($stored as $logo)
            {
                if (isset($logo['name'], $logo['value']))
                {
                    self::$logos[$logo['name']] = $logo['value'];
                }
            }
        }
        return self::$logos[$domain] ?? false;
    }

    /** Ensure uploads/ce‑logos directory exists and return its path. */
    public static function getMerchantLogoDir()
    {
        $uploads = wp_upload_dir();
        $dir     = trailingslashit($uploads['basedir']) . self::MERCHANT_LOGO_DIR;
        return is_dir($dir) || wp_mkdir_p($dir) ? $dir : false;
    }

    public static function getMerchantIconUrl(array $item, $blankOnError = false)
    {
        $prefix = 'icon_';

        // Respect module‑level config: show_small_logos
        if (!empty($item['module_id']))
        {
            $parser = ModuleManager::getInstance()->parserFactory($item['module_id']);
            if (
                $parser->getConfigInstance()->option_exists('show_small_logos') &&
                !filter_var($parser->config('show_small_logos'), FILTER_VALIDATE_BOOLEAN)
            )
            {
                return $blankOnError ? self::getBlankImg() : false;
            }
        }

        // Need a domain to build the favicon URL
        if (empty($item['domain']))
        {
            return $blankOnError ? self::getBlankImg() : false;
        }

        // Normalise domain (strip leading protocol)
        $domain = preg_replace('#^https?://#', '', (string) $item['domain']);
        $remoteUrl = sprintf(
            'https://t2.gstatic.com/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&url=https://%s&size=16',
            urlencode($domain)
        );

        return self::getMerchantImageUrl(
            $item,
            $prefix,
            $remoteUrl,
            $blankOnError,
            false
        );
    }

    public static function purgeCachedLogos()
    {
        $dir = self::getMerchantLogoDir();
        if (!$dir)
        {
            return 0;
        }

        $deleted = 0;
        foreach (glob($dir . '/*') ?: [] as $path)
        {
            // Safety: process only regular files within the cache directory
            if (!is_file($path) || strpos(realpath($path), realpath($dir)) !== 0)
            {
                continue;
            }

            if (@unlink($path))
            {
                $deleted++;
            }
        }
        \delete_option(self::DOWNLOADS_OPTION);

        return $deleted;
    }

    /**
     * Delete only the blank markers from the logo cache directory.
     *
     * A failed download leaves a copy of res/img/blank.gif under the logo's
     * file name so it is not retried (see getMerchantImageUrl()). Logos the
     * user put in the same directory by hand must survive, so a file goes
     * only when its content is byte-identical to the blank marker.
     *
     * @return int Number of files deleted.
     */
    public static function purgeBlankLogoMarkers(?string $dir = null, ?string $blank_file = null): int
    {
        if ($dir === null)
        {
            $dir = self::getMerchantLogoDir();
        }
        if (!$dir || !is_dir($dir))
        {
            return 0;
        }

        if ($blank_file === null)
        {
            $blank_file = \ContentEgg\PLUGIN_PATH . 'res/img/blank.gif';
        }
        if (!is_file($blank_file))
        {
            return 0;
        }

        $real_dir = realpath($dir);
        if ($real_dir === false)
        {
            return 0;
        }

        $deleted = 0;
        foreach (glob($dir . '/*') ?: [] as $path)
        {
            // Safety: process only regular files within the cache directory
            $real = realpath($path);
            if (!is_file($path) || $real === false || strpos($real, $real_dir) !== 0)
            {
                continue;
            }

            if (!self::isBlankMarker($path, $blank_file))
            {
                continue;
            }

            if (@unlink($path))
            {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Whether a file is a blank marker: byte-identical to res/img/blank.gif.
     * The size is checked first; only same-size files are hashed.
     */
    public static function isBlankMarker(string $path, ?string $blank_file = null): bool
    {
        if ($blank_file === null)
        {
            $blank_file = \ContentEgg\PLUGIN_PATH . 'res/img/blank.gif';
        }
        if (!is_file($path) || !is_file($blank_file))
        {
            return false;
        }

        return filesize($path) === filesize($blank_file) && md5_file($path) === md5_file($blank_file);
    }

    /** A blank marker older than BLANK_RETRY_DAYS: the logo is tried again. */
    public static function isStaleBlankMarker(string $path, int $now, ?string $blank_file = null): bool
    {
        if (!self::isBlankMarker($path, $blank_file))
        {
            return false;
        }
        $mtime = filemtime($path);

        return $mtime !== false && $mtime < $now - self::BLANK_RETRY_DAYS * 86400;
    }

    /** The Logo Source setting as stored; unset reads as the default, "favicon". */
    public static function currentSource(): string
    {
        $source = (string) GeneralConfig::getInstance()->option('logo_source');

        return $source !== '' ? $source : 'favicon';
    }

    private static function recordDownload(string $file_name, string $source): void
    {
        $downloads = \get_option(self::DOWNLOADS_OPTION, array());
        if (!is_array($downloads))
        {
            $downloads = array();
        }
        if (($downloads[$file_name] ?? null) === $source)
        {
            return;
        }
        $downloads[$file_name] = $source;
        \update_option(self::DOWNLOADS_OPTION, $downloads, false);
    }

    /**
     * The downloaded logos a switch to $source makes outdated: every file
     * recorded from another source. Pure.
     *
     * @return string[] file names
     */
    public static function outdatedDownloads(array $downloads, string $source): array
    {
        $outdated = array();
        foreach ($downloads as $file_name => $from)
        {
            if ((string) $from !== $source)
            {
                $outdated[] = (string) $file_name;
            }
        }

        return $outdated;
    }

    public static function initAction()
    {
        \add_action('update_option_' . GeneralConfig::getInstance()->option_name(), array(__CLASS__, 'onSettingsChange'), 10, 2);
    }

    /**
     * A Logo Source change: the logos downloaded from the old source and the
     * blank markers go, so every shop gets its logo from the new source.
     * Files placed in ce-logos by hand are kept.
     */
    public static function onSettingsChange($old, $new)
    {
        $source_of = function ($values) {
            $source = is_array($values) ? (string) ($values['logo_source'] ?? '') : '';

            return $source !== '' ? $source : 'favicon';
        };
        if ($source_of($old) === $source_of($new))
        {
            return;
        }

        self::refreshDownloadedLogos($source_of($new));
    }

    /** @return int Number of files deleted. */
    public static function refreshDownloadedLogos(string $source, ?string $dir = null, ?string $blank_file = null): int
    {
        if ($dir === null)
        {
            $dir = self::getMerchantLogoDir();
        }
        if (!$dir || !is_dir($dir))
        {
            return 0;
        }

        $downloads = \get_option(self::DOWNLOADS_OPTION, array());
        if (!is_array($downloads))
        {
            $downloads = array();
        }

        $deleted = 0;
        foreach (self::outdatedDownloads($downloads, $source) as $file_name)
        {
            unset($downloads[$file_name]);
            $path = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . basename($file_name);
            if (is_file($path) && @unlink($path))
            {
                $deleted++;
            }
        }
        \update_option(self::DOWNLOADS_OPTION, $downloads, false);

        return $deleted + self::purgeBlankLogoMarkers($dir, $blank_file);
    }
}
