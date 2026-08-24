<?php

defined('\ABSPATH') || exit;

use ContentEgg\application\admin\GeneralConfig;
use ContentEgg\application\admin\ShopsController;
use ContentEgg\application\components\ShopCoupon;
use ContentEgg\application\components\ShopStore;

$is_new = $previous_domain === '';
$now = time();
$types = (string) GeneralConfig::getInstance()->option('coupon_types');
$state_labels = ShopsController::stateLabels();

/**
 * Hierarchical public taxonomies only. A coupon targets a section of the site,
 * and a flat tag is not one - offering every tag would bury the categories the
 * operator actually means under hundreds of irrelevant options.
 */
$taxonomies = array();
$terms_by_id = array();
foreach (\get_taxonomies(array('public' => true), 'objects') as $tax)
{
    if (!$tax->hierarchical)
        continue;

    $terms = \get_terms(array('taxonomy' => $tax->name, 'hide_empty' => false));

    if (\is_wp_error($terms) || !$terms)
        continue;

    $taxonomies[$tax->name] = array('label' => $tax->labels->name, 'terms' => $terms);

    foreach ($terms as $term)
        $terms_by_id[(int) $term->term_id] = $term->name;
}

/**
 * The full option list, built once.
 *
 * Rendering it inside every coupon row multiplies the page by the number of
 * rows: a shop with 20 coupons on a store with 5,000 product categories would
 * emit 100,000 <option> elements. Each row instead ships only the terms it has
 * SELECTED - so the value still round-trips with JavaScript off - and the
 * script below injects the rest from this template.
 */
ob_start();
foreach ($taxonomies as $tax_name => $tax)
{
    echo '<optgroup label="' . \esc_attr($tax['label']) . '">';
    foreach ($tax['terms'] as $term)
        echo '<option value="' . (int) $term->term_id . '">' . \esc_html($term->name) . '</option>';
    echo '</optgroup>';
}
$term_options = ob_get_clean();

$warnings = isset($warnings) && is_array($warnings) ? $warnings : array();

// The blank row the JS clones, rendered once with index __i__.
$blank = ShopStore::sanitizeCoupon(array());
$blank['id'] = '';

$rows = $shop['coupons'];
$rows[] = $blank;

