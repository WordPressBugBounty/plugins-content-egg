<?php

namespace ContentEgg\application\EggBlocks\shared;

defined('ABSPATH') || exit;

class EggbTocCollector
{
    public static function collect(array $blocks): array
    {
        $items = [];
        self::walk($blocks, $items);
        return $items;
    }

    private static function walk(array $blocks, array &$items): void
    {
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $name = (string) ($block['blockName'] ?? '');
            if ($name === '') {
                if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                    self::walk($block['innerBlocks'], $items);
                }
                continue;
            }

            if ($name !== 'eggb/toc' && EggbTocRegistry::isSupportedBlock($name)) {
                $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
                if (!empty($attrs['include_in_toc'])) {
                    $anchor = EggbTocSupport::normalizeAnchor((string) ($attrs['anchor'] ?? ''));
                    $label = EggbTocSupport::normalizeLabel((string) ($attrs['toc_label'] ?? ''));
                    if ($label === '') {
                        $headingAttr = EggbTocRegistry::headingAttr($name);
                        if ($headingAttr !== null) {
                            $label = EggbTocSupport::normalizeLabel((string) ($attrs[$headingAttr] ?? ''));
                        }
                    }
                    if ($label === '') {
                        $labelAttr = EggbTocRegistry::labelAttr($name);
                        if ($labelAttr !== null) {
                            $label = EggbTocSupport::normalizeLabel((string) ($attrs[$labelAttr] ?? ''));
                        }
                    }
                    $level = EggbTocSupport::normalizeLevel(
                        $attrs['level'] ?? null,
                        EggbTocRegistry::defaultLevel($name)
                    );

                    if ($anchor !== '' && $label !== '') {
                        $items[] = [
                            'anchor' => $anchor,
                            'label' => $label,
                            'level' => $level,
                        ];
                    }
                }
            }

            if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                self::walk($block['innerBlocks'], $items);
            }
        }
    }
}
