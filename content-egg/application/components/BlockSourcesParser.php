<?php

namespace ContentEgg\application\components;

defined('\ABSPATH') || defined('ContentEgg\application\components\TESTING') || exit;

/**
 * BlockSourcesParser class file
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class BlockSourcesParser
{
    const SOURCE_SEPARATOR = ';';
    const OPTION_SEPARATOR = '|';
    const KEY_VALUE_SEPARATOR = ':';

    private static function getDefaults()
    {
        return array(
            'group' => '',
            'limit' => 1,
            'badge' => '',
        );
    }

    public static function parse($raw)
    {
        $raw = (string) $raw;
        if (trim($raw) === '')
            return array();

        $sources = array();

        foreach (explode(self::SOURCE_SEPARATOR, $raw) as $source_def)
        {
            $source_def = trim($source_def);
            if ($source_def === '')
                continue;

            $parts = array_map('trim', explode(self::OPTION_SEPARATOR, $source_def));
            $post_id = (int) array_shift($parts);
            if ($post_id <= 0)
                continue;

            $source = self::getDefaults();
            $source['post_id'] = $post_id;

            foreach ($parts as $part)
            {
                if ($part === '')
                    continue;

                $pair = array_pad(explode(self::KEY_VALUE_SEPARATOR, $part, 2), 2, '');
                $key = \sanitize_key(trim($pair[0]));
                $value = trim($pair[1]);

                if ($key === '')
                    continue;

                $source[$key] = $value;
            }

            $source['limit'] = max(0, (int) $source['limit']);
            $source['group'] = \sanitize_text_field($source['group']);
            $source['badge'] = \sanitize_text_field($source['badge']);

            $sources[] = $source;
        }

        return $sources;
    }
}
