<?php
defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\components\ModuleManager;

$module = ModuleManager::factory($module_id);
$is_woo = (\get_post_type($GLOBALS['post']->ID) == 'product') ? true : false;
$isAffiliateParser = $module->isAffiliateParser();
if ($isAffiliateParser && $module->isProductParser())
    $isProductParser = true;
else
    $isProductParser = false;

$ai_key = GeneralConfig::getInstance()->option('ai_key');
$prompt1 = GeneralConfig::getInstance()->option('prompt1');
$prompt2 = GeneralConfig::getInstance()->option('prompt2');
$prompt3 = GeneralConfig::getInstance()->option('prompt3');
$prompt4 = GeneralConfig::getInstance()->option('prompt4');

?>
<?php if ($isProductParser && $ai_key) : ?>
    <div class="row mt-2 cegg-ai-tools" ng-if="models['<?php echo esc_attr($module_id); ?>'].added.length">

        <div class="col-md-12 d-flex align-middle">
            <div class="dropdown">
                <button class="btn btn-outline-info btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span ng-show="aiProcessingTitle['<?php echo esc_attr($module_id); ?>']" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    <i class="bi bi-magic"></i>
                    <?php esc_html_e('Titles', 'content-egg'); ?>
                    <span ng-show="selected['<?php echo esc_attr($module_id); ?>'] > 0">({{selectedCount('<?php echo esc_attr($module_id); ?>')}})</span>
                    <span ng-show="selected['<?php echo esc_attr($module_id); ?>'] == 0">(<?php esc_html_e('all', 'content-egg'); ?>)</span>
                </button>
                <ul class="dropdown-menu">
                    <?php if ($prompt1) : ?><li class="small m-0"><a ng-click="ai('<?php echo esc_attr($module_id); ?>', 'prompt1', '')" class="dropdown-item"><?php echo esc_html(sprintf(__('Custom prompt #%d', 'content-egg'), 1)); ?></a></li><?php endif; ?>
                    <?php if ($prompt2) : ?><li class="small m-0"><a ng-click="ai('<?php echo esc_attr($module_id); ?>', 'prompt2', '')" class="dropdown-item"><?php echo esc_html(sprintf(__('Custom prompt #%d', 'content-egg'), 2)); ?></a></li><?php endif; ?>
                    <?php if ($prompt3) : ?><li class="small m-0"><a ng-click="ai('<?php echo esc_attr($module_id); ?>', 'prompt3', '')" class="dropdown-item"><?php echo esc_html(sprintf(__('Custom prompt #%d', 'content-egg'), 3)); ?></a></li><?php endif; ?>
                    <?php if ($prompt4) : ?><li class="small m-0"><a ng-click="ai('<?php echo esc_attr($module_id); ?>', 'prompt4', '')" class="dropdown-item"><?php echo esc_html(sprintf(__('Custom prompt #%d', 'content-egg'), 4)); ?></a></li><?php endif; ?>
                    <li class="small m-0"><a ng-click="ai('<?php echo esc_attr($module_id); ?>', 'shorten', '')" class="dropdown-item"><?php esc_html_e('Shorten', 'content-egg'); ?></a></li>
                    <li class="small m-0"><a ng-click="ai('<?php echo esc_attr($module_id); ?>', 'rephrase', '')" class="dropdown-item"><?php esc_html_e('Rephrase', 'content-egg'); ?></a></li>
                    <li class="small m-0"><a ng-click="ai('<?php echo esc_attr($module_id); ?>', 'translate', '')" class="dropdown-item"><?php esc_html_e('Translate', 'content-egg'); ?></a></li>
                </ul>
            </div>
            <div class="dropdown ms-1">
                <button class="btn btn-outline-info btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span ng-show="aiProcessingDescription['<?php echo esc_attr($module_id); ?>']" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    <i class="bi bi-magic"></i>
                    <?php esc_html_e('Descriptions', 'content-egg'); ?>
                    <span ng-show="selected['<?php echo esc_attr($module_id); ?>'] > 0">({{selectedCount('<?php echo esc_attr($module_id); ?>')}})</span>
                    <span ng-show="selected['<?php echo esc_attr($module_id); ?>'] == 0">(<?php esc_html_e('all', 'content-egg'); ?>)</span>
                </button>
                <ul class="dropdown-menu">
                    <?php if ($prompt1) : ?><li class="small m-0"><a ng-click="ai('<?php echo esc_attr($module_id); ?>', '', 'prompt1')" class="dropdown-item"><?php echo esc_html(sprintf(__('Custom prompt #%d', 'content-egg'), 1)); ?></a></li><?php endif; ?>
                    <?php if ($prompt2) : ?><li class="small m-0"><a ng-click="ai('<?php echo esc_attr($module_id); ?>', '', 'prompt2')" class="dropdown-item"><?php echo esc_html(sprintf(__('Custom prompt #%d', 'content-egg'), 2)); ?></a></li><?php endif; ?>
                    <?php if ($prompt3) : ?><li class="small m-0"><a ng-click="ai('<?php echo esc_attr($module_id); ?>', '', 'prompt3')" class="dropdown-item"><?php echo esc_html(sprintf(__('Custom prompt #%d', 'content-egg'), 3)); ?></a></li><?php endif; ?>
                    <?php if ($prompt4) : ?><li class="small m-0"><a ng-click="ai('<?php echo esc_attr($module_id); ?>', '', 'prompt4')" class="dropdown-item"><?php echo esc_html(sprintf(__('Custom prompt #%d', 'content-egg'), 4)); ?></a></li><?php endif; ?>
                    <li class="small m-0"><a ng-click="ai('<?php echo esc_attr($module_id); ?>', '', 'rewrite')" class="dropdown-item"><?php esc_html_e('Rewrite', 'content-egg'); ?></a></li>
                    <li class="small m-0"><a ng-click="ai('<?php echo esc_attr($module_id); ?>', '', 'paraphrase')" class="dropdown-item"><?php esc_html_e('Paraphrase', 'content-egg'); ?></a></li>
                    <li class="small m-0"><a ng-click="ai('<?php echo esc_attr($module_id); ?>', '', 'translate')" class="dropdown-item"><?php esc_html_e('Translate', 'content-egg'); ?></a></li>
                    <li class="small m-0"><a ng-click="ai('<?php echo esc_attr($module_id); ?>', '', 'summarize')" class="dropdown-item"><?php esc_html_e('Summarize', 'content-egg'); ?></a></li>
                    <li class="small m-0"><a ng-click="ai('<?php echo esc_attr($module_id); ?>', '', 'bullet_points')" class="dropdown-item"><?php esc_html_e('Bullet points', 'content-egg'); ?></a></li>
                    <li class="small m-0"><a ng-click="ai('<?php echo esc_attr($module_id); ?>', '', 'write_review')" class="dropdown-item"><?php esc_html_e('Write a review', 'content-egg'); ?></a></li>
                    <li class="small m-0"><a ng-click="ai('<?php echo esc_attr($module_id); ?>', '', 'write_article')" class="dropdown-item"><?php esc_html_e('Write an article', 'content-egg'); ?></a></li>
                    <li class="small m-0"><a ng-click="ai('<?php echo esc_attr($module_id); ?>', '', 'write_buyers_guide')" class="dropdown-item"><?php esc_html_e('Write a buyer\'s guide', 'content-egg'); ?></a></li>
                    <li class="small m-0"><a ng-click="ai('<?php echo esc_attr($module_id); ?>', '', 'write_paragraphs')" class="dropdown-item"><?php esc_html_e('Write a few paragraphs', 'content-egg'); ?></a></li>
                    <li class="small m-0"><a ng-click="ai('<?php echo esc_attr($module_id); ?>', '', 'craft_description')" class="dropdown-item"><?php esc_html_e('Craft a product description', 'content-egg'); ?></a></li>
                    <li class="small m-0"><a ng-click="ai('<?php echo esc_attr($module_id); ?>', '', 'write_how_to_use')" class="dropdown-item"><?php esc_html_e('Write a how to use instruction', 'content-egg'); ?></a></li>
                    <li class="small m-0"><a ng-click="ai('<?php echo esc_attr($module_id); ?>', '', 'turn_into_advertising')" class="dropdown-item"><?php esc_html_e('Turn into advertising', 'content-egg'); ?></a></li>
                    <li class="small m-0"><a ng-click="ai('<?php echo esc_attr($module_id); ?>', '', 'cta_text')" class="dropdown-item"><?php esc_html_e('Generate CTA text', 'content-egg'); ?></a></li>
                </ul>
            </div>

            <button ng-disabled="!models['<?php echo esc_attr($module_id); ?>'].undo.length || aiProcessingDescription['<?php echo esc_attr($module_id); ?>'] || aiProcessingTitle['<?php echo esc_attr($module_id); ?>']" ng-click="aiUndo('<?php echo esc_attr($module_id); ?>')" type="button" class="ms-2 btn btn-sm btn-outline-info" title="<?php echo esc_attr('Undo', 'content-egg'); ?>"><i class="bi bi-arrow-counterclockwise"></i></button>
        </div>
        <div class="col-md-12 text-danger small mt-2" ng-show="models.<?php echo esc_attr($module_id); ?>.aiError">
            {{models.<?php echo esc_attr($module_id); ?>.aiError}}
        </div>
    </div>
