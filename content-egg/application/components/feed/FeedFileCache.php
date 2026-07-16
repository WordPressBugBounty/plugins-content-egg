<?php

namespace ContentEgg\application\components\feed;

defined('\ABSPATH') || exit;

/**
 * FeedFileCache class file
 *
 * Persistent per-module cache of the downloaded (decompressed) feed payload,
 * stored gzipped at rest in the protected cegg-datafeeds directory. Enables
 * re-imports without re-downloading, conditional-GET revalidation, and a
 * sha1 + options-hash delta shortcut that skips DB rebuilds when nothing
 * changed. Metadata lives in a non-autoloaded option.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class FeedFileCache
{
    const META_OPTION_PREFIX = 'cegg_feed_cache_meta_';
    const CACHE_EXT = '.dat.gz';
    const GZIP_LEVEL = 6;
    const CHUNK = 131072;

    private $module_id;
    private $dir;

    public function __construct(string $module_id, string $dir)
    {
        $this->module_id = $module_id;
        $this->dir = rtrim($dir, '/');
    }

    public function filePath(): string
    {
        return $this->dir . '/' . strtolower($this->module_id) . self::CACHE_EXT;
    }

    public function metaOptionName(): string
    {
        return self::META_OPTION_PREFIX . strtolower($this->module_id);
    }

    public function exists(): bool
    {
        return is_file($this->filePath()) && $this->meta() !== array();
    }

    public function meta(): array
    {
        $meta = \get_option($this->metaOptionName(), array());

        return is_array($meta) ? $meta : array();
    }

    public function size(): int
    {
        return $this->exists() ? (int) @filesize($this->filePath()) : 0;
    }

    public function sha1(): string
    {
        $meta = $this->meta();

        return isset($meta['sha1']) ? (string) $meta['sha1'] : '';
    }

    /** The cache is bound to the URL it was downloaded from. */
    public function matchesUrl(string $feed_url): bool
    {
        $meta = $this->meta();

        return isset($meta['url_hash']) && $meta['url_hash'] === md5($feed_url);
    }

    public function isFresh(string $feed_url, int $window): bool
    {
        if (!$this->exists() || !$this->matchesUrl($feed_url))
        {
            return false;
        }

        $meta = $this->meta();
        $age = time() - (int) (isset($meta['downloaded_at']) ? $meta['downloaded_at'] : 0);

        return $age >= 0 && $age < $window;
    }

    /**
     * Conditional HEAD revalidation. Returns true only when the server
     * answers 304 Not Modified (the cache timestamp is refreshed); any other
     * outcome means the caller should download.
     */
    public function revalidate(string $feed_url): bool
    {
        if (!$this->exists() || !$this->matchesUrl($feed_url))
        {
            return false;
        }

        $scheme = strtolower((string) parse_url($feed_url, PHP_URL_SCHEME));
        if ($scheme !== 'http' && $scheme !== 'https')
        {
            return false;
        }

        $meta = $this->meta();
        $headers = array();

        if (!empty($meta['etag']))
        {
            $headers['If-None-Match'] = $meta['etag'];
        }
        if (!empty($meta['last_modified']))
        {
            $headers['If-Modified-Since'] = $meta['last_modified'];
        }

        if (!$headers)
        {
            return false;
        }

        $response = \wp_remote_head($feed_url, array(
            'timeout' => 15,
            'redirection' => 3,
            'headers' => $headers,
        ));

        if (\is_wp_error($response) || (int) \wp_remote_retrieve_response_code($response) !== 304)
        {
            return false;
        }

        $meta['downloaded_at'] = time();
        $this->saveMeta($meta);

        return true;
    }

    /**
     * Move a downloaded, already-decompressed feed payload into the cache
     * (gzipped, atomic rename). Returns false when the payload exceeds the
     * cegg_feed_cache_max_bytes cap or on IO failure — the caller should then
     * process the source file directly. The source file is left in place.
     */
    public function store(string $raw_file, string $feed_url): bool
    {
        if (!is_readable($raw_file))
        {
            return false;
        }

        $raw_size = (int) @filesize($raw_file);
        $max = (int) \apply_filters('cegg_feed_cache_max_bytes', 0, $this->module_id);

        if ($max > 0 && $raw_size > $max)
        {
            $this->delete();

            return false;
        }

        $tmp = $this->filePath() . '.tmp';

        $in = @fopen($raw_file, 'rb');
        if (!$in)
        {
            return false;
        }

        $out = @gzopen($tmp, 'wb' . self::GZIP_LEVEL);
        if (!$out)
        {
            fclose($in);

            return false;
        }

        $ctx = hash_init('sha1');

        while (!feof($in))
        {
            $buf = fread($in, self::CHUNK);
            if ($buf === false)
            {
                fclose($in);
                gzclose($out);
                @unlink($tmp);

                return false;
            }
            if ($buf === '')
            {
                continue;
            }

            hash_update($ctx, $buf);

            if (gzwrite($out, $buf) === false)
            {
                fclose($in);
                gzclose($out);
                @unlink($tmp);

                return false;
            }
        }

        fclose($in);
        gzclose($out);

        if (!@rename($tmp, $this->filePath()))
        {
            @unlink($tmp);

            return false;
        }

        $old = $this->meta();

        $meta = array(
            'url_hash' => md5($feed_url),
            'etag' => '',
            'last_modified' => '',
            'downloaded_at' => time(),
            'size' => (int) @filesize($this->filePath()),
            'raw_size' => $raw_size,
            'sha1' => hash_final($ctx),
            'last_import_sha1' => isset($old['last_import_sha1']) ? $old['last_import_sha1'] : '',
            'last_import_options_hash' => isset($old['last_import_options_hash']) ? $old['last_import_options_hash'] : '',
        );

        // Capture validators for later conditional revalidation.
        $scheme = strtolower((string) parse_url($feed_url, PHP_URL_SCHEME));
        if ($scheme === 'http' || $scheme === 'https')
        {
            $response = \wp_remote_head($feed_url, array('timeout' => 15, 'redirection' => 3));
            if (!\is_wp_error($response))
            {
                $meta['etag'] = (string) \wp_remote_retrieve_header($response, 'etag');
                $meta['last_modified'] = (string) \wp_remote_retrieve_header($response, 'last-modified');
            }
        }

        $this->saveMeta($meta);

        return true;
    }

    /**
     * Gunzip the cache into a temporary working file for stream processing.
     * The caller is responsible for deleting the returned file.
     */
    public function createWorkingCopy(): string
    {
        if (!$this->exists())
        {
            throw new \Exception('Cached feed file does not exist.');
        }

        $working = $this->dir . '/' . strtolower($this->module_id) . '.work.' . getmypid() . '.tmp';

        $in = @gzopen($this->filePath(), 'rb');
        if (!$in)
        {
            throw new \Exception('Unable to open the cached feed file.');
        }

        $out = @fopen($working, 'wb');
        if (!$out)
        {
            gzclose($in);
            throw new \Exception('Unable to create a working copy of the cached feed.');
        }

        while (!gzeof($in))
        {
            $buf = gzread($in, self::CHUNK);
            if ($buf === false || fwrite($out, $buf) === false)
            {
                gzclose($in);
                fclose($out);
                @unlink($working);
                throw new \Exception('Failed to unpack the cached feed file.');
            }
        }

        gzclose($in);
        fclose($out);

        return $working;
    }

    /** First $bytes of the decompressed payload (used by previews). */
    public function readHead(int $bytes): string
    {
        if (!$this->exists())
        {
            return '';
        }

        $in = @gzopen($this->filePath(), 'rb');
        if (!$in)
        {
            return '';
        }

        $data = gzread($in, $bytes);
        gzclose($in);

        return $data === false ? '' : $data;
    }

    /**
     * Record that the current cache content was imported with the given
     * module options. No-op when the cache is empty (e.g. declined by cap).
     */
    public function setLastImport(string $options_hash): void
    {
        $meta = $this->meta();
        if (!$meta)
        {
            return;
        }

        $meta['last_import_sha1'] = isset($meta['sha1']) ? $meta['sha1'] : '';
        $meta['last_import_options_hash'] = $options_hash;
        $this->saveMeta($meta);
    }

    public function isUnchangedSinceLastImport(string $options_hash): bool
    {
        $meta = $this->meta();

        return !empty($meta['sha1'])
            && isset($meta['last_import_sha1'], $meta['last_import_options_hash'])
            && $meta['last_import_sha1'] === $meta['sha1']
            && $meta['last_import_options_hash'] === $options_hash;
    }

    public function delete(): void
    {
        @unlink($this->filePath());
        @unlink($this->filePath() . '.tmp');
        \delete_option($this->metaOptionName());
    }

    private function saveMeta(array $meta): void
    {
        // autoload=false: only read around feed imports.
        if (false === \add_option($this->metaOptionName(), $meta, '', false))
        {
            \update_option($this->metaOptionName(), $meta, false);
        }
    }
}
