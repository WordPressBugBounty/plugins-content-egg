<?php

use ContentEgg\application\admin\ProductPrefillController;

use function ContentEgg\prnx;

defined('\ABSPATH') || exit; ?>

<?php
$back_url = admin_url('admin.php?page=' . ProductPrefillController::slug);

if (!$has_ai_api_key)
{
    $ai_warning = sprintf(
        '<div class="alert alert-warning mt-4" role="alert">
        <strong>%s</strong>
        You need to set up an <a href="%s" target="_blank" rel="noopener noreferrer" class="alert-link">OpenAI API key</a> to use the AI features.
        <a href="%s" class="alert-link">Go to Settings</a>
    </div>',
        __('Warning:', 'content-egg'),
        'https://platform.openai.com/api-keys',
        admin_url('admin.php?page=content-egg')
    );
}
else
{
    $ai_warning = '';
}
?>

<div class="wrap cegg5-container">

    <h1 class="h3">
        <?php echo esc_html(__('Product Prefill Tool', 'content-egg')); ?>

    </h1>
    <?php if (!empty($selected_posts)) : ?>

        <div class="alert alert-warning mt-4" role="alert">
            <strong><?php echo esc_html__('Important:', 'content-egg'); ?></strong>
            <?php echo esc_html__('Prefill can modify your posts and changes cannot be undone.', 'content-egg'); ?>
            <?php echo esc_html__('Please back up your database before proceeding.', 'content-egg'); ?>
            <a href="https://developer.wordpress.org/advanced-administration/security/backup/#database-backup-instructions"
                target="_blank"
                rel="noopener noreferrer"
                class="alert-link">
                <?php echo esc_html__('Learn how to back up your database', 'content-egg'); ?>
            </a>
        </div>
    <?php endif; ?>

    <h2 class="h5">
        <?php
        printf(
            esc_html__('Total Selected Posts: %d', 'content-egg'),
            intval($total_posts)
        );
        ?>
    </h2>

    <?php if (!empty($selected_posts)) : ?>

        <button type="button" class="button button-secondary mb-3" id="togglePosts">
            <?php echo esc_html__('Show Selected Posts', 'content-egg'); ?>
        </button>

        <div id="selectedPosts" style="display: none; margin-top: 1em; max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 1em;">
            <ul style="list-style: disc; margin-left: 2em;">
                <?php foreach ($selected_posts as $post) : ?>
                    <li>
                        <a href="<?php echo esc_url(get_permalink($post->ID)); ?>" target="_blank" rel="noopener">
                            <?php echo esc_html($post->post_title); ?>
                        </a>
                    </li>
                <?php endforeach; ?>

                <?php if ($total_posts > ProductPrefillController::PREVIEW_POST_LIMIT): ?>
                    <p>
                        <?php echo esc_html(sprintf(__('Note: Only the first %s posts are displayed.', 'content-egg'), ProductPrefillController::PREVIEW_POST_LIMIT)); ?>
                    </p>
                <?php endif; ?>
            </ul>
        </div>

        <!-- JavaScript for toggling the selected posts section -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var toggleBtn = document.getElementById('togglePosts');
                var postList = document.getElementById('selectedPosts');
                var isVisible = false;

                toggleBtn.addEventListener('click', function() {
                    isVisible = !isVisible;
                    if (isVisible) {
                        postList.style.display = 'block';
                        toggleBtn.innerText = '<?php echo esc_js(__('Hide Selected Posts', 'content-egg')); ?>';
                    } else {
                        postList.style.display = 'none';
                        toggleBtn.innerText = '<?php echo esc_js(__('Show Selected Posts', 'content-egg')); ?>';
                    }
                });
            });
        </script>

    <?php else : ?>

        <div class="notice notice-warning">
            <p><?php echo esc_html(__('No posts found for the selected criteria.', 'content-egg')); ?></p>
        </div>

        <p>
            <a href="<?php echo esc_url($back_url); ?>" class="button button-secondary">
                <?php echo esc_html__('Back', 'content-egg'); ?>
            </a>
        </p>

    <?php endif; ?>
</div>

<?php
if (empty($selected_posts)) return;

$modules = \ContentEgg\application\components\ModuleManager::getInstance()->getAffiliateParsersList(true, true);
?>

