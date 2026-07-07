<?php
defined('\ABSPATH') || exit;

$ae_ok     = \ContentEgg\application\admin\AeIntegrationConfig::isAEIntegrationPosible();
$custom_ok = \ContentEgg\application\admin\AeIntegrationConfig::isCustomDomainSupported();
$get_ae    = \ContentEgg\application\admin\AeIntegrationConfig::getAffiliateEggUrl('connect_modal');
$disabled  = $ae_ok ? '' : ' disabled';
?>
<div id="cegg-ae-modal" class="cegg-ae-modal" role="presentation" style="display:none;">
    <div class="cegg-ae-modal__backdrop" onclick="ceggAeModalClose()"></div>
    <div class="cegg-ae-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="cegg-ae-modal-title" aria-describedby="cegg-ae-modal-sub">
        <button type="button" class="cegg-ae-modal__close" aria-label="<?php esc_attr_e('Close', 'content-egg'); ?>" onclick="ceggAeModalClose()">&times;</button>

        <div class="cegg-ae-modal__head">
            <span class="cegg-ae-modal__icon" aria-hidden="true"><i class="bi bi-shop"></i></span>
            <div>
                <h2 id="cegg-ae-modal-title" class="cegg-ae-modal__title"><?php esc_html_e('Connect a store', 'content-egg'); ?></h2>
                <p id="cegg-ae-modal-sub" class="cegg-ae-modal__sub"><?php esc_html_e('Enter a store\'s domain and Content Egg pulls its products through Affiliate Egg — price, image, stock and more. No API or feed.', 'content-egg'); ?></p>
            </div>
        </div>

        <?php if (!$ae_ok) : ?>
            <div class="cegg-ae-note cegg-ae-note--warn">
                <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                <span><?php esc_html_e('This uses the Affiliate Egg plugin to fetch product data. Install and activate it to connect stores.', 'content-egg'); ?></span>
            </div>
        <?php elseif (!$custom_ok) : ?>
            <div class="cegg-ae-note cegg-ae-note--info">
                <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                <span><?php printf(esc_html__('Custom domains require Affiliate Egg %s or newer. Registered shops work now.', 'content-egg'), esc_html(\ContentEgg\application\admin\AeIntegrationConfig::MIN_AE_VERSION_CUSTOM_DOMAIN)); ?></span>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="cegg_ae_connect" />
            <?php wp_nonce_field('cegg_ae_connect'); ?>

            <div class="cegg-ae-modal__body">
                <div class="cegg-ae-field">
                    <label for="cegg-ae-domain"><?php esc_html_e('Shop domain', 'content-egg'); ?></label>
                    <input type="text" id="cegg-ae-domain" name="domain" placeholder="example.com" autocomplete="off" spellcheck="false" required<?php echo $disabled; ?> />
                </div>
                <div class="cegg-ae-field">
                    <label for="cegg-ae-search"><?php esc_html_e('Search URL', 'content-egg'); ?> <span class="cegg-ae-opt"><?php esc_html_e('optional', 'content-egg'); ?></span></label>
                    <input type="text" id="cegg-ae-search" name="search_uri" placeholder="https://example.com/search?q=%KEYWORD%" autocomplete="off" spellcheck="false"<?php echo $disabled; ?> />
                    <p class="cegg-ae-help"><?php
                        echo wp_kses(
                            sprintf(
                                /* translators: %s is the %KEYWORD% placeholder shown in a <code> tag. */
                                __('Enter the store\'s search URL. Use %s as the query placeholder to enable keyword-based product search. Otherwise, only direct product and category URLs are supported.', 'content-egg'),
                                '<code>%KEYWORD%</code>'
                            ),
                            array('code' => array())
                        );
                    ?></p>
                </div>
            </div>

            <div class="cegg-ae-modal__foot">
                <button type="button" class="button cegg-ae-btn" onclick="ceggAeModalClose()"><?php esc_html_e('Cancel', 'content-egg'); ?></button>
                <?php if ($ae_ok) : ?>
                    <button type="submit" class="button button-primary cegg-ae-btn"><?php esc_html_e('Connect', 'content-egg'); ?></button>
                <?php else : ?>
                    <a class="button button-primary cegg-ae-btn" href="<?php echo esc_url($get_ae); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Get Affiliate Egg', 'content-egg'); ?> &rarr;</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
