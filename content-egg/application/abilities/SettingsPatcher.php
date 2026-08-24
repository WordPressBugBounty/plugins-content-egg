<?php

namespace ContentEgg\application\abilities;

defined('\ABSPATH') || exit;

use ContentEgg\application\components\Config;
use ContentEgg\application\components\ShopMigration;

/**
 * SettingsPatcher class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */

/**
 * Guard-railed partial settings writes for agent abilities. Bypasses the
 * Settings-API validate() pipeline (admin-context only) and compensates with:
 * option-key allowlisting, protected-key rejection, masked-placeholder
 * rejection (agents must never write back a masked read), choice-list
 * enforcement, and wp_kses_post on strings.
 */
final class SettingsPatcher
{
    /**
     * @return array{applied: string[], values: array}
     */
    /**
     * Folds the legacy shop options into ShopStore when - and only when - the
     * row about to be rewritten still carries them.
     */
    private static function maybeMigrateShops($option_name)
    {
        $existing = \get_option($option_name);

        if (!is_array($existing))
            return;

        if (!isset($existing['merchants']) && !isset($existing['merchant_names']))
            return;

        ShopMigration::maybeRun();
    }

    public static function apply(Config $config, array $patch, array $rejected_keys = array()): array
    {
        if (!$patch)
        {
            throw new AbilityInputException("'settings' must be a non-empty object of option => value.");
        }

        $meta = (array) $config->getOptionsMeta();
        // getOptionValues() returns only DEFINED options and the result replaces
        // the whole row, so this path drops merchant_names/merchants exactly the
        // way Config::validate() does - and REST never fires admin_init, where
        // the upgrade ladder lives. Fold them into ShopStore first, or an agent
        // editing one unrelated setting destroys every shop.
        //
        // Gated on the row actually holding them: a module config has no such
        // keys, so this neither runs nor drags GeneralConfig in for those.
        self::maybeMigrateShops($config->option_name());

        $values = (array) $config->getOptionValues();
        $applied = array();

        foreach ($patch as $key => $value)
        {
            $key = (string) $key;

            if (in_array($key, $rejected_keys, true))
            {
                throw new AbilityInputException("Option '{$key}' cannot be changed through this ability.");
            }

            if (!array_key_exists($key, $meta))
            {
                $known = array_keys($meta);
                throw new AbilityInputException(
                    "Unknown option '{$key}'. Valid keys: " . implode(', ', array_slice($known, 0, 40))
                        . (count($known) > 40 ? ', …' : '') . '.'
                );
            }

            // Reject masked placeholders whether the value is a plain string or
            // nested inside an array (multi-value settings).
            foreach (is_array($value) ? $value : array($value) as $masked_candidate)
            {
                if (is_string($masked_candidate) && strpos($masked_candidate, SecretMasker::MASK) === 0)
                {
                    throw new AbilityInputException(
                        "Option '{$key}' looks like a masked placeholder value. Send the real full value; "
                            . 'masked reads can never be written back.'
                    );
                }
            }

            if (isset($meta[$key]['choices']) && is_array($meta[$key]['choices']))
            {
                $choice_keys = array_map('strval', array_keys($meta[$key]['choices']));
                if ($value === null)
                {
                    throw new AbilityInputException(
                        "Option '{$key}' cannot be null; it must be one of: " . implode(', ', $choice_keys) . '.'
                    );
                }
                $to_check = is_array($value) ? $value : array($value);
                foreach ($to_check as $candidate)
                {
                    if (!is_scalar($candidate) || !in_array((string) $candidate, $choice_keys, true))
                    {
                        throw new AbilityInputException(
                            "Option '{$key}' must be one of: " . implode(', ', $choice_keys) . '.'
                        );
                    }
                }
            }

            if (!is_scalar($value) && !is_array($value) && $value !== null)
            {
                throw new AbilityInputException("Option '{$key}' has an unsupported value type.");
            }

            if (is_string($value))
            {
                $value = (string) \wp_check_invalid_utf8($value);
                // WP core convention: users with unfiltered_html store raw
                // values (kses entity-encodes '&' and strips tags, which
                // corrupts URL-bearing settings); others get the standard
                // kses posture.
                if (!\current_user_can('unfiltered_html'))
                {
                    $value = \wp_kses_post($value);
                }
            }

            $values[$key] = $value;
            $applied[] = $key;
        }

        // Field-level validation parity with the admin save pipeline: reject
        // out-of-range / too-long / required-empty values that Config::validate()
        // would refuse. The guards above only allowlist keys, reject masked/
        // protected values and enforce choice lists; the per-field validators
        // (required, less_than_equal_to, exact_length, …) live in validate() and
        // are shared through validatePatchValues(). Run on the raw patch so the
        // validators see the real submitted values.
        $validation = $config->validatePatchValues($patch);
        if (!empty($validation['errors']))
        {
            $parts = array();
            foreach ($validation['errors'] as $error_key => $message)
            {
                $parts[] = $error_key . ': ' . $message;
            }
            throw new AbilityInputException(
                'Some values were rejected by validation — ' . implode('; ', $parts)
                    . '. Fix them and resend.'
            );
        }

        \update_option($config->option_name(), $values);

        // Keep the Config instance in sync with the DB write so same-request
        // readers (including maskedValues() for the response) see the new
        // values instead of the construction-time snapshot.
        foreach ($applied as $key)
        {
            $config->setOptionValue($key, $values[$key]);
        }

        return array('applied' => $applied, 'values' => $values);
    }

    /**
     * Dual-signal masked settings view (KEY_PATTERN + render_password flag),
     * shared by the settings read and write abilities.
     *
     * @return array{settings: array, options: array}
     */
    public static function maskedValues(Config $config): array
    {
        // getOptionValues() returns only DEFINED options and the result replaces
        // the whole row, so this path drops merchant_names/merchants exactly the
        // way Config::validate() does - and REST never fires admin_init, where
        // the upgrade ladder lives. Fold them into ShopStore first, or an agent
        // editing one unrelated setting destroys every shop.
        //
        // Gated on the row actually holding them: a module config has no such
        // keys, so this neither runs nor drags GeneralConfig in for those.
        self::maybeMigrateShops($config->option_name());

        $values = (array) $config->getOptionValues();

        $options = array();
        foreach ((array) $config->getOptionsMeta() as $key => $meta)
        {
            $is_password = !empty($meta['is_password']);
            unset($meta['is_password']);

            $meta['secret'] = $is_password || SecretMasker::isSecretKey((string) $key);

            if ($is_password && !SecretMasker::isSecretKey((string) $key) && isset($values[$key]) && is_scalar($values[$key]))
            {
                $values[$key] = SecretMasker::maskValue($values[$key]);
            }

            $options[$key] = $meta;
        }

        return array(
            'settings' => SecretMasker::maskArray($values),
            'options' => $options,
        );
    }
}
