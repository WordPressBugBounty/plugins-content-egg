<?php

namespace ContentEgg\application\admin\import;

defined('\ABSPATH') || exit;

/**
 * PresetNormalizer
 *
 * Pure preset transformations: legacy migration, name sanitization,
 * placeholder rewriting and reference scanning.
 *
 * Deliberately free of WordPress calls (except sanitize_textarea_field) so the
 * logic can be unit-tested outside WordPress. Never writes to the database.
 *
 * @author keywordrush.com <support@keywordrush.com>
 * @link https://www.keywordrush.com
 * @copyright Copyright &copy; 2026 keywordrush.com
 */
class PresetNormalizer
{
    public const FORMAT_TEXT = 'text';
    public const FORMAT_HTML = 'html';

    /** Built-in %AI.*% keys that a custom prompt must never shadow. */
    public const RESERVED_NAMES = [
        'title',
        'content',
        'short_desc',
        'extra_section1',
        'extra_section2',
    ];

    /** Preset keys that hold a selected generation method. */
    public const SINK_KEYS = ['ai_title', 'ai_content', 'ai_short_desc'];

    /** Plain-string preset fields that placeholders are resolved in. */
    public const TEMPLATE_FIELDS = ['title_tpl', 'body_tpl', 'woo_short_desc_tpl', 'tags'];

