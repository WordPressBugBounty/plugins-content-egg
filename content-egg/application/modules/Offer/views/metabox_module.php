<?php defined('\ABSPATH') || exit; ?>

<?php
$tpl_manager = ContentEgg\application\components\ModuleTemplateManager::getInstance($module_id);
$templates = $tpl_manager->getTemplatesList(true);
$is_woo = (\get_post_type($GLOBALS['post']->ID) == 'product') ? true : false;
$isAffiliateParser = $module->isAffiliateParser();

?>

<div ng-controllerTMP="<?php echo esc_attr($module_id); ?>Controller">
    <input type="hidden" name="cegg_data[<?php echo esc_attr($module_id); ?>]" ng-value="models.<?php echo esc_attr($module_id); ?>.added | json" />
    <input type="hidden" name="cegg_updateKeywords[<?php echo esc_attr($module_id); ?>]" ng-value="updateKeywords.<?php echo esc_attr($module_id); ?>" />
    <input type="hidden" name="cegg_updateParams[<?php echo esc_attr($module_id); ?>]" ng-value="updateParams.<?php echo esc_attr($module_id); ?>| json" />

    <nav class="mt-5 mb-4 small">
        <div class="nav nav-tabs" id="nav-tab-<?php echo esc_attr($module_id); ?>" role="tablist">
            <button class="nav-link active" id="nav-products-tab-<?php echo esc_attr($module_id); ?>" data-bs-toggle="tab" data-bs-target="#nav-products-<?php echo esc_attr($module_id); ?>" type="button" role="tab" aria-controls="nav-products-<?php echo esc_attr($module_id); ?>" aria-selected="{{activeResultTabs.<?php echo esc_attr($module_id); ?>}}">
                <?php echo esc_html($module->getName()); ?>
                <span ng-show="models.<?php echo esc_attr($module_id); ?>.added.length" class="badge" ng-class="{'text-bg-danger':models.<?php echo esc_attr($module_id); ?>.added_changed, 'text-bg-dark':!models.<?php echo esc_attr($module_id); ?>.added_changed}">{{models.<?php echo esc_attr($module_id); ?>.added.length}}</span>
            </button>
        </div>
    </nav>
    <div class="tab-content" id="nav-tabContent">
        <div class="tab-pane show active" id="nav-products-<?php echo esc_attr($module_id); ?>" role="tabpanel" aria-labelledby="nav-products-tab-<?php echo esc_attr($module_id); ?>" tabindex="0">

            <div class="row mb-2">
                <div class="pe-0 col-lg-8 col-md-7 col-sm-12">
                    <div class="input-group input-group-sm">
                        <input class="form-control form-control-sm shortcode-input cegg-copy-input" ng-model="shortcodes.<?php echo esc_attr($module_id); ?>" select-on-click readonly type="text" style="flex-basis: 35%;" />
                        <button class="btn btn-outline-secondary cegg-copy-button" type="button" title="Copy to clipboard"><i class="bi bi-copy"></i></button>

                        <?php if ($templates) : ?>
                            <select class="form-control form-control-sm ms-1" ng-model="selectedTemplate_<?php echo esc_attr($module_id); ?>" ng-change="buildShortcode('<?php echo esc_attr($module_id); ?>', selectedTemplate_<?php echo esc_attr($module_id); ?>, selectedGroup_<?php echo esc_attr($module_id); ?>);">
                                <option value="">&larr; <?php esc_html_e('Shortcode Template', 'content-egg'); ?></option>
                                <?php foreach ($templates as $id => $name) : ?>
                                    <option value="<?php echo esc_attr($id); ?>"><?php echo esc_html($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <select ng-show="productGroups.length" class="form-control form-control-sm" ng-model="selectedGroup_<?php echo esc_attr($module_id); ?>" ng-change="buildShortcode('<?php echo esc_attr($module_id); ?>', selectedTemplate_<?php echo esc_attr($module_id); ?>, selectedGroup_<?php echo esc_attr($module_id); ?>);">
                            <option value="">&larr; <?php esc_html_e('Group', 'content-egg'); ?></option>
                            <option ng-repeat="group in productGroups" value="{{group}}">{{group}}</option>
                        </select>

                    </div>
                </div>
                <div class="ps-0 col-lg-4 col-md-5 col-sm-12">
                    <div class="input-group input-group-sm float-end" style="width: auto;">

                        <a class='btn btn-primary btn-sm' ng-click="addBlank('<?php echo esc_attr($module_id); ?>')"><i class="bi bi-plus-square"></i> <?php esc_html_e('Add offer', 'content-egg'); ?></a>

                        <button title="<?php esc_html_e('Remove all', 'content-egg'); ?>" type="button" class="btn btn-sm btn-outline-danger ms-2" ng-click="deleteAll('<?php echo esc_attr($module_id); ?>')" ng-confirm-click="<?php esc_html_e('Are you sure you want to delete all the results?', 'content-egg'); ?>" ng-show='models.<?php echo esc_attr($module_id); ?>.added.length'><i class="bi bi-trash3"></i></span></button>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col">
                    <div ng-show="!models.<?php echo esc_attr($module_id); ?>.added.length && !models.<?php echo esc_attr($module_id); ?>.processing" class="alert alert-secondary text-center small"><?php esc_html_e('No module data...', 'content-egg'); ?></div>
                    <?php //results
                    ?>
                    <div ng-model="models.<?php echo esc_attr($module_id); ?>.added" ui-sortable="sortableOptions" ng-if="models.<?php echo esc_attr($module_id); ?>.added.length" id="<?php echo \esc_attr($module->getId()); ?>" style="max-height: 600px;overflow-y: scroll;padding-right: 15px;">
                        <div class="row egg-hover-row mt-2 pb-2 pt-2" ng-repeat="data in models.<?php echo esc_attr($module_id); ?>.added">
                            <div class="col-md-1 col-xs-12 pe-0 text-center small" id="<?php echo \esc_attr($module->getId()); ?>-{{data.unique_id}}">
                                <img ng-show="data.img" ng-src="{{data.img}}" class="img-thumbnail" style="max-height:75px;" />
                                <div class="mt-1">
                                    <span class="cegg-item-handle bg-light px-2 py-1" title="<?php esc_html_e('Sort', 'content-egg'); ?>">☰</span>
                                    <span style="cursor: copy" class="bg-light px-2 py-1" title="<?php esc_html_e('Insert item ID into shortcode', 'content-egg'); ?>" ng-click="buildShortcode('<?php echo esc_attr($module_id); ?>', selectedTemplate_<?php echo esc_attr($module_id); ?>, selectedGroup_<?php echo esc_attr($module_id); ?>, data.unique_id);">id</span>
                                </div>
                            </div>
                            <div class="col-md-9 col-xs-12">
                                <div class="input-group input-group-sm">
                                    <input style="flex-basis: 35%;" type="text" placeholder="<?php esc_html_e('Title', 'content-egg'); ?>" ng-model="data.title" class="form-control">
                                    <input type="text" placeholder="<?php esc_html_e('Price', 'content-egg'); ?>" ng-model="data.price" class="form-control">
                                    <input type="text" placeholder="<?php esc_html_e('Old Price', 'content-egg'); ?>" ng-model="data.priceOld" class="form-control">
                                    <select class="form-control" ng-model="data.currencyCode" ng-init="data.currencyCode = data.currencyCode || '<?php echo esc_attr($module->getConfigInstance()->option('default_currency')); ?>'">
                                        <?php foreach (\ContentEgg\application\helpers\CurrencyHelper::getCurrenciesList() as $currency) : ?>
                                            <option value="<?php echo \esc_attr($currency); ?>"><?php echo \esc_html($currency); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="input-group input-group-sm mt-1">
                                    <input style="flex-basis: 35%;" type="text" placeholder="<?php esc_html_e('Product URL', 'content-egg'); ?> (<?php esc_html_e('required', 'content-egg'); ?>)" ng-model="data.orig_url" class="form-control">
                                    <input type="text" placeholder="<?php esc_html_e('Merchant name', 'content-egg'); ?>" ng-model="data.merchant" class="form-control">
                                    <input type="text" placeholder="<?php esc_html_e('Domain', 'content-egg'); ?>" ng-model="data.domain" class="form-control">
                                    <input type="text" placeholder="<?php esc_html_e('Rating (e.g., 4.5)', 'content-egg'); ?>" ng-model="data.ratingDecimal" title="<?php esc_html_e('Product Rating (X out of 5)', 'content-egg'); ?>" class="form-control">

                                </div>

                                <div class="input-group input-group-sm mt-1">
                                    <input type="text" placeholder="<?php esc_html_e('Product Image URL', 'content-egg'); ?>" ng-model="data.img" class="form-control">
                                    <input type="text" placeholder="<?php esc_html_e('Merchant Logo URL (optional)', 'content-egg'); ?>" ng-model="data.logo" class="form-control">
                                </div>

                                <div class="input-group input-group-sm mt-1">
                                    <input type="text" placeholder="<?php esc_html_e('Custom XPath Price Selector', 'content-egg'); ?>" ng-model="data.extra.priceXpath" class="form-control">
                                    <input type="text" placeholder="<?php esc_html_e('Custom Deeplink', 'content-egg'); ?>" ng-model="data.extra.deeplink" class="form-control">
                                </div>

                                <div class="input-group input-group-sm mt-1 small text-danger" ng-show="data.extra.last_error">
                                    <span ng-show="data.last_update"><i title="<?php esc_html_e('Last XPath error', 'content-egg'); ?>" class="bi bi-exclamation-triangle"></i> <?php esc_html_e('Last XPath error', 'content-egg'); ?>: {{data.extra.last_error}}</abbr></span>
                                </div>

                                <div class="row mt-1">
                                    <div class="col" ng-if="!isHtmlContent(data.description)">
                                        <textarea type="text" placeholder="<?php esc_html_e('Description', 'content-egg'); ?>" rows="2" ng-model="data.description" class="form-control form-control-sm"></textarea>
                                    </div>
                                    <div class="col" ng-if="isHtmlContent(data.description)">
                                        <textarea ui-tinymce="tinymceOptions" ng-model="data.description"></textarea>
                                    </div>
                                </div>

                                <?php if ($isAffiliateParser) : ?>
                                    <div class="row">
                                        <div class="col mt-1">
                                            <div class="input-group input-group-sm">

                                                <input type="number" min="0" max="999" placeholder="<?php esc_html_e('Order', 'content-egg'); ?>" title="<?php esc_html_e('Order Number: Lower Value - Product Ranks Higher in the List', 'content-egg'); ?>" ng-model="data.order_num" class="form-control">
                                                <input style="flex-basis: 25%;" type="text" placeholder="<?php esc_html_e('Subtitle', 'content-egg'); ?>" title="<?php esc_html_e('Subtitle', 'content-egg'); ?>" ng-model="data.subtitle" class="form-control">
                                                <select class="form-control" ng-model="data.badge_color" title="<?php esc_html_e('Badge Color', 'content-egg'); ?>">
                                                    <option value="">- <?php esc_html_e('Badge color', 'content-egg'); ?> -</option>
                                                    <option value="primary">primary</option>
                                                    <option value="secondary">secondary</option>
                                                    <option value="success">success</option>
                                                    <option value="danger">danger</option>
                                                    <option value="warning">warning</option>
                                                    <option value="info">info</option>
                                                    <option value="light">light</option>
                                                    <option value="dark">dark</option>
                                                </select>
                                                <input type="text" placeholder="<?php esc_html_e('Badge (e.g., Our pick)', 'content-egg'); ?>" title="<?php esc_html_e('Badge Label', 'content-egg'); ?>" ng-model="data.badge" class="form-control">
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($isAffiliateParser) : ?>
                                    <div class="row small p-0 m-0 pt-1">
                                        <div class="col">

                                            <?php if ($is_woo) : ?>
                                                <div class="mt-2 hstack gap-3">
                                                    <label><input ng-true-value="'true'" type="checkbox" ng-model="data.woo_sync" name="woo_sync" ng-change="wooRadioChange(data.unique_id, 'woo_sync')"> <?php esc_html_e('Woo synchronization', 'content-egg'); ?></label>
                                                    <label ng-show="data.features.length">
                                                        <input ng-true-value="'true'" type="checkbox" ng-model="data.woo_attr" name="woo_attr" ng-change="wooRadioChange(data.unique_id, 'woo_attr')"> <?php esc_html_e('Woo attributes', 'content-egg'); ?>: {{data.features.length}}
                                                        <a class="link-dark" data-bs-toggle="collapse" href="#ceggFeatures{{$index}}" role="button" aria-expanded="false" aria-controls="ceggFeatures{{$index}}"><i class="bi bi-pencil-square"></i></a>
                                                    </label>
                                                </div>
                                            <?php else : ?>
                                                <div ng-class="result.ean ? 'mt-2' : 'mt-1'" ng-show="data.features.length">
                                                    <?php esc_html_e('Attributes:', 'content-egg'); ?> {{data.features.length}}
                                                    <a class="text-muted" data-bs-toggle="collapse" href="#ceggFeatures{{$index}}" role="button" aria-expanded="false" aria-controls="ceggFeatures{{$index}}"><i class="bi bi-pencil-square"></i></a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div ng-show="data.ean" class="col-4 p-0 m-0">
                                            <div class="input-group input-group-sm">
                                                <input class="form-control form-control-sm cegg-copy-input" readonly type="text" title="EAN" select-on-click ng-model="data.ean" />
                                                <button class="btn btn-outline-secondary cegg-copy-button" type="button" title="Copy to clipboard"><i class="bi bi-copy"></i></button>
                                            </div>
                                        </div>

                                    </div>
                                <?php endif; ?>

                                <div id="ceggFeatures{{$index}}" ng-show="data.features.length" class="row collapse mt-2 mb-3">
                                    <div class="col-md-12" ng-repeat="feature in data.features">
                                        <div class="input-group input-group-sm">
                                            <input type="text" ng-model="feature.name" class="form-control">
                                            <input type="text" ng-model="feature.value" class="form-control">
                                            <button class="btn btn-outline-secondary" ng-click="data.features.splice($index, 1)" aria-label="Delete">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-2 col-xs-12 border-start small">
                                <span ng-show="data.last_update">
                                    <i ng-show="data.stock_status == 1" class="bi bi-bag-check text-success" title="<?php echo esc_attr('In stock', 'content-egg'); ?>"></i>
                                    <i ng-show=" data.stock_status==-1" class="bi bi-bag-dash-fill text-danger" title="<?php echo esc_attr('Out of stock', 'content-egg'); ?>"></i>
                                    <abbr class="" title="<?php echo esc_attr('Last updated:', 'content-egg'); ?> {{data.last_update * 1000| date:'medium'}}">{{data.last_update * 1000| date:'shortDate'}}</abbr>
                                </span>

                                <button class="btn-close float-end" aria-label="Close" ng-click="delete(data, '<?php echo esc_attr($module_id); ?>')" title="<?php esc_attr('Remove', 'content-egg'); ?>"></button>

                                <div class="small text-mutted mt-2 text-truncate" ng-show="data.url">
                                    <a class="link-dark text-decoration-none" title="<?php echo esc_attr(__('Go to', 'content-egg')); ?>" href="{{data.url}}" target="_blank">
                                        <span ng-show="data.domain"><img src="https://www.google.com/s2/favicons?domain=https://{{data.domain}}"> {{data.domain}}</span><span ng-hide="data.domain"><?php esc_html_e('Go to ', 'content-egg'); ?></span>
                                        <sup><i class="bi bi-box-arrow-up-right"></i></sup>
                                    </a>
                                </div>
                                <?php if ($isAffiliateParser) : ?>
                                    <div ng-show="productGroups.length" class="mt-2">
                                        <select ng-model="data.group" class="form-control form-control-sm">
                                            <option value="">- <?php esc_html_e('Product group', 'content-egg'); ?> -</option>
                                            <option ng-repeat="group in productGroups" ng-value="group">{{group}}</option>
                                        </select>
                                    </div>

                                <?php endif; ?>

                            </div>
                        </div>
                    </div>
                    <?php //.results
                    ?>
                </div>
            </div>

        </div>

    </div>

</div>