<?php defined('ABSPATH') || exit; ?>

<div class="wrap cegg5-container">
    <h1 class="h3"><?php echo esc_html(__('Product Prefill Tool', 'content-egg')); ?></h1>
    <p><?php echo esc_html(__('Use this tool to automatically add products to your existing posts.', 'content-egg')); ?></p>

    <h2 class="h5"><?php echo esc_html(__('Select Posts to Prefill', 'content-egg')); ?></h2>

    <form method="get" action="">
        <?php wp_nonce_field('prefill_config_nonce'); ?>
        <input type="hidden" name="page" value="content-egg-product-prefill">
        <input type="hidden" name="action" value="prefill_config">

        <table class="form-table">
            <?php
            $supported_types = \ContentEgg\application\admin\GeneralConfig::getInstance()->option('post_types');
            $all_public_types = get_post_types(['public' => true], 'names');
            $post_types = array_intersect($supported_types, $all_public_types);
            $selected_post_type = reset($post_types);
            ?>

            <!-- Post Type -->
            <tr>
                <th scope="row"><label for="post_type"><?php echo esc_html(__('Post Type', 'content-egg')); ?></label></th>
                <td>
                    <select name="_post_type" id="post_type" class="form-select w-auto">
                        <?php foreach ($post_types as $post_type): ?>
                            <option value="<?php echo esc_attr($post_type); ?>" <?php selected($post_type, $selected_post_type); ?>>
                                <?php echo esc_html(get_post_type_object($post_type)->labels->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <!-- Post Status -->
            <tr>
                <th scope="row"><label for="post_status"><?php echo esc_html(__('Post Status', 'content-egg')); ?></label></th>
                <td>
                    <select name="post_status" id="post_status" class="form-select w-auto">
                        <option value="publish"><?php echo esc_html(__('Published', 'content-egg')); ?></option>
                        <option value="draft"><?php echo esc_html(__('Draft', 'content-egg')); ?></option>
                        <option value="pending"><?php echo esc_html(__('Pending', 'content-egg')); ?></option>
                        <option value="future"><?php echo esc_html(__('Future', 'content-egg')); ?></option>
                        <option value="private"><?php echo esc_html(__('Private', 'content-egg')); ?></option>
                    </select>
                </td>
            </tr>

            <!-- Categories (post) -->
            <tr id="category-row-post">
                <th scope="row"><label for="category_post"><?php echo esc_html(__('Category', 'content-egg')); ?></label></th>
                <td>
                    <?php
                    wp_dropdown_categories([
                        'show_option_all' => __('All Categories', 'content-egg'),
                        'name'            => 'category_post',
                        'id'              => 'category_post',
                        'orderby'         => 'name',
                        'hide_empty'      => false,
                        'taxonomy'        => 'category',
                        'class'           => 'form-select w-auto',
                    ]);
                    ?>
                </td>
            </tr>

            <!-- Categories (product) -->
            <tr id="category-row-product">
                <th scope="row"><label for="category_product"><?php echo esc_html(__('Product Category', 'content-egg')); ?></label></th>
                <td>
                    <?php
                    wp_dropdown_categories([
                        'show_option_all' => __('All Product Categories', 'content-egg'),
                        'name'            => 'category_product',
                        'id'              => 'category_product',
                        'orderby'         => 'name',
                        'hide_empty'      => true,
                        'taxonomy'        => 'product_cat',
                        'class'           => 'form-select w-auto',
                    ]);
                    ?>
                </td>
            </tr>

            <!-- Author -->
            <tr>
                <th scope="row"><label for="author"><?php echo esc_html(__('Author', 'content-egg')); ?></label></th>
                <td>
                    <?php
                    wp_dropdown_users([
                        'name'            => 'author',
                        'show_option_all' => __('All Authors', 'content-egg'),
                        'selected'        => isset($_GET['author']) ? intval($_GET['author']) : 0,
                        'capability'      => 'edit_posts',
                        'class'           => 'form-select w-auto',
                    ]);
                    ?>
                </td>
            </tr>

            <!-- Date Range -->
            <tr>
                <th scope="row"><label for="date_from"><?php echo esc_html(__('Publish Date Range', 'content-egg')); ?></label></th>
                <td>
                    <input type="date" name="date_from" class="form-control w-auto d-inline-block" />
                    —
                    <input type="date" name="date_to" class="form-control w-auto d-inline-block" />
                </td>
            </tr>

            <!-- Content Egg Data Filter -->
            <tr>
                <th scope="row"><label for="ce_filter"><?php echo esc_html(__('Filter by Existing Product Data', 'content-egg')); ?></label></th>
                <td>
                    <select name="ce_filter" id="ce_filter" class="form-select w-auto">
                        <option value="all"><?php echo esc_html(__('All Posts', 'content-egg')); ?></option>
                        <option value="with_data"><?php echo esc_html(__('Posts With Content Egg Data', 'content-egg')); ?></option>
                        <option value="without_data"><?php echo esc_html(__('Posts Without Content Egg Data', 'content-egg')); ?></option>
                    </select>
                </td>
            </tr>

            <!-- Keywords -->
            <tr>
                <th scope="row"><label for="keywords"><?php echo esc_html(__('Search by Keyword', 'content-egg')); ?></label></th>
                <td>
                    <input type="text" name="keywords" id="keywords" placeholder="<?php echo esc_attr(__('e.g. laptop, travel, fitness', 'content-egg')); ?>" class="form-control" />
                </td>
            </tr>

            <!-- Include Post IDs -->
            <tr>
                <th scope="row"><label for="post__in"><?php echo esc_html(__('Include Post IDs', 'content-egg')); ?></label></th>
                <td>
                    <input type="text" name="post__in" id="post__in" placeholder="1,2,3" class="form-control" />
                </td>
            </tr>

            <!-- Exclude Post IDs -->
            <tr>
                <th scope="row"><label for="post__not_in"><?php echo esc_html(__('Exclude Post IDs', 'content-egg')); ?></label></th>
                <td>
                    <input type="text" name="post__not_in" id="post__not_in" placeholder="4,5,6" class="form-control" />
                </td>
            </tr>

            <!-- Limit -->
            <tr>
                <th scope="row"><label for="post_limit"><?php echo esc_html(__('Post Limit', 'content-egg')); ?></label></th>
                <td>
                    <input type="number" name="post_limit" id="post_limit" class="form-control w-auto" />
                </td>
            </tr>

            <!-- Offset -->
            <tr>
                <th scope="row"><label for="offset"><?php echo esc_html(__('Offset', 'content-egg')); ?></label></th>
                <td>
                    <input type="number" name="offset" id="offset" class="form-control w-auto" />
                </td>
            </tr>

        </table>

        <?php submit_button(__('Filter Posts', 'content-egg')); ?>
    </form>
</div>

<!-- JavaScript for dynamic category switching -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var postTypeSelect = document.getElementById('post_type');
        var categoryRowPost = document.getElementById('category-row-post');
        var categoryRowProduct = document.getElementById('category-row-product');

        function toggleCategoryRows() {
            var selectedValue = postTypeSelect.value;

            categoryRowPost.style.display = (selectedValue === 'post') ? '' : 'none';
            categoryRowProduct.style.display = (selectedValue === 'product') ? '' : 'none';
        }

        toggleCategoryRows();
        postTypeSelect.addEventListener('change', toggleCategoryRows);
    });
</script>