    /**
     * Reduces arbitrary user input to a safe placeholder name.
     */
    public static function sanitizeName(string $raw): string
    {
        $name = strtolower(trim($raw));
        $name = preg_replace('/[^a-z0-9_]+/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '_');

        if ($name !== '' && preg_match('/^[0-9]/', $name))
        {
            $name = 'p_' . $name;
        }

        return $name;
    }

    /**
     * Suffixes the name until it collides with neither $taken nor a reserved name.
     */
    public static function uniqueName(string $name, array $taken): string
    {
        $candidate = $name;
        $n = 2;

        while (in_array($candidate, $taken, true) || in_array($candidate, self::RESERVED_NAMES, true))
        {
            $candidate = $name . '_' . $n;
            $n++;
        }

        return $candidate;
    }

    /**
     * Rewrites %AI.<old>% tokens in one string.
     *
     * Single-pass, so two names swapping places resolve correctly.
     *
     * @param array $map lowercase old name => new name
     */
    public static function rewritePlaceholders(string $text, array $map): string
    {
        if ($text === '' || !$map)
        {
            return $text;
        }

        return preg_replace_callback(
            '/%AI\.([A-Za-z0-9_]+)%/',
            static function (array $m) use ($map): string
            {
                $key = strtolower($m[1]);

                return isset($map[$key]) ? '%AI.' . $map[$key] . '%' : $m[0];
            },
            $text
        );
    }

    /**
     * Applies rewritePlaceholders() to every preset field that resolves placeholders.
     *
     * @param array $map lowercase old name => new name
     */
    public static function rewritePresetPlaceholders(array $preset, array $map): array
    {
        if (!$map)
        {
            return $preset;
        }

        foreach (self::TEMPLATE_FIELDS as $field)
        {
            if (isset($preset[$field]) && is_string($preset[$field]))
            {
                $preset[$field] = self::rewritePlaceholders($preset[$field], $map);
            }
        }

        if (!empty($preset['custom_fields']) && is_array($preset['custom_fields']))
        {
            foreach ($preset['custom_fields'] as $i => $cf)
            {
                if (is_array($cf) && isset($cf['value']) && is_string($cf['value']))
                {
                    $preset['custom_fields'][$i]['value'] = self::rewritePlaceholders($cf['value'], $map);
                }
            }
        }

        return $preset;
    }

    /**
     * Converts a legacy preset (prompt1..prompt5 + extra sections) to the
     * ai_prompts shape. Idempotent, and never writes to the database — the
     * result is persisted only when the preset is next saved.
     */
    public static function normalize(array $preset): array
    {
        if (isset($preset['ai_prompts']) && is_array($preset['ai_prompts']))
        {
            return self::stripLegacyKeys($preset);
        }

        // Which legacy prompts were wired to an extra section, and under which
        // placeholder. Extra sections ran prepareMarkdown(), hence format=html.
        $extraRefs = [];
        foreach (['ai_extra_section1' => 'extra_section1', 'ai_extra_section2' => 'extra_section2'] as $sinkKey => $placeholder)
        {
            if (!empty($preset[$sinkKey]))
            {
                $extraRefs[$preset[$sinkKey]][] = $placeholder;
            }
        }

        $rows = [];
        $map  = [];

        for ($n = 1; $n <= 5; $n++)
        {
            $key = 'prompt' . $n;

            if (empty($preset[$key]))
            {
                continue;
            }

            $rows[] = [
                'name'   => $key,
                'prompt' => (string) $preset[$key],
                'format' => isset($extraRefs[$key]) ? self::FORMAT_HTML : self::FORMAT_TEXT,
            ];

            foreach ($extraRefs[$key] ?? [] as $placeholder)
            {
                $map[$placeholder] = $key;
            }
        }

        $preset['ai_prompts'] = $rows;
        $preset = self::rewritePresetPlaceholders($preset, $map);

        return self::stripLegacyKeys($preset);
    }

    /**
     * Names of prompts the preset actually uses: selected in a sink, or
     * referenced as %AI.<name>% in any field where placeholders resolve.
     *
     * A prompt that is defined but never referenced is never generated.
     *
     * @return string[] canonical prompt names
     */
    public static function collectReferencedNames(array $preset): array
    {
        $known = [];
        foreach ($preset['ai_prompts'] ?? [] as $row)
        {
            if (is_array($row) && !empty($row['name']))
            {
                $known[strtolower($row['name'])] = $row['name'];
            }
        }

        if (!$known)
        {
            return [];
        }

        $referenced = [];

        foreach (self::SINK_KEYS as $sink)
        {
            $value = strtolower((string) ($preset[$sink] ?? ''));

            if ($value !== '' && isset($known[$value]))
            {
                $referenced[$known[$value]] = true;
            }
        }

        $texts = [];

        foreach (self::TEMPLATE_FIELDS as $field)
        {
            if (isset($preset[$field]) && is_string($preset[$field]))
            {
                $texts[] = $preset[$field];
            }
        }

        foreach ($preset['custom_fields'] ?? [] as $cf)
        {
            if (is_array($cf) && isset($cf['value']) && is_string($cf['value']))
            {
                $texts[] = $cf['value'];
            }
        }

        foreach ($texts as $text)
        {
            if (!preg_match_all('/%AI\.([A-Za-z0-9_]+)%/', $text, $matches))
            {
                continue;
            }

            foreach ($matches[1] as $token)
            {
                $key = strtolower($token);

                if (isset($known[$key]))
                {
                    $referenced[$known[$key]] = true;
                }
            }
        }

        return array_keys($referenced);
    }

    /**
     * Sanitises submitted repeater rows into storable prompt rows.
     *
     * Names are never refused — duplicates and reserved names are suffixed, and
     * every correction is reported in $result['notices'] so nothing is silent.
     *
     * @param array $rawRows  rows of ['name', 'original_name', 'prompt', 'format']
     * @param array $takenNames names already spoken for — pass the built-in
     *                          generator method keys so a prompt cannot shadow
     *                          one and silently replace it in the dropdowns
     * @return array{prompts: array, renames: array, notices: array}
     */
    public static function sanitizePrompts(array $rawRows, array $takenNames = []): array
    {
        $prompts = [];
        $renames = [];
        $notices = [];
        $taken   = array_values($takenNames);
        $index   = 0;

        foreach ($rawRows as $row)
        {
            if (!is_array($row))
            {
                continue;
            }

            $prompt = \sanitize_textarea_field((string) ($row['prompt'] ?? ''));

            if (trim($prompt) === '')
            {
                continue;
            }

            $index++;

            $name = self::sanitizeName((string) ($row['name'] ?? ''));

            if ($name === '')
            {
                $name = 'prompt_' . $index;
            }

            $unique = self::uniqueName($name, $taken);

            if ($unique !== $name)
            {
                $notices[] = sprintf(
                    /* translators: 1: submitted prompt name, 2: name actually stored */
                    \__('Custom prompt name "%1$s" was already in use and was saved as "%2$s".', 'content-egg'),
                    $name,
                    $unique
                );
            }

            $taken[] = $unique;

            $original = self::sanitizeName((string) ($row['original_name'] ?? ''));

            if ($original !== '' && $original !== $unique)
            {
                $renames[$original] = $unique;
            }

            $format = (($row['format'] ?? self::FORMAT_TEXT) === self::FORMAT_HTML)
                ? self::FORMAT_HTML
                : self::FORMAT_TEXT;

            $prompts[] = [
                'name'   => $unique,
                'prompt' => $prompt,
                'format' => $format,
            ];
        }

        return [
            'prompts' => $prompts,
            'renames' => $renames,
            'notices' => $notices,
        ];
    }

    /**
     * Propagates prompt renames across sink selections and every field where
     * placeholders resolve, so a rename never dangles.
     *
     * @param array $renames old name => new name
     */
    public static function applyRenames(array $preset, array $renames): array
    {
        if (!$renames)
        {
            return $preset;
        }

        $map = [];
        foreach ($renames as $old => $new)
        {
            $map[strtolower((string) $old)] = $new;
        }

        foreach (self::SINK_KEYS as $sink)
        {
            $value = strtolower((string) ($preset[$sink] ?? ''));

            if ($value !== '' && isset($map[$value]))
            {
                $preset[$sink] = $map[$value];
            }
        }

        return self::rewritePresetPlaceholders($preset, $map);
    }

    /**
     * Clears sink selections that point at neither a current custom prompt nor
     * a built-in method — which is what happens when the prompt a sink referenced
     * is deleted. Left in place, the sink generates nothing and the post silently
     * gets an empty title or body.
     *
     * @param array $builtInKeysBySink sink key => list of valid built-in method keys
     */
    public static function pruneDanglingSinks(array $preset, array $builtInKeysBySink): array
    {
        $names = [];
        foreach ($preset['ai_prompts'] ?? [] as $row)
        {
            if (is_array($row) && !empty($row['name']))
            {
                $names[] = $row['name'];
            }
        }

        foreach (self::SINK_KEYS as $sink)
        {
            $value = (string) ($preset[$sink] ?? '');

            if ($value === '' || in_array($value, $names, true))
            {
                continue;
            }

            if (in_array($value, $builtInKeysBySink[$sink] ?? [], true))
            {
                continue;
            }

            $preset[$sink] = '';
        }

        return $preset;
    }

    private static function stripLegacyKeys(array $preset): array
    {
        for ($n = 1; $n <= 5; $n++)
        {
            unset($preset['prompt' . $n]);
        }

        unset($preset['ai_extra_section1'], $preset['ai_extra_section2']);

        return $preset;
    }
}
