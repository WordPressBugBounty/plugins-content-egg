<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

/**
 * SecretMasker class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Masks credential-shaped values in settings arrays before they are exposed
 * to agents or written to the ability audit log. Reads are masked; writes
 * accept full values (see design spec §7).
 */
final class SecretMasker
{
    const KEY_PATTERN = '/(secret|password|passwd|token|api_?key|access_key|client_id|client_secret|private_key|credential|passphrase|signature|license_key|cert|(^|_)key$)/i';
    const MASK = '••••';

    public static function isSecretKey(string $key): bool
    {
        return (bool) preg_match(self::KEY_PATTERN, $key);
    }

    /**
     * @param mixed $value
     */
    public static function maskValue($value): string
    {
        $value = (string) $value;

        if ($value === '')
        {
            return '';
        }

        if (mb_strlen($value) > 8)
        {
            return self::MASK . mb_substr($value, -4);
        }

        return self::MASK;
    }

    /**
     * Returns a copy of $values with secret-keyed entries masked. When a
     * secret key holds an array, every scalar underneath it is masked.
     */
    public static function maskArray(array $values, bool $force_all = false): array
    {
        $out = array();
        foreach ($values as $key => $value)
        {
            $secret = $force_all || (is_string($key) && self::isSecretKey($key));

            if (is_array($value))
            {
                $out[$key] = self::maskArray($value, $secret);
            }
            elseif ($secret && is_scalar($value))
            {
                $out[$key] = self::maskValue($value);
            }
            else
            {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