?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php echo $is_new ? \esc_html__('Add shop', 'content-egg') : \esc_html__('Edit shop', 'content-egg'); ?>
    </h1>
    <a class="page-title-action" href="<?php echo \esc_url(\admin_url('admin.php?page=' . ShopsController::SLUG)); ?>"><?php \esc_html_e('Back to shops', 'content-egg'); ?></a>
    <hr class="wp-header-end">

    <?php if ($message): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo \esc_html($message); ?></p></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <?php // Escaped at the point it is built, so the "edit that shop" link survives. ?>
        <div class="notice notice-error"><p><?php echo \wp_kses_post($error); ?></p></div>
    <?php endif; ?>
    <?php // Saved, but bound to nothing that will ever render. Shown after the
    // success notice on purpose: the save DID happen, and the warning is about
    // what the coupon will do, not about whether it was stored. ?>
    <?php foreach ($warnings as $warning): ?>
        <div class="notice notice-warning is-dismissible"><p><?php echo \esc_html($warning); ?></p></div>
    <?php endforeach; ?>

    <form method="post" action="<?php echo \esc_url(\admin_url('admin.php?page=' . ShopsController::SLUG_EDIT . ($previous_domain !== '' ? '&domain=' . rawurlencode($previous_domain) : ''))); ?>">
        <?php \wp_nonce_field('cegg_shop_save', 'cegg_shop_nonce'); ?>
        <input type="hidden" name="previous_domain" value="<?php echo \esc_attr($previous_domain); ?>" />

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="cegg-shop-domain"><?php \esc_html_e('Domain', 'content-egg'); ?></label></th>
                <td>
                    <input name="shop[domain]" id="cegg-shop-domain" type="text" class="regular-text ltr"
                        value="<?php echo \esc_attr($shop['domain']); ?>"
                        placeholder="thalia.de"
                        spellcheck="false" autocorrect="off" autocapitalize="off" autocomplete="off" required />
                    <p class="description">
                        <?php \esc_html_e('Paste a product URL or type the domain — either works, and both land on the same shop.', 'content-egg'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="cegg-shop-name"><?php \esc_html_e('Display name', 'content-egg'); ?></label></th>
                <td>
                    <input name="shop[name]" id="cegg-shop-name" type="text" class="regular-text"
                        value="<?php echo \esc_attr($shop['name']); ?>" placeholder="Thalia" />
                    <p class="description"><?php \esc_html_e('Shown instead of the domain in templates and by %MERCHANT%.', 'content-egg'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="cegg-shop-logo"><?php \esc_html_e('Logo', 'content-egg'); ?></label></th>
                <td>
                    <input name="shop[logo]" id="cegg-shop-logo" type="url" class="large-text ltr"
                        value="<?php echo \esc_attr($shop['logo']); ?>" placeholder="https://" />
                    <p>
                        <button type="button" class="button" id="cegg-shop-logo-pick"><?php \esc_html_e('Choose image', 'content-egg'); ?></button>
                        <button type="button" class="button-link" id="cegg-shop-logo-clear"><?php \esc_html_e('Clear', 'content-egg'); ?></button>
                    </p>
                    <p class="description">
                        <?php \esc_html_e('Overrides the logo provider for this shop. Leave empty to keep using the provider.', 'content-egg'); ?>
                    </p>
                    <?php if ($shop['logo'] !== ''): ?>
                        <p><img src="<?php echo \esc_url($shop['logo']); ?>" alt="" style="max-height:48px;max-width:200px" /></p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php \esc_html_e('Shop info', 'content-egg'); ?></th>
                <td>
                    <?php \wp_editor($shop['info'], 'cegg_shop_info', array(
                        'textarea_name' => 'shop[info]',
                        'textarea_rows' => 8,
                        'media_buttons' => false,
                    )); ?>
                    <p class="description"><?php \esc_html_e('Shown in the shop-info popup where a template enables it.', 'content-egg'); ?></p>
                </td>
            </tr>
            <?php if ($shop['coupons_html'] !== ''): ?>
                <tr>
                    <th scope="row"><label for="cegg-shop-coupons-html"><?php \esc_html_e('Coupons HTML', 'content-egg'); ?></label></th>
                    <td>
                        <textarea name="shop[coupons_html]" id="cegg-shop-coupons-html" rows="6" class="large-text code"><?php echo \esc_textarea($shop['coupons_html']); ?></textarea>
                        <p class="description">
                            <strong><?php \esc_html_e('Deprecated.', 'content-egg'); ?></strong>
                            <?php \esc_html_e('Free HTML carried over from the old Shops settings. It still renders in the coupons popup, but it has no code, no dates and no category targeting — add real coupons below instead. Clear this field once you have.', 'content-egg'); ?>
                        </p>
                    </td>
                </tr>
            <?php endif; ?>
        </table>

        <h2><?php \esc_html_e('Coupons', 'content-egg'); ?></h2>
        <p class="description">
            <?php \esc_html_e('The first coupon that applies is shown beside this shop\'s offers. A coupon targeted at the reader\'s category wins over one that is not; otherwise the order below decides.', 'content-egg'); ?>
        </p>

        <div id="cegg-coupons">
            <?php foreach ($rows as $i => $c): ?>
                <?php
                $is_blank = $i === count($rows) - 1;
                $idx = $is_blank ? '__i__' : $i;
                $state = $is_blank ? '' : ShopCoupon::stateOf($c, $now, $types);
                ?>
                <div class="cegg-coupon-row" <?php if ($is_blank): ?>data-cegg-coupon-template style="display:none"<?php endif; ?>>
                    <div class="inside">
                        <input type="hidden" name="shop[coupons][<?php echo \esc_attr($idx); ?>][id]" value="<?php echo \esc_attr($c['id']); ?>" />

                        <div class="cegg-coupon-fields">
                            <div class="cegg-field">
                                <label><?php \esc_html_e('Code', 'content-egg'); ?></label>
                                <input type="text" class="ltr" name="shop[coupons][<?php echo \esc_attr($idx); ?>][code]"
                                    value="<?php echo \esc_attr($c['code']); ?>" placeholder="10%SUMMER"
                                    spellcheck="false" autocapitalize="characters" autocomplete="off" />
                            </div>

                            <div class="cegg-field">
                                <label><?php \esc_html_e('Discount label', 'content-egg'); ?></label>
                                <input type="text" name="shop[coupons][<?php echo \esc_attr($idx); ?>][discount]"
                                    value="<?php echo \esc_attr($c['discount']); ?>" placeholder="10% off" />
                            </div>

                            <div class="cegg-field">
                                <label><?php \esc_html_e('Starts', 'content-egg'); ?></label>
                                <input type="date" name="shop[coupons][<?php echo \esc_attr($idx); ?>][start]"
                                    value="<?php echo \esc_attr(ShopsController::timestampToDate($c['start'])); ?>" />
                            </div>

                            <div class="cegg-field">
                                <label><?php \esc_html_e('Ends', 'content-egg'); ?></label>
                                <input type="date" name="shop[coupons][<?php echo \esc_attr($idx); ?>][end]"
                                    value="<?php echo \esc_attr(ShopsController::timestampToDate($c['end'])); ?>" />
                            </div>

                            <?php // Renamed from "Description" now that there is a real one:
                                  // this is stored as `title` and is the offer headline. ?>
                            <div class="cegg-field cegg-field-wide">
                                <label><?php \esc_html_e('Title', 'content-egg'); ?></label>
                                <input type="text" name="shop[coupons][<?php echo \esc_attr($idx); ?>][title]"
                                    value="<?php echo \esc_attr($c['title']); ?>"
                                    placeholder="<?php \esc_attr_e('10% off video games', 'content-egg'); ?>" />
                                <span class="cegg-field-help"><?php \esc_html_e('Shown on hover, and in place of the code for a deal.', 'content-egg'); ?></span>
                            </div>

                            <div class="cegg-field cegg-field-wide">
                                <label><?php \esc_html_e('Description', 'content-egg'); ?></label>
                                <input type="text" class="large-text" name="shop[coupons][<?php echo \esc_attr($idx); ?>][description]"
                                    value="<?php echo \esc_attr($c['description']); ?>"
                                    placeholder="<?php \esc_attr_e('Excludes sale items. One use per customer.', 'content-egg'); ?>" />
                                <span class="cegg-field-help"><?php \esc_html_e('Optional. Shown under the title on coupon cards.', 'content-egg'); ?></span>
                            </div>

                            <div class="cegg-field cegg-field-wide">
                                <label><?php \esc_html_e('Image', 'content-egg'); ?></label>
                                <input type="url" class="ltr cegg-coupon-image" name="shop[coupons][<?php echo \esc_attr($idx); ?>][image]"
                                    value="<?php echo \esc_attr($c['image']); ?>" placeholder="https://" />
                                <span class="cegg-field-help">
                                    <?php \esc_html_e('Optional. Shown on coupon cards.', 'content-egg'); ?>
                                    <button type="button" class="button-link cegg-coupon-image-pick"><?php \esc_html_e('Choose image', 'content-egg'); ?></button>
                                </span>
                            </div>

                            <div class="cegg-field cegg-field-wide">
                                <label><?php \esc_html_e('Link', 'content-egg'); ?></label>
                                <input type="url" class="ltr" name="shop[coupons][<?php echo \esc_attr($idx); ?>][link]"
                                    value="<?php echo \esc_attr($c['link']); ?>" placeholder="https://" />
                                <span class="cegg-field-help"><?php \esc_html_e('Optional. Empty uses the offer\'s own affiliate link, which is what keeps the click tracked.', 'content-egg'); ?></span>
                            </div>

                            <?php // Both fields answer the same question - where does this
                                  // coupon apply - so they share a row rather than stacking as
                                  // two full-width blocks. Wrapped rather than sized inline, or
                                  // the Link field above would float up beside one of them. ?>
                            <div class="cegg-coupon-scope">
                                <div class="cegg-field cegg-field-terms">
                                    <label><?php \esc_html_e('Only on posts in these categories', 'content-egg'); ?></label>
                                    <select name="shop[coupons][<?php echo \esc_attr($idx); ?>][terms][]" multiple size="5"
                                        data-cegg-terms="<?php echo \esc_attr(implode(',', $c['terms'])); ?>">
                                        <?php foreach ($c['terms'] as $term_id): ?>
                                            <option value="<?php echo (int) $term_id; ?>" selected><?php echo \esc_html(isset($terms_by_id[(int) $term_id]) ? $terms_by_id[(int) $term_id] : '#' . (int) $term_id); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="cegg-field-help">
                                        <?php \esc_html_e('Nothing selected means every post. A parent covers its children.', 'content-egg'); ?>
                                        <?php // Deselecting the last item in a native multi-select needs a
                                        // Ctrl/Cmd-click, which nobody discovers. Without this the coupon
                                        // is stuck on whatever category was picked first. ?>
                                        <button type="button" class="button-link cegg-terms-clear" hidden><?php \esc_html_e('Apply to all categories', 'content-egg'); ?></button>
                                    </span>
                                </div>

                                <div class="cegg-field cegg-field-products">
                                    <label><?php \esc_html_e('Only on these products', 'content-egg'); ?></label>
                                    <textarea class="ltr" name="shop[coupons][<?php echo \esc_attr($idx); ?>][products]" rows="2"
                                        placeholder='[{"title":"...","product_ref":{"module_id":"...","unique_id":"..."}}]'><?php
                                        echo \esc_textarea($c['products'] ? \wp_json_encode($c['products']) : '');
                                    ?></textarea>
                                    <?php // The parse rendered back. A bad paste is this feature's most
                                    // likely mistake and its most silent one - a coupon bound to nothing
                                    // renders nowhere and says nothing - so the row shows what it understood. ?>
                                    <div class="cegg-product-chips">
                                        <?php foreach ($c['products'] as $p): ?>
                                            <span class="cegg-product-chip">
                                                <?php // The ids alone when there is no product name to
                                                      // show, rather than the id twice over. ?>
                                                <?php if ($p['label'] !== ''): ?>
                                                    <?php echo \esc_html($p['label']); ?>
                                                <?php endif; ?>
                                                <code><?php echo \esc_html($p['module_id'] . ' · ' . $p['unique_id']); ?></code>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <span class="cegg-field-help">
                                        <?php \esc_html_e('Nothing here means every product from this shop. Paste a reference copied from the products modal ("Copy product reference").', 'content-egg'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="cegg-coupon-footer">
                            <label>
                                <input type="checkbox" name="shop[coupons][<?php echo \esc_attr($idx); ?>][enabled]" value="1" <?php \checked($c['enabled']); ?> />
                                <?php \esc_html_e('Enabled', 'content-egg'); ?>
                            </label>
                            <?php if ($state && isset($state_labels[$state])): ?>
                                <span class="cegg-coupon-state cegg-coupon-state-<?php echo \esc_attr($state); ?>"><?php echo \wp_kses_post($state_labels[$state]); ?></span>
                            <?php endif; ?>
                            <button type="button" class="button-link delete cegg-coupon-remove"><?php \esc_html_e('Remove', 'content-egg'); ?></button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <p><button type="button" class="button" id="cegg-coupon-add"><?php \esc_html_e('+ Add coupon', 'content-egg'); ?></button></p>

        <template id="cegg-term-options"><?php echo $term_options; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above from esc_html/esc_attr ?></template>

        <?php \submit_button($is_new ? __('Add shop', 'content-egg') : __('Save shop', 'content-egg')); ?>
    </form>
</div>

<style>
    /*
     * Explicit card styling rather than leaning on .postbox: common.css gives
     * .postbox only a font-size, and its background and border live in
     * edit.css - which never loads on an admin.php page. Relying on it left
     * every coupon row flat and borderless.
     */
    .cegg-coupon-row {
        background: #fff;
        border: 1px solid #c3c4c7;
        box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
        margin-bottom: 12px;
    }

    .cegg-coupon-row .inside { padding: 12px 14px; margin: 0; }

    /*
     * A grid, not inline labels. Each field owns a column, so a long label can
     * never wrap onto the line of the field before it.
     */
    .cegg-coupon-fields {
        display: flex;
        flex-wrap: wrap;
        gap: 12px 16px;
        align-items: flex-start;
    }

    .cegg-field {
        display: flex;
        flex-direction: column;
        flex: 0 0 auto;
    }

    .cegg-field > label {
        font-weight: 600;
        font-size: 12px;
        line-height: 1.6;
        margin-bottom: 2px;
    }

    .cegg-field > input,
    .cegg-field > select {
        margin: 0;
        min-width: 12em;
    }

    .cegg-field-wide { flex: 1 1 22em; }
    .cegg-field-wide > input { width: 100%; }

    .cegg-coupon-scope {
        flex: 1 1 100%;
        display: flex;
        flex-wrap: wrap;
        gap: 12px 16px;
        align-items: flex-start;
    }

    /* Equal columns that fall to one per line when the screen cannot hold two. */
    .cegg-coupon-scope > .cegg-field { flex: 1 1 22em; min-width: 0; }

    .cegg-field-products > textarea { width: 100%; font-family: Consolas, Monaco, monospace; font-size: 12px; }
    .cegg-product-chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .cegg-product-chips:empty { display: none; }
    .cegg-product-chip { background: #f0f0f1; border-radius: 3px; padding: 2px 8px; font-size: 12px; }
    .cegg-product-chip code { background: none; font-size: 11px; opacity: .7; }
    .cegg-field-terms > select { width: 100%; }

    .cegg-field-help {
        font-size: 12px;
        color: #646970;
        margin-top: 3px;
    }

    .cegg-terms-clear { font-size: 12px; }
    .cegg-terms-clear[hidden] { display: none; }

    .cegg-coupon-footer {
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 12px 0 0;
        padding-top: 10px;
        border-top: 1px solid #f0f0f1;
    }

    .cegg-coupon-footer label { display: flex; align-items: center; gap: 4px; margin: 0; }
    .cegg-coupon-footer .cegg-coupon-remove { margin-left: auto; }

    .cegg-coupon-state { font-size: 12px; padding: 2px 8px; border-radius: 10px; background: #f0f0f1; }
    .cegg-coupon-state-live { background: #d7f5dd; }
    .cegg-coupon-state-expired { background: #fbe2e2; }
    .cegg-coupon-state-scheduled { background: #fcf3d7; }
    .cegg-coupon-state-hidden_deal { background: #e5e5e5; }
</style>

<script type="text/javascript">
    (function () {
        var wrap = document.getElementById('cegg-coupons');
        if (!wrap || wrap.dataset.ceggInit) return;
        wrap.dataset.ceggInit = '1';

        var template = wrap.querySelector('[data-cegg-coupon-template]');
        var next = wrap.querySelectorAll('.cegg-coupon-row').length;

        document.getElementById('cegg-coupon-add').addEventListener('click', function (e) {
            e.preventDefault();

            var row = template.cloneNode(true);
            row.removeAttribute('data-cegg-coupon-template');
            row.style.display = '';
            // Every name carries __i__; a fresh index keeps rows from
            // overwriting each other on submit.
            row.innerHTML = row.innerHTML.split('__i__').join(String(next++));
            wrap.appendChild(row);
            row.querySelectorAll('select[data-cegg-terms]').forEach(function (s) {
                delete s.dataset.ceggFilled;
                fillTerms(s);
                syncClear(s);
            });
        });

        wrap.addEventListener('click', function (e) {
            if (!e.target.classList || !e.target.classList.contains('cegg-coupon-remove')) return;
            e.preventDefault();

            var row = e.target.closest('.cegg-coupon-row');
            if (!row || row.hasAttribute('data-cegg-coupon-template')) return;

            // Clearing the code and description is what actually drops the row:
            // prepareSubmission() skips any row with neither.
            row.querySelectorAll('input[type=text], input[type=url]').forEach(function (i) { i.value = ''; });
            row.remove();
        });

        // Each row shipped only its selected terms so the value survives with
        // JavaScript off. Fill in the rest from the single template, then
        // re-apply the selection.
        var optionsTpl = document.getElementById('cegg-term-options');

        function fillTerms(select) {
            if (!optionsTpl || select.dataset.ceggFilled) return;
            select.dataset.ceggFilled = '1';

            var chosen = (select.getAttribute('data-cegg-terms') || '')
                .split(',').filter(function (v) { return v !== ''; });

            select.innerHTML = '';
            select.appendChild(optionsTpl.content.cloneNode(true));

            chosen.forEach(function (id) {
                var opt = select.querySelector('option[value="' + id + '"]');
                if (opt) opt.selected = true;
            });
        }

        // Deliberately skips the hidden template row. Filling it would bake the
        // whole option list into the string that gets cloned, putting the size
        // problem straight back.
        function fillAll(root) {
            root.querySelectorAll('.cegg-coupon-row:not([data-cegg-coupon-template]) select[data-cegg-terms]')
                .forEach(fillTerms);
        }

        fillAll(wrap);

        // The clear button only exists while there is something to clear, so it
        // never invites a click that would do nothing.
        function syncClear(select) {
            var help = select.parentNode.querySelector('.cegg-terms-clear');
            if (!help) return;
            help.hidden = select.selectedOptions.length === 0;
        }

        function syncAllClears(root) {
            root.querySelectorAll('select[data-cegg-terms]').forEach(syncClear);
        }

        syncAllClears(wrap);

        wrap.addEventListener('change', function (e) {
            if (e.target.matches && e.target.matches('select[data-cegg-terms]')) syncClear(e.target);
        });

        wrap.addEventListener('click', function (e) {
            if (!e.target.classList || !e.target.classList.contains('cegg-terms-clear')) return;
            e.preventDefault();

            var select = e.target.closest('.cegg-field').querySelector('select[data-cegg-terms]');
            if (!select) return;

            Array.prototype.forEach.call(select.options, function (o) { o.selected = false; });
            syncClear(select);
        });

        // One picker for every coupon row, opened against whichever row was
        // clicked - a picker per row would build dozens of media frames.
        var couponPicker;

        wrap.addEventListener('click', function (e) {
            if (!e.target.classList || !e.target.classList.contains('cegg-coupon-image-pick')) return;
            e.preventDefault();

            var field = e.target.closest('.cegg-field').querySelector('.cegg-coupon-image');
            if (!field) return;

            if (!couponPicker) {
                // library.type is not cosmetic: without it the frame offers
                // every attachment, and picking a ZIP or a PDF stores its file
                // URL in an image field. The card then renders a broken image,
                // or - worse - a real one, since WordPress hands back its own
                // grey placeholder for a mime type it has no icon for and that
                // placeholder loads with a 200, so the template's onerror
                // guard never fires.
                couponPicker = wp.media({
                    title: '<?php echo \esc_js(__('Choose a coupon image', 'content-egg')); ?>',
                    button: { text: '<?php echo \esc_js(__('Use this image', 'content-egg')); ?>' },
                    library: { type: 'image' },
                    multiple: false
                });
                couponPicker.on('select', function () {
                    if (couponPicker.cegg_target)
                        couponPicker.cegg_target.value = couponPicker.state().get('selection').first().toJSON().url;
                });
            }

            couponPicker.cegg_target = field;
            couponPicker.open();
        });

        var logoField = document.getElementById('cegg-shop-logo');
        var picker;

        document.getElementById('cegg-shop-logo-pick').addEventListener('click', function (e) {
            e.preventDefault();

            if (!picker) {
                picker = wp.media({
                    title: '<?php echo \esc_js(__('Choose a logo', 'content-egg')); ?>',
                    button: { text: '<?php echo \esc_js(__('Use this image', 'content-egg')); ?>' },
                    library: { type: 'image' },
                    multiple: false
                });
                picker.on('select', function () {
                    logoField.value = picker.state().get('selection').first().toJSON().url;
                });
            }

            picker.open();
        });

        document.getElementById('cegg-shop-logo-clear').addEventListener('click', function (e) {
            e.preventDefault();
            logoField.value = '';
        });
    })();
</script>