<div class="wrap cegg5-container">

    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=content-egg-product-prefill&action=prefill_start')); ?>">
        <?php wp_nonce_field('prefill_start_nonce'); ?>
        <input type="hidden" name="page" value="content-egg-product-prefill">
        <input type="hidden" name="action" value="prefill_start">
        <input type="hidden" name="prefill_transient" value="<?php echo esc_attr($prefill_transient_key); ?>">

        <table class="form-table">

            <tr>
                <th scope="row">
                    <label><?php echo esc_html(__('Modules for Product Prefill', 'content-egg')); ?></label>
                </th>
                <td>
                    <div class="container-fluid p-0 m-0">
                        <div class="row">
                            <?php foreach ($modules as $module_id => $module_name): ?>
                                <div class="col-md-4 mb-1">
                                    <div class="form-check">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="modules[]"
                                            value="<?php echo esc_attr($module_id); ?>"
                                            id="module_<?php echo esc_attr($module_id); ?>"
                                            <?php if (!empty($_GET['modules']) && in_array($module_id, (array) $_GET['modules'])) echo 'checked'; ?>>
                                        <label class="form-check-label" for="module_<?php echo esc_attr($module_id); ?>">
                                            <?php echo esc_html($module_name); ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="small text-muted mt-1">
                            <?php echo esc_html(__('Modules will be processed based on their priority, which can be set in each module’s settings.', 'content-egg')); ?>
                        </div>
                    </div>
                </td>
            </tr>

            <?php
            $modules = \ContentEgg\application\components\ModuleManager::getInstance()->getAffiliateParsersList();
            ?>

            <tr>
                <th scope="row">
                    <label><?php echo esc_html(__('Prefill Mode / Keyword Source', 'content-egg')); ?></label>
                </th>
                <td>
                    <div class="container-fluid p-0 m-0">
                        <div class="row">
                            <!-- Fully Automatic AI Mode -->
                            <div class="form-check mb-2">
                                <input class="form-check-input1" type="radio" name="keyword_source" id="source_fully_ai" value="fully_automatic_ai">
                                <label class="form-check-label" for="source_fully_ai">
                                    <?php echo esc_html(__('Fully Automatic AI Mode', 'content-egg')); ?> <?php echo esc_html(__('(beta)', 'content-egg')); ?>
                                </label>
                                <div class="form-text">
                                    <?php echo esc_html(__('AI will automatically analyze the content, generate relevant keywords, and insert shortcodes into the post content.', 'content-egg')); ?>
                                </div>
                                <?php echo wp_kses_post($ai_warning); ?>
                            </div>

                            <!-- AI Mode -->
                            <div class="form-check mb-2">
                                <input class="form-check-input1" type="radio" name="keyword_source" id="source_ai" value="ai">
                                <label class="form-check-label" for="source_ai">
                                    <?php echo esc_html(__('AI Mode', 'content-egg')); ?>
                                </label>
                                <div class="form-text"><?php echo esc_html(__('AI will analyze the content and try to determine the best keywords automatically.', 'content-egg')); ?></div>
                                <?php echo wp_kses_post($ai_warning); ?>
                            </div>

                            <!-- Post Title -->
                            <div class="form-check mb-2">
                                <input class="form-check-input1" type="radio" name="keyword_source" id="source_title" value="post_title" checked>
                                <label class="form-check-label" for="source_title">
                                    <?php echo esc_html(__('Post Title', 'content-egg')); ?>
                                </label>
                                <div class="form-text"><?php echo esc_html(__('Uses the post’s title as the keyword.', 'content-egg')); ?></div>
                            </div>

                            <!-- Product Title from Existing Module -->
                            <div class="form-check mb-2">
                                <input class="form-check-input1" type="radio" name="keyword_source" id="source_title_module" value="product_title_module">
                                <label class="form-check-label" for="source_title_module">
                                    <?php echo esc_html(__('Product Title from Existing Module', 'content-egg')); ?>
                                </label>
                                <div class="form-text"><?php echo esc_html(__('Use product titles from an existing module as new keyword sources.', 'content-egg')); ?></div>
                                <div class="mt-2 ms-4" id="title_module_select" style="display: none;">
                                    <select name="source_module_title" class="form-select">
                                        <option value=""><?php echo esc_html(__('Select a module', 'content-egg')); ?></option>
                                        <?php foreach ($modules as $id => $name): ?>
                                            <option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Custom Meta Field -->
                            <div class="form-check mb-2">
                                <input class="form-check-input1" type="radio" name="keyword_source" id="source_meta" value="meta_field">
                                <label class="form-check-label" for="source_meta">
                                    <?php echo esc_html(__('Custom Field (Meta)', 'content-egg')); ?>
                                </label>
                                <div class="form-text"><?php echo esc_html(__('Specify a custom field name that contains the keyword.', 'content-egg')); ?></div>
                                <div class="mt-2 ms-4" id="meta_field_input" style="display: none;">
                                    <input type="text" name="meta_field_name" class="form-control" placeholder="e.g. keyword_meta">
                                </div>
                            </div>

                            <!-- GTIN from Existing Module -->
                            <div class="form-check mb-2">
                                <input class="form-check-input1" type="radio" name="keyword_source" id="source_gtin" value="gtin_module">
                                <label class="form-check-label" for="source_gtin">
                                    <?php echo esc_html(__('GTIN/EAN from Existing Module', 'content-egg')); ?>
                                </label>
                                <div class="form-text"><?php echo esc_html(__('Use GTIN/EAN values from products added by another module.', 'content-egg')); ?></div>
                                <div class="mt-2 ms-4" id="gtin_module_select" style="display: none;">
                                    <select name="source_module_gtin" class="form-select">
                                        <option value=""><?php echo esc_html(__('Select a module', 'content-egg')); ?></option>
                                        <?php foreach ($modules as $id => $name): ?>
                                            <option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <?php if ($post_type === 'product'): ?>
                                <!-- GTIN from WooCommerce Product -->
                                <div class="form-check mb-2">
                                    <input class="form-check-input1" type="radio" name="keyword_source" id="source_gtin_wc" value="gtin_woocommerce">
                                    <label class="form-check-label" for="source_gtin_wc">
                                        <?php echo esc_html(__('GTIN/EAN from WooCommerce', 'content-egg')); ?>
                                    </label>
                                    <div class="form-text">
                                        <?php echo esc_html(__('Use the GTIN/EAN value saved in WooCommerce product data.', 'content-egg')); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </td>
            </tr>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const toggleFields = () => {
                        const selected = document.querySelector('input[name="keyword_source"]:checked')?.value;

                        document.getElementById('meta_field_input').style.display = selected === 'meta_field' ? 'block' : 'none';
                        document.getElementById('gtin_module_select').style.display = selected === 'gtin_module' ? 'block' : 'none';
                        document.getElementById('title_module_select').style.display = selected === 'product_title_module' ? 'block' : 'none';
                    };

                    document.querySelectorAll('input[name="keyword_source"]').forEach(el => {
                        el.addEventListener('change', toggleFields);
                    });

                    toggleFields();
                });
            </script>

            <tr>
                <th scope="row">
                    <label><?php echo esc_html(__('If Module Data Already Exists in Post', 'content-egg')); ?></label>
                </th>
                <td>
                    <div class="container-fluid p-0 m-0">
                        <div class="row">

                            <!-- Skip Module (default) -->
                            <div class="form-check mb-2">
                                <input class="form-check-input1" type="radio" name="existing_module_behavior" id="behavior_skip_module" value="skip_module" checked>
                                <label class="form-check-label" for="behavior_skip_module">
                                    <?php echo esc_html(__('Skip Module', 'content-egg')); ?>
                                </label>
                                <div class="form-text"><?php echo esc_html(__('Keep existing data. Prefill only missing modules.', 'content-egg')); ?></div>
                            </div>

                            <!-- Skip Post Prefill -->
                            <div class="form-check mb-2">
                                <input class="form-check-input1" type="radio" name="existing_module_behavior" id="behavior_skip_post" value="skip_post">
                                <label class="form-check-label" for="behavior_skip_post">
                                    <?php echo esc_html(__('Skip Post Prefill', 'content-egg')); ?>
                                </label>
                                <div class="form-text"><?php echo esc_html(__('Do not prefill this post if any selected module already has data.', 'content-egg')); ?></div>
                            </div>

                            <!-- Replace Existing Data -->
                            <div class="form-check mb-2">
                                <input class="form-check-input1" type="radio" name="existing_module_behavior" id="behavior_replace" value="replace">
                                <label class="form-check-label" for="behavior_replace">
                                    <?php echo esc_html(__('Replace Existing Module Data', 'content-egg')); ?>
                                </label>
                                <div class="form-text"><?php echo esc_html(__('Remove existing product data from selected modules and replace with new results.', 'content-egg')); ?></div>
                            </div>

                        </div>
                    </div>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="ai_relevance_check"><?php echo esc_html(__('AI Relevance Check', 'content-egg')); ?></label>
                </th>
                <td>
                    <div class="form-check">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="ai_relevance_check"
                            name="ai_relevance_check"
                            value="1">
                        <label class="form-check-label" for="ai_relevance_check">
                            <?php echo esc_html(__('Enable AI-powered relevance check for products based on post title.', 'content-egg')); ?>
                        </label>
                        <div class="small text-muted mt-1">
                            <?php echo esc_html(__('If enabled, products will be filtered by AI for better relevance.', 'content-egg')); ?>
                        </div>
                        <?php echo wp_kses_post($ai_warning); ?>

                    </div>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="max_products_per_module"><?php echo esc_html(__('Maximum Products per Module', 'content-egg')); ?></label>
                </th>
                <td>
                    <select name="max_products_per_module" id="max_products_per_module" class="form-select w-auto">
                        <option value="0" selected><?php echo esc_html(__('Default Module Settings', 'content-egg')); ?></option>
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                    <div class="small text-muted mt-1">
                        <?php echo esc_html(__('Maximum number of products each selected module can add to a post.', 'content-egg')); ?>
                    </div>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="max_products_total"><?php echo esc_html(__('Maximum Products per Post', 'content-egg')); ?></label>
                </th>
                <td>
                    <select name="max_products_total" id="max_products_total" class="form-select w-auto">
                        <option value="0" selected><?php echo esc_html(__('Unlimited', 'content-egg')); ?></option>
                        <?php for ($i = 1; $i <= 30; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                    <div class="small text-muted mt-1">
                        <?php echo esc_html(__('Total number of products to insert into each post from all modules combined.', 'content-egg')); ?>
                    </div>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="product_group"><?php echo esc_html(__('Product Group (Optional)', 'content-egg')); ?></label>
                </th>
                <td>
                    <input type="text" name="product_group" id="product_group" class="form-control w-auto" placeholder="<?php echo esc_attr(__('Group name for products', 'content-egg')); ?>">
                    <div class="small text-muted mt-1">
                        <?php echo esc_html(__('Products added during prefill will be grouped under this name.', 'content-egg')); ?>
                    </div>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label><?php echo esc_html(__('Insert Shortcodes or Blocks into Post Content', 'content-egg')); ?></label>
                </th>
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
                        ?>

                        <?php for ($i = 0; $i < 3; $i++): ?>
                            <div class="row align-items-center mb-2">
                                <div class="col-md-4">
                                    <select name="shortcode_blocks[<?php echo $i; ?>][position]" class="form-select">
                                        <?php foreach ($positions as $value => $label): ?>
                                            <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <input type="text" name="shortcode_blocks[<?php echo $i; ?>][code]" class="form-control" placeholder="<?php echo esc_attr(__('Shortcode or block markup', 'content-egg')); ?>">
                                </div>

                            </div>
                        <?php endfor; ?>

                        <div class="text-muted mt-2">
                            <?php echo esc_html(__('You can insert shortcodes such as', 'content-egg')); ?>
                            <i>[content-egg-block template=offers_grid]</i>
                            <?php echo esc_html__('or use Content Egg Gutenberg block markup.', 'content-egg'); ?>
                        </div>
                    </div>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label><?php echo esc_html(__('Add Custom Fields to Each Processed Post', 'content-egg')); ?></label>
                </th>
                <td>
                    <div class="container-fluid p-0 m-0">
                        <?php for ($i = 0; $i < 3; $i++): ?>
                            <div class="row mb-2">
                                <div class="col-md-4">
                                    <input type="text" name="custom_fields[<?php echo $i; ?>][key]" class="form-control" placeholder="<?php echo esc_attr(__('Field Name', 'content-egg')); ?>">
                                </div>
                                <div class="col-md-8">
                                    <input type="text" name="custom_fields[<?php echo $i; ?>][value]" class="form-control" placeholder="<?php echo esc_attr(__('Field Value (e.g. %PRODUCT.title%)', 'content-egg')); ?>">
                                </div>
                            </div>
                        <?php endfor; ?>

                        <div class="text-muted mt-2">
                            <?php echo esc_html(__('Available placeholders:', 'content-egg')); ?>
                            <i>%KEYWORD%</i>,
                            <i>%RANDOM(10,50)%</i>,
                            <i>%PRODUCT.title%</i>,
                            <i>%PRODUCT.price%</i>,
                            <i>%PRODUCT.domain%</i>,
                            <i>%PRODUCT.url%</i>
                        </div>
                    </div>
                </td>
            </tr>

        </table>

        <p class="submit">
            <button type="submit" class="button button-primary me-2">
                <?php echo esc_html__('Start Prefill', 'content-egg'); ?>
            </button>
            <a href="<?php echo esc_url($back_url); ?>" class="button button-secondary">
                <?php echo esc_html__('Back', 'content-egg'); ?>
            </a>
        </p>

    </form>

</div>