<style>
.cegg-ae-modal{position:fixed;inset:0;z-index:100000;align-items:flex-start;justify-content:center;padding:8vh 16px 24px;overflow-y:auto;}
.cegg-ae-modal__backdrop{position:fixed;inset:0;background:rgba(12,16,20,.55);backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px);}
.cegg-ae-modal__dialog{position:relative;width:100%;max-width:480px;background:#fff;border-radius:12px;box-shadow:0 16px 48px -12px rgba(0,0,0,.35),0 4px 12px rgba(0,0,0,.10);animation:ceggAeIn .16s ease-out;}
@keyframes ceggAeIn{from{opacity:0;transform:translateY(8px) scale(.985)}to{opacity:1;transform:none}}
.cegg-ae-modal__close{position:absolute;top:12px;right:12px;width:32px;height:32px;padding:0;border:0;background:transparent;color:#787c82;font-size:22px;line-height:1;border-radius:6px;cursor:pointer;}
.cegg-ae-modal__close:hover{background:#f0f0f1;color:#1d2327;}
.cegg-ae-modal__close:focus-visible{outline:2px solid #2271b1;outline-offset:1px;}
.cegg-ae-modal__head{display:flex;gap:12px;padding:24px 48px 4px 24px;}
.cegg-ae-modal__icon{flex:0 0 auto;width:40px;height:40px;border-radius:9px;background:#f0f6fc;color:#2271b1;display:flex;align-items:center;justify-content:center;font-size:19px;}
.cegg-ae-modal__title{margin:3px 0 0;padding:0;font-size:17px;font-weight:600;line-height:1.3;color:#1d2327;}
.cegg-ae-modal__sub{margin:5px 0 0;font-size:13px;line-height:1.5;color:#646970;}
.cegg-ae-note{display:flex;gap:8px;margin:12px 24px 0;padding:10px 12px;border-radius:8px;font-size:13px;line-height:1.5;}
.cegg-ae-note .bi{flex:0 0 auto;margin-top:1px;font-size:14px;}
.cegg-ae-note--warn{background:#fcf5e9;color:#8a6d1a;}
.cegg-ae-note--info{background:#eef4fb;color:#1d5a8a;}
.cegg-ae-modal__body{padding:18px 24px 4px;}
.cegg-ae-field{margin-bottom:16px;}
.cegg-ae-field>label{display:block;margin-bottom:6px;font-size:13px;font-weight:600;color:#1d2327;}
.cegg-ae-opt{margin-left:5px;font-weight:400;font-size:12px;color:#8c8f94;}
.cegg-ae-field input[type=text]{width:100%;box-sizing:border-box;margin:0;padding:9px 12px;font-size:14px;line-height:1.4;color:#1d2327;background:#fff;border:1px solid #8c8f94;border-radius:7px;box-shadow:none;transition:border-color .1s ease,box-shadow .1s ease;}
.cegg-ae-field input[type=text]::placeholder{color:#a7aaad;}
.cegg-ae-field input[type=text]:focus{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1;outline:2px solid transparent;}
.cegg-ae-field input[type=text]:disabled{background:#f6f7f7;color:#8c8f94;border-color:#dcdcde;}
.cegg-ae-help{margin:10px 0 0;font-size:12.5px;line-height:1.5;color:#646970;}
.cegg-ae-help code{font-size:12px;color:#d63384;background:#f0f0f1;padding:2px 6px;border-radius:4px;}
.cegg-ae-modal__foot{display:flex;justify-content:flex-end;gap:8px;padding:16px 24px 22px;margin-top:6px;border-top:1px solid #f0f0f1;}
.cegg-ae-modal__foot .cegg-ae-btn{border-radius:7px;}
@media (prefers-reduced-motion:reduce){.cegg-ae-modal__dialog{animation:none}}
@media (max-width:600px){.cegg-ae-modal{padding:16px;}.cegg-ae-modal__head{padding-right:44px;}}
</style>
<script>
function ceggAeModalOpen(){
    var m=document.getElementById('cegg-ae-modal');
    if(!m)return;
    m.style.display='flex';
    document.addEventListener('keydown',ceggAeModalEsc);
    var f=document.getElementById('cegg-ae-domain');
    if(f&&!f.disabled){setTimeout(function(){f.focus();},60);}
}
function ceggAeModalClose(){
    var m=document.getElementById('cegg-ae-modal');
    if(!m)return;
    m.style.display='none';
    document.removeEventListener('keydown',ceggAeModalEsc);
}
function ceggAeModalEsc(e){if(e.key==='Escape'||e.keyCode===27){ceggAeModalClose();}}
</script>
