<?php

namespace ContentEgg\application\EggBlocks\blocks\specifications\variants;

defined('ABSPATH') || exit;

class CompactVariant
{
    public static function render(array $specs, array $specs_data, string $theme_class = '', string $data_theme = ''): void
    {
?>
        <div class="eggb-block eggb-specs eggb-specs--compact<?php echo $theme_class ? ' ' . esc_attr($theme_class) : ''; ?>" <?php echo $data_theme; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                                                                                                                ?>>
            <?php if ($specs_data['section_label'] !== '' || $specs_data['title'] !== ''): ?>
                <div class="d-flex flex-column gap-1 mb-3">
                    <?php if ($specs_data['section_label'] !== ''): ?>
                        <span class="eggb-section-title eggb-section-title--muted mb-0"><?php echo esc_html($specs_data['section_label']); ?></span>
                    <?php endif; ?>
                    <?php if ($specs_data['title'] !== ''):
                        $ht = $specs_data['heading_tag']; ?>
                        <<?php echo esc_attr($ht); ?> class="eggb-block-title eggb-block-title--sm eggb-sp-heading"><?php echo esc_html($specs_data['title']); ?></<?php echo esc_attr($ht); ?>>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <table class="">
                <tbody>
                    <?php foreach ($specs as $spec): ?>
                        <tr>
                            <td class="eggb-ks-label"><?php echo esc_html($spec['label']); ?></td>
                            <td><?php echo esc_html($spec['value']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
<?php
    }
}
