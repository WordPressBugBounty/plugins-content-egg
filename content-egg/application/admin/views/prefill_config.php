<?php

use ContentEgg\application\admin\ProductPrefillController;
use ContentEgg\application\helpers\AdminHelper;

use function ContentEgg\prn;

defined('ABSPATH') || exit;

$settings = is_array($settings ?? null) ? $settings : [];
$def = static function ($key, $fallback = '') use ($settings)
{
    return $settings[$key] ?? $fallback;
};

// -------------------------------------------------------------------------
// Rebuild defaults from settings
// -------------------------------------------------------------------------

$selected_modules            = (array) $def('modules', []);
$keyword_source              = $def('keyword_source', '');
$existing_module_behavior    = $def('existing_module_behavior', 'skip_module');
$ai_relevance_check          = (bool) $def('ai_relevance_check', false);
$max_products_per_module     = (int)  $def('max_products_per_module', 0);
$max_products_total          = (int)  $def('max_products_total', 0);
$product_group               = $def('product_group', '');
$meta_field_name             = $def('meta_field_name', '');
$source_module_title         = $def('source_module_title', '');
$source_module_gtin          = $def('source_module_gtin', '');
$shortcode_blocks            = $def('shortcode_blocks', []); // expects [ i => [ 'position' => '', 'code' => '' ] ]
$custom_fields               = $def('custom_fields', []);    // expects [ i => [ 'key' => '', 'value' => '' ] ]

// fallback for keyword‑source when no settings stored yet
if (! $keyword_source)
{
    $keyword_source = ($is_pro && empty($ai_warning)) ? 'fully_automatic_ai' : 'post_title';
}

$back_url = admin_url('admin.php?page=' . ProductPrefillController::slug);

// -------------------------------------------------------------------------
// AI warning message (same logic as original)
// -------------------------------------------------------------------------
$ai_warning = AdminHelper::getSysAiWarning();

// -------------------------------------------------------------------------
// BEGIN OUTPUT
// -------------------------------------------------------------------------
?>

<div class="wrap cegg5-container">
    <h1 class="h3"><?php echo esc_html__('Product Prefill Tool', 'content-egg'); ?></h1>

    <?php if (! empty($selected_posts)) : ?>
        <div class="alert alert-warning mt-4" role="alert">
            <strong><?php esc_html_e('Important:', 'content-egg'); ?></strong>
            <?php esc_html_e('Prefill can modify your posts and changes cannot be undone.', 'content-egg'); ?>
            <?php esc_html_e('Please back up your database before proceeding.', 'content-egg'); ?>
            <a href="https://developer.wordpress.org/advanced-administration/security/backup/#database-backup-instructions" target="_blank" rel="noopener noreferrer" class="alert-link">
                <?php esc_html_e('Learn how to back up your database', 'content-egg'); ?>
            </a>
        </div>
    <?php endif; ?>

    <h2 class="h5">
        <?php printf(esc_html__('Total Selected Posts: %d', 'content-egg'), (int) $total_posts); ?>
    </h2>

    <?php if (! empty($selected_posts)) : ?>
        <button type="button" class="button button-secondary mb-3" id="togglePosts"><?php esc_html_e('Show Selected Posts', 'content-egg'); ?></button>

        <div id="selectedPosts" style="display:none; margin-top:1em; max-height:300px; overflow-y:auto; border:1px solid #ddd; padding:1em;">
            <ul style="list-style:disc; margin-left:2em;">
                <?php foreach ($selected_posts as $post) : ?>
                    <li><a href="<?php echo esc_url(get_permalink($post->ID)); ?>" target="_blank" rel="noopener"><?php echo esc_html($post->post_title); ?></a></li>
                <?php endforeach; ?>
                <?php if ($total_posts > ProductPrefillController::PREVIEW_POST_LIMIT) : ?>
                    <p><?php echo esc_html(sprintf(__('Note: Only the first %s posts are displayed.', 'content-egg'), ProductPrefillController::PREVIEW_POST_LIMIT)); ?></p>
                <?php endif; ?>
            </ul>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const btn = document.getElementById('togglePosts');
                const list = document.getElementById('selectedPosts');
                let visible = false;
                btn.addEventListener('click', () => {
                    visible = !visible;
                    list.style.display = visible ? 'block' : 'none';
                    btn.innerText = visible ? '<?php echo esc_js(__('Hide Selected Posts', 'content-egg')); ?>' : '<?php echo esc_js(__('Show Selected Posts', 'content-egg')); ?>';
                });
            });
        </script>
    <?php else : ?>
        <div class="notice notice-warning">
            <p><?php esc_html_e('No posts found for the selected criteria.', 'content-egg'); ?></p>
        </div>
        <p><a href="<?php echo esc_url($back_url); ?>" class="button button-secondary"><?php esc_html_e('Back', 'content-egg'); ?></a></p>
    <?php endif; ?>