<?php endif; ?>
<div ng-model="models.<?php echo esc_attr($module_id); ?>.added" ui-sortable="sortableOptions" ng-if="models.<?php echo esc_attr($module_id); ?>.added.length" id="<?php echo \esc_attr($module->getId()); ?>" style="max-height: 600px;overflow-y: scroll;padding-right: 15px;">
    <div class="row egg-hover-row mt-2 pb-2 pt-2" ng-repeat="data in models.<?php echo esc_attr($module_id); ?>.added">
        <div class="col-md-1 col-xs-12 pe-0 text-center small">
            <img ng-show="data.img" ng-src="{{data.img}}" class="img-thumbnail" style="max-height:75px;" />
            <div class="mt-1">
                <span class="cegg-item-handle bg-light px-2 py-1" title="<?php esc_html_e('Sort', 'content-egg'); ?>">☰</span>
                <span style="cursor: copy" class="bg-light px-2 py-1" title="<?php esc_html_e('Insert item ID into shortcode', 'content-egg'); ?>" ng-click="buildShortcode('<?php echo esc_attr($module_id); ?>', selectedTemplate_<?php echo esc_attr($module_id); ?>, selectedGroup_<?php echo esc_attr($module_id); ?>, data.unique_id);">id</span>
            </div>
        </div>
        <div class="col-md-9 col-xs-12">
            <div class="input-group input-group-sm mb-1">
                <input <?php if ($isAffiliateParser) echo ' style="flex-basis: 40%;"'; ?> type="text" placeholder="<?php esc_html_e('Title', 'content-egg'); ?>" ng-model="data.title" class="form-control">
                <?php if ($isAffiliateParser) : ?>
                    <input type="text" placeholder="<?php esc_html_e('Merchant name', 'content-egg'); ?>" ng-model="data.merchant" class="form-control">
                    <input type="text" placeholder="<?php esc_html_e('Domain', 'content-egg'); ?>" ng-model="data.domain" class="form-control">
                    <input type="text" placeholder="<?php esc_html_e('Price', 'content-egg'); ?>" ng-model="data.price" class="form-control">
                <?php endif; ?>
            </div>

            <div class="row">
                <?php if ($isAffiliateParser) : ?>
                    <div class="col" ng-if="!isHtmlContent(data.description)">
                        <textarea type="text" placeholder="<?php esc_html_e('Description', 'content-egg'); ?>" rows="2" ng-model="data.description" class="form-control form-control-sm"></textarea>
                    </div>
                    <div class="col" ng-if="isHtmlContent(data.description)">
                        <textarea ui-tinymce="tinymceOptions" ng-model="data.description"></textarea>
                    </div>
                <?php else : ?>
                    <div class="col">
                        <textarea type="text" placeholder="<?php esc_html_e('Description', 'content-egg'); ?>" rows="2" ng-model="data.description" class="form-control form-control-sm"></textarea>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($isAffiliateParser) : ?>
                <div class="row small p-0 m-0 pt-1">
                    <div class="col ms-0 ps-0">
                        <div class=" hstack gap-3">
                            <?php if ($ai_key) : ?>
                                <div class="btn-group" role="group" aria-label="Basic example">
                                    <input ng-model="data._selected" type="checkbox" class="btn-check" id="{{data.unique_id}}" autocomplete="off">
                                    <label title="<?php echo esc_attr('Apply AI to this product', 'content-egg'); ?>" class="btn btn-outline-info btn-sm cegg-btn-xs" for="{{data.unique_id}}"><i class="bi bi-magic"></i></label>
                                    <button title="<?php esc_html_e('Copy the description shortcode', 'content-egg'); ?>" type="button" class="btn btn-sm btn-outline-info cegg-btn-xs" ng-click="copyDescriptionShortcode('<?php echo esc_attr($module_id); ?>', data.unique_id, $event)"><i class="bi bi-code-square"></i></span></button>
                                </div>
                            <?php endif; ?>

                            <?php if ($is_woo) : ?>
                                <label><input ng-true-value="'true'" type="checkbox" ng-model="data.woo_sync" name="woo_sync" ng-change="wooRadioChange(data.unique_id, 'woo_sync')"> <?php esc_html_e('Woo synchronization', 'content-egg'); ?></label>
                                <label ng-show="data.features.length">
                                    <input ng-true-value="'true'" type="checkbox" ng-model="data.woo_attr" name="woo_attr" ng-change="wooRadioChange(data.unique_id, 'woo_attr')"> <?php esc_html_e('Woo attributes', 'content-egg'); ?>: {{data.features.length}}
                                    <a class="link-dark" data-bs-toggle="collapse" href="#ceggFeatures{{$index}}" role="button" aria-expanded="false" aria-controls="ceggFeatures{{$index}}"><i class="bi bi-pencil-square"></i></a>
                                </label>
                            <?php else : ?>
                                <div ng-show="data.features.length">
                                    <?php esc_html_e('Attributes:', 'content-egg'); ?> {{data.features.length}}
                                    <a class="text-muted" data-bs-toggle="collapse" href="#ceggFeatures{{$index}}" role="button" aria-expanded="false" aria-controls="ceggFeatures{{$index}}"><i class="bi bi-pencil-square"></i></a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div ng-show="data.ean" class="col-3 p-0 m-0">
                        <div class="input-group input-group-sm">
                            <input class="form-control form-control-sm" readonly type="text" title="EAN" select-on-click ng-model="data.ean" />
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
                <abbr data-bs-toggle="tooltip" title="<?php echo esc_attr('Last updated:', 'content-egg'); ?> {{data.last_update * 1000| date:'medium'}}">{{data.last_update * 1000| date:'shortDate'}}</abbr>
            </span>

            <button class="btn-close float-end" aria-label="Close" ng-click="delete(data, '<?php echo esc_attr($module_id); ?>')" title="<?php esc_attr('Remove', 'content-egg'); ?>"></button>

            <div class="small text-mutted mt-2 text-truncate">
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