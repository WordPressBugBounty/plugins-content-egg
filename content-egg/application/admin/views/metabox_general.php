<?php defined('\ABSPATH') || exit; ?>
<?php
\wp_nonce_field('contentegg_metabox', 'contentegg_nonce');
$tpl_manager = ContentEgg\application\components\BlockTemplateManager::getInstance();
$templates = $tpl_manager->getTemplatesList(true);
if (!$global_keyword = \get_post_meta($post->ID, '_cegg_global_autoupdate_keyword', true))
    $global_keyword = '';

?>

<script>
    jQuery(document).ready(function($) {

        jQuery('#cegg_update_lists, #cegg_update_prices').click(function(e) {
            e.preventDefault();
            var this_btn = $(this);
            jQuery('#cegg_update_lists, #cegg_update_prices, .button, .btn').attr('disabled', true);
            var nonce = $('#contentegg_nonce').val();
            jQuery('body').addClass('cegg_wait');

            jQuery.ajax({
                type: "POST",
                url: ajaxurl,
                data: {
                    action: 'cegg_update_products',
                    btn: this_btn.attr('id'),
                    contentegg_nonce: nonce,
                    post_id: <?php echo \esc_attr($post->ID); ?>
                },
                success: function(data) {
                    location.reload();
                },
                error: function(errorThrown) {
                    location.reload();
                },
                timeout: 180000
            });
            return false;
        });
    });
</script>
<div class="row mt-3 pb-3">
    <div class="col">

        <div class="input-group input-group-sm">

            <input style="flex-basis: 20%;" class="form-control form-control-sm shortcode-input cegg-copy-input" ng-model="blockShortcode" select-on-click readonly type="text" />
            <button class="btn btn-outline-secondary cegg-copy-button" type="button" title="Copy to clipboard"><i class="bi bi-copy"></i></button>

            <select class="form-control form-control-sm ms-3" ng-init="blockShortcodeBuillder.template = '<?php echo esc_attr(key($templates)); ?>'; buildBlockShortcode();" ng-model="blockShortcodeBuillder.template" ng-change="buildBlockShortcode();">
                <?php foreach ($templates as $id => $name) : ?>
                    <option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($name); ?></option>
                <?php endforeach; ?>
            </select>

            <select ng-show="productGroups.length" class="form-control form-control-sm" ng-model="blockShortcodeBuillder.group" ng-change="buildBlockShortcode();">
                <option value="">- <?php esc_html_e('Groups', 'content-egg'); ?> ({{productGroups.length}}) -</option>
                <option ng-repeat="group in productGroups" value="{{group}}">{{group}}</option>
            </select>

            <a target="_blank" href="https://ce-docs.keywordrush.com/frontend/how-content-is-displayed" class="btn btn-sm btn-secondary ms-3" title="<?php esc_html_e('View plugin documentation', 'content-egg'); ?>">
                <i class="bi bi-question-circle"></i>
            </a>
        </div>
    </div>
</div>
<div class="row mt-2">
    <div class="col text-end">
        <div class="input-group input-group-sm">
            <input type="text" ng-model="newProductGroup" select-on-click on-enter="addProductGroup()" class="form-control form-control-sm" placeholder="<?php esc_html_e('Add a product group', 'content-egg'); ?>" aria-label="<?php esc_html_e('Add a product group ', 'content-egg'); ?>">
            <div class="input-group-btn me-3">
                <button ng-disabled="!newProductGroup" ng-click="addProductGroup()" type="button" class="btn btn-sm btn-outline-primary" aria-label="Add">
                    <i class="bi bi-plus"></i>
                </button>
            </div>

            <input class="form-control form-control-sm" name="globalUpdateKeyword" value="<?php echo esc_attr($global_keyword); ?>" type="text" placeholder="<?php esc_html_e('Global auto-update keyword', 'content-egg'); ?>" title="<?php esc_html_e('Global auto-update keyword for all active modules', 'content-egg'); ?>">

            <?php if ($keywordsExist || $global_keyword) : ?>
                <input type="submit" id="cegg_update_lists" class="btn btn-sm btn-outline-primary ms-3" value="<?php esc_html_e('Refresh listings', 'content-egg'); ?>" title="<?php esc_html_e('Refresh all product listings using auto-update keywords', 'content-egg'); ?>">
            <?php endif; ?>
            <?php if ($dataExist) : ?>
                <input type="submit" id="cegg_update_prices" class="btn btn-sm btn-outline-primary ms-3" value="<?php esc_html_e('Update prices', 'content-egg'); ?>" title="<?php esc_html_e('Force update all product prices', 'content-egg'); ?>">
            <?php endif; ?>

        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="input-group input-group-sm">
        <input ng-disabled="processCounter" type="text" ng-model="global_keywords" select-on-click on-enter="global_findAll()" class="form-control" placeholder="<?php echo esc_attr('Keyword to search all modules', 'content-egg'); ?>" aria-label="<?php echo esc_attr('Keyword to search all modules', 'content-egg'); ?>">
        <button ng-disabled='processCounter || !global_keywords' ng-click="global_findAll()" type="button" class="btn btn-primary" aria-label="Find">
            <i class="bi bi-search"></i>
        </button>
        <button ng-show='!processCounter && global_isSearchResults()' ng-click="global_addAll()" type="button" class="btn btn-outline-primary"><?php echo esc_attr('Add all', 'content-egg'); ?></button>
        <button ng-show='global_isAddedResults()' ng-click="global_deleteAll()" ng-confirm-click="<?php esc_html_e('Are you sure you want to delete results from all modules?', 'content-egg'); ?>" type="button" class="btn btn-outline-danger ms-3"><?php echo esc_attr('Remove all', 'content-egg'); ?></button>
    </div>
</div>

<script>
    jQuery(document).ready(function() {
        jQuery('.cegg-copy-button').click(function() {
            var copyButton = jQuery(this);
            var input = copyButton.siblings('.cegg-copy-input');
            var copyText = input.val();
            navigator.clipboard.writeText(copyText).then(function() {
                var icon = copyButton.find('i');
                icon.removeClass('bi-copy').addClass('bi-check');
                setTimeout(function() {
                    icon.removeClass('bi-check').addClass('bi-copy');
                }, 1000);

            });
        });
    });
</script>