</div>

<?php if (empty($selected_posts))
{
    return;
} ?>

<?php $modules = ContentEgg\application\components\ModuleManager::getInstance()->getAffiliateParsersList(true, true); ?>

<div class="wrap cegg5-container">
    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=content-egg-product-prefill&action=prefill_start&noheader=true')); ?>">
        <?php wp_nonce_field('prefill_start_nonce'); ?>
        <input type="hidden" name="page" value="content-egg-product-prefill">
        <input type="hidden" name="action" value="prefill_start">
        <input type="hidden" name="prefill_transient" value="<?php echo esc_attr($prefill_transient_key); ?>">

        <table class="form-table">
            <!-- MODULE CHECKBOXES -->
            <tr>
                <th scope="row"><label><?php esc_html_e('Modules for Product Prefill', 'content-egg'); ?></label></th>
                <td>
                    <div class="container-fluid p-0 m-0">
                        <div class="row">
                            <?php foreach ($modules as $module_id => $module_name) : ?>
                                <div class="col-md-4 mb-1">
                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="modules[]"
                                            value="<?php echo esc_attr($module_id); ?>"
                                            id="module_<?php echo esc_attr($module_id); ?>"
                                            <?php checked(in_array($module_id, $selected_modules, true)); ?>>
                                        <label class="form-check-label" for="module_<?php echo esc_attr($module_id); ?>">
                                            <?php echo esc_html($module_name); ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="small text-muted mt-1"><?php esc_html_e('Modules will be processed based on their priority, which can be set in each module’s settings.', 'content-egg'); ?></div>
                    </div>
                </td>
            </tr>

            <?php
            $modules_full     = ContentEgg\application\components\ModuleManager::getInstance()->getAffiliateParsersList();
            ?>

            <!-- KEYWORD SOURCE / PREFILL MODE -->
            <tr id="keyword_source_row">
                <th scope="row"><label><?php esc_html_e('Prefill Mode / Keyword Source', 'content-egg'); ?></label></th>
                <td>
                    <div class="container-fluid p-0 m-0">
                        <div class="row">
                            <?php
                            // Helper for radio items
                            $radio = static function ($value, $label, $desc = '', $extra = '', $pro_feature = false) use ($keyword_source)
                            {
                                $id = 'radio_' . esc_attr($value);
                            ?>
                                <div class="form-check mb-2">
                                    <input class="" type="radio" name="keyword_source" id="<?php echo $id; ?>" value="<?php echo esc_attr($value); ?>" <?php checked($keyword_source, $value); ?> <?php echo $extra; ?>>
                                    <label class="form-check-label" for="<?php echo $id; ?>">
                                        <?php echo esc_html($label); ?>
                                        <?php if ($pro_feature) : ?>
                                            <?php echo wp_kses_post(AdminHelper::getProFeatureWarning()); ?>
                                        <?php endif; ?>
                                    </label>
                                    <?php if ($desc) : ?><div class="form-text"><?php echo esc_html($desc); ?></div><?php endif; ?>
                                </div>
                            <?php
                            };
                            ?>

                            <?php $radio('fully_automatic_ai', __('Fully Automatic AI Mode', 'content-egg'), __('AI will automatically analyze the content, generate relevant keywords, and insert shortcodes into the post content.', 'content-egg') . ' ' . __('Note: Existing product data may be replaced.', 'content-egg'), disabled(! $is_pro, true, false) . ' ' . checked($keyword_source, 'fully_automatic_ai', false), true);
                            echo wp_kses_post($ai_warning); ?>
                            <?php $radio('ai', __('AI Mode', 'content-egg'), __('AI will analyze the content and try to determine the best keywords automatically.', 'content-egg'));
                            echo wp_kses_post($ai_warning); ?>
                            <?php $radio('post_title', __('Post Title', 'content-egg'), __('Uses the post’s title as the keyword.', 'content-egg')); ?>
                            <?php $radio('product_title_module', __('Product Title from Existing Module', 'content-egg'), __('Use product titles from an existing module as new keyword sources.', 'content-egg')); ?>
                            <?php $radio('meta_field', __('Custom Field (Meta)', 'content-egg'), __('Specify a custom field name that contains the keyword.', 'content-egg')); ?>
                            <?php $radio('gtin_module', __('GTIN/EAN from Existing Module', 'content-egg'), __('Use GTIN/EAN values from products added by another module.', 'content-egg')); ?>
                            <?php if ($post_type === 'product')
                            {
                                $radio('gtin_woocommerce', __('GTIN/EAN from WooCommerce', 'content-egg'), __('Use the GTIN/EAN value saved in WooCommerce product data.', 'content-egg'));
                            } ?>

                            <!-- Extra selects / inputs for certain keyword sources -->
                            <div class="mt-2 ms-4" id="title_module_select" style="display:none;">
                                <select name="source_module_title" class="form-select">
                                    <option value="">"><?php esc_html_e('Select a module', 'content-egg'); ?></option>
                                    <?php foreach ($modules_full as $id => $name) : ?>
                                        <option value="<?php echo esc_attr($id); ?>" <?php selected($source_module_title, $id); ?>><?php echo esc_html($name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mt-2 ms-4" id="meta_field_input" style="display:none;">
                                <input type="text" name="meta_field_name" class="form-control" value="<?php echo esc_attr($meta_field_name); ?>" placeholder="e.g. keyword_meta">
                            </div>

                            <div class="mt-2 ms-4" id="gtin_module_select" style="display:none;">
                                <select name="source_module_gtin" class="form-select">
                                    <option value="">"><?php esc_html_e('Select a module', 'content-egg'); ?></option>
                                    <?php foreach ($modules_full as $id => $name) : ?>
                                        <option value="<?php echo esc_attr($id); ?>" <?php selected($source_module_gtin, $id); ?>><?php echo esc_html($name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>

            <!-- EXISTING MODULE DATA BEHAVIOR -->
            <tr>
                <th scope="row"><label><?php esc_html_e('If Module Data Already Exists in Post', 'content-egg'); ?></label></th>
                <td>
                    <div class="container-fluid p-0 m-0">
                        <div class="row">
                            <?php
                            $behaviors = [
                                'skip_module' => __('Skip Module', 'content-egg') . '|' . __('Keep existing data. Prefill only missing modules.', 'content-egg'),
                                'skip_post'   => __('Skip Post Prefill', 'content-egg') . '|' . __('Do not prefill this post if any selected module already has data.', 'content-egg'),
                                'replace'     => __('Replace Existing Module Data', 'content-egg') . '|' . __('Remove existing product data from selected modules and replace with new results.', 'content-egg'),
                            ];
                            foreach ($behaviors as $val => $combo)
                            {
                                [$label, $desc] = explode('|', $combo);
                            ?>
                                <div class="form-check mb-2">
                                    <input class="" type="radio" name="existing_module_behavior" id="behavior_<?php echo esc_attr($val); ?>" value="<?php echo esc_attr($val); ?>" <?php checked($existing_module_behavior, $val); ?>>
                                    <label class="form-check-label" for="behavior_<?php echo esc_attr($val); ?>"><?php echo esc_html($label); ?></label>
                                    <div class="form-text"><?php echo esc_html($desc); ?></div>
                                </div>
                            <?php
                            }
                            ?>
                        </div>
                    </div>
                </td>
            </tr>

            <!-- AI RELEVANCE CHECK -->
            <tr>
                <th scope="row"><label for="ai_relevance_check"><?php esc_html_e('AI Relevance Check', 'content-egg'); ?></label></th>
                <td>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="ai_relevance_check" name="ai_relevance_check" value="1" <?php checked($ai_relevance_check); ?>>
                        <label class="form-check-label" for="ai_relevance_check"><?php esc_html_e('Enable AI-powered relevance checking for products based on the post title and content.', 'content-egg'); ?></label>
                        <div class="small text-muted mt-1"><?php esc_html_e('If enabled, products will be filtered by AI to improve relevance.', 'content-egg'); ?></div>
                        <?php echo wp_kses_post($ai_warning); ?>
                    </div>
                </td>
            </tr>

            <!-- MAX PRODUCTS PER MODULE -->
            <tr>
                <th scope="row"><label for="max_products_per_module"><?php esc_html_e('Maximum Products per Module', 'content-egg'); ?></label></th>
                <td>
                    <select name="max_products_per_module" id="max_products_per_module" class="form-select w-auto">
                        <option value="0" <?php selected($max_products_per_module, 0); ?>><?php esc_html_e('Default Module Settings', 'content-egg'); ?></option>
                        <?php for ($i = 1; $i <= 10; $i++) : ?>
                            <option value="<?php echo $i; ?>" <?php selected($max_products_per_module, $i); ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                    <div class="small text-muted mt-1"><?php esc_html_e('Maximum number of products each selected module can add to a post.', 'content-egg'); ?></div>
                </td>
            </tr>

            <!-- MAX PRODUCTS TOTAL -->
            <tr>
                <th scope="row"><label for="max_products_total"><?php esc_html_e('Maximum Products per Post', 'content-egg'); ?></label></th>
                <td>
                    <select name="max_products_total" id="max_products_total" class="form-select w-auto">
                        <option value="0" <?php selected($max_products_total, 0); ?>><?php esc_html_e('Unlimited', 'content-egg'); ?></option>
                        <?php for ($i = 1; $i <= 30; $i++) : ?>
                            <option value="<?php echo $i; ?>" <?php selected($max_products_total, $i); ?>><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                    <div class="small text-muted mt-1"><?php esc_html_e('Total number of products to insert into each post from all modules combined.', 'content-egg'); ?></div>
                </td>
            </tr>

            <!-- PRODUCT GROUP -->
            <tr>
                <th scope="row"><label for="product_group"><?php esc_html_e('Product Group (Optional)', 'content-egg'); ?></label></th>
                <td>
                    <input type="text" maxlength="60" name="product_group" id="product_group" class="form-control w-auto" value="<?php echo esc_attr($product_group); ?>" placeholder="<?php esc_attr_e('Group name for products', 'content-egg'); ?>">
                    <div class="small text-muted mt-1"><?php esc_html_e('Products added during prefill will be grouped under this name.', 'content-egg'); ?></div>
                </td>
            </tr>

            <!-- SHORTCODE / BLOCK INSERTIONS -->
            <tr>
                <th scope="row"><label><?php esc_html_e('Insert Shortcodes or Blocks into Post Content', 'content-egg'); ?></label></th>
                <td>
                    <div class="container-fluid p-0 m-0">
                        <?php
                        $positions = [
                            'disabled'          => __('Disabled', 'content-egg'),
                            'before_content'    => __('Before Content', 'content-egg'),
                            'after_excerpt'     => __('After Excerpt', 'content-egg'),
                            'middle'            => __('In the Middle of Content', 'content-egg'),
                            'after_content'     => __('After Content', 'content-egg'),
                        ];
                        for ($i = 1; $i <= 10; $i++)
                        {
                            $positions['after_paragraph_' . $i] = sprintf(__('After Paragraph %d', 'content-egg'), $i);
                        }
                        for ($i = 0; $i < 3; $i++) :
                            $pos_val = $shortcode_blocks[$i]['position'] ?? 'disabled';
                            $code_val = $shortcode_blocks[$i]['code'] ?? '';
                        ?>
                            <div class="row align-items-center mb-2">
                                <div class="col-md-4">
                                    <select name="shortcode_blocks[<?php echo $i; ?>][position]" class="form-select">
                                        <?php foreach ($positions as $value => $label) : ?>
                                            <option value="<?php echo esc_attr($value); ?>" <?php selected($pos_val, $value); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <input maxlength="300" type="text" name="shortcode_blocks[<?php echo $i; ?>][code]" class="form-control" value="<?php echo esc_attr($code_val); ?>" placeholder="<?php esc_attr_e('Shortcode or block markup', 'content-egg'); ?>">
                                </div>
                            </div>
                        <?php endfor; ?>
                        <div class="text-muted mt-2">
                            <?php esc_html_e('You can insert shortcodes such as', 'content-egg'); ?> <i>[content-egg-block template=offers_grid]</i> <?php esc_html_e('or use Content Egg Gutenberg block markup.', 'content-egg'); ?>
                        </div>
                    </div>
                </td>
            </tr>

            <!-- CUSTOM FIELDS -->
            <tr>
                <th scope="row"><label><?php esc_html_e('Add Custom Fields to Each Processed Post', 'content-egg'); ?></label></th>
                <td>
                    <div class="container-fluid p-0 m-0">
                        <?php for ($i = 0; $i < 3; $i++) :
                            $key_val   = $custom_fields[$i]['key']   ?? '';
                            $value_val = $custom_fields[$i]['value'] ?? '';
                        ?>
                            <div class="row mb-2">
                                <div class="col-md-4"><input type="text" name="custom_fields[<?php echo $i; ?>][key]" class="form-control" value="<?php echo esc_attr($key_val); ?>" placeholder="<?php esc_attr_e('Field Name', 'content-egg'); ?>"></div>
                                <div class="col-md-8"><input type="text" name="custom_fields[<?php echo $i; ?>][value]" class="form-control" value="<?php echo esc_attr($value_val); ?>" placeholder="<?php esc_attr_e('Field Value (e.g. %PRODUCT.title%)', 'content-egg'); ?>"></div>
                            </div>
                        <?php endfor; ?>
                        <div class="text-muted mt-2"><?php esc_html_e('Available placeholders:', 'content-egg'); ?> <i>%KEYWORD%</i>, <i>%RANDOM(10,50)%</i>, <i>%PRODUCT.title%</i>, <i>%PRODUCT.price%</i>, <i>%PRODUCT.domain%</i>, <i>%PRODUCT.url%</i>, <i>%PRODUCT.ATTRIBUTE.attribute-name%</i></div>
                    </div>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary me-2"><?php esc_html_e('Start Prefill', 'content-egg'); ?></button>
            <a href="<?php echo esc_url($back_url); ?>" class="button button-secondary"><?php esc_html_e('Back', 'content-egg'); ?></a>
        </p>
    </form>
</div>

<script>
    // Toggle keyword-source dependant fields & hide rest of form on fully-AI
    (function() {
        document.addEventListener('DOMContentLoaded', () => {
            const keywordRadios = document.querySelectorAll('input[name="keyword_source"]');
            const keywordRow = document.getElementById('keyword_source_row');
            const metaInput = document.getElementById('meta_field_input');
            const gtinSelect = document.getElementById('gtin_module_select');
            const titleSelect = document.getElementById('title_module_select');

            const toggle = () => {
                const selected = document.querySelector('input[name="keyword_source"]:checked').value;
                metaInput.style.display = selected === 'meta_field' ? 'block' : 'none';
                gtinSelect.style.display = selected === 'gtin_module' ? 'block' : 'none';
                titleSelect.style.display = selected === 'product_title_module' ? 'block' : 'none';

                let row = keywordRow.nextElementSibling;
                while (row) {
                    row.style.display = selected === 'fully_automatic_ai' ? 'none' : '';
                    row = row.nextElementSibling;
                }
            };
            keywordRadios.forEach(el => el.addEventListener('change', toggle));
            toggle();
        });
    })();
</script>