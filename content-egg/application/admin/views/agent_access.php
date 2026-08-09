<?php

defined('\ABSPATH') || exit;

/**
 * "AI Agents" (Agent Access) admin page.
 *
 * Variables (via PluginAdmin::render): $supported, $enabled, $ability_groups,
 * $ability_count, $is_paid, $mcp_present, $mcp_active, $mcp_endpoint,
 * $openapi_url, $guide_url, $profile_url, $docs_url, $log_rows.
 */

$state_class = $enabled ? 'is-on' : 'is-off';

// Bootstrap-icons. Every copy button prints a "rest" glyph (--copy) plus the
// shared check (--done); CSS swaps them on .is-copied. Icon-only buttons carry
// a title/aria-label instead of visible text. Static markup — safe to echo raw.
$check_ico = '<svg class="cegg-copy__ico cegg-copy__ico--done" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path fill-rule="evenodd" d="M10.854 7.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0"/><path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1z"/><path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5zm-3-1A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0z"/></svg>';

$clip_ico = '<svg class="cegg-copy__ico cegg-copy__ico--copy" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1z"/><path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5zm-3-1A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0z"/></svg>';

// file-text — the guide's "copy guide text" glyph (the only button that isn't
// a plain clipboard copy).
$doc_ico = '<svg class="cegg-copy__ico cegg-copy__ico--copy" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M5 4a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1zm-.5 2.5A.5.5 0 0 1 5 6h6a.5.5 0 0 1 0 1H5a.5.5 0 0 1-.5-.5M5 8a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1zm0 2a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1z"/><path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2zm10-1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V2a1 1 0 0 0-1-1"/></svg>';

$copy_icon      = $clip_ico . $check_ico;   // generic clipboard copy (all "Copy link" buttons)
$guidetext_icon = $doc_ico . $check_ico;    // copy guide text
?>
<?php
/* The agent guide's copy row, identical in every assistant panel: the URL, a
   "copy the text" button (fetches the guide and copies its markdown — see
   agent-access.js) and a plain "copy the link" button. Which one an assistant
   wants differs, so both are offered everywhere and the row's subtitle names
   the one that applies. Built once, echoed per panel. */
ob_start();
?>
<div class="cegg-aa__link-copy">
    <input type="text" class="cegg-aa__url" readonly value="<?php echo esc_url($guide_url); ?>" onfocus="this.select()">
    <button type="button" class="button cegg-copy--icon cegg-aa__guide-text" data-guide-url="<?php echo esc_url($guide_url); ?>" title="<?php esc_attr_e('Copy guide text', 'content-egg'); ?>" aria-label="<?php esc_attr_e('Copy agent guide text', 'content-egg'); ?>">
        <?php echo $guidetext_icon; ?>
    </button>
    <button type="button" class="button cegg-copy cegg-copy--icon" data-copy="<?php echo esc_attr($guide_url); ?>" title="<?php esc_attr_e('Copy link', 'content-egg'); ?>" aria-label="<?php esc_attr_e('Copy agent guide link', 'content-egg'); ?>">
        <?php echo $copy_icon; ?>
    </button>
</div>
<?php
$guide_copy_row = ob_get_clean();
?>
<div class="wrap cegg-aa <?php echo esc_attr($state_class); ?>" data-enabled="<?php echo $enabled ? '1' : '0'; ?>">

    <h1 class="cegg-aa__title">
        <?php esc_html_e('AI Agents', 'content-egg'); ?>
        <span class="cegg-aa__beta"><?php esc_html_e('Beta', 'content-egg'); ?></span>
    </h1>
    <p class="cegg-aa__lead">
        <?php esc_html_e('Connect your own AI assistant — ChatGPT, Claude, or another — and let it run Content Egg for you: update settings, find products, build pages from blocks, and more.', 'content-egg'); ?>
    </p>

    <?php if (!$supported): ?>
        <div class="notice notice-warning">
            <p><?php esc_html_e('Agent Access requires WordPress 6.9 or newer. Please update WordPress to use these features.', 'content-egg'); ?></p>
        </div>
    <?php endif; ?>

    <?php /* ---------- Status hero + master switch ---------- */ ?>
    <div class="cegg-aa__hero">
        <label class="cegg-switch" title="<?php esc_attr_e('Enable Agent Access', 'content-egg'); ?>">
            <input type="checkbox" id="cegg-aa-toggle" <?php checked($enabled); ?> <?php disabled(!$supported); ?>>
            <span class="cegg-switch__track"><span class="cegg-switch__thumb"></span></span>
            <span class="screen-reader-text"><?php esc_html_e('Enable Agent Access', 'content-egg'); ?></span>
        </label>

        <div class="cegg-aa__hero-text">
            <strong class="cegg-aa__state">
                <span class="cegg-aa__on-text"><?php esc_html_e('Agent Access is on', 'content-egg'); ?></span>
                <span class="cegg-aa__off-text"><?php esc_html_e('Agent Access is off', 'content-egg'); ?></span>
            </strong>
            <span class="cegg-aa__hero-sub">
                <span class="cegg-aa__on-text"><?php esc_html_e('Your assistant can connect and work on your site.', 'content-egg'); ?></span>
                <span class="cegg-aa__off-text"><?php esc_html_e('Turn it on to let an assistant connect.', 'content-egg'); ?></span>
            </span>
        </div>

        <div class="cegg-aa__hero-meta cegg-aa__on-text">
            <span class="cegg-aa__count" id="cegg-aa-count"><?php echo (int) $ability_count; ?></span>
            <span><?php esc_html_e('abilities ready', 'content-egg'); ?></span>
        </div>

        <span class="cegg-aa__save" id="cegg-aa-save" role="status" aria-live="polite"></span>
    </div>

    <?php /* ---------- Connect your assistant ---------- */ ?>
    <div class="cegg-aa__card cegg-aa__gated">
        <div class="cegg-aa__card-head">
            <h2><?php esc_html_e('Connect your assistant', 'content-egg'); ?></h2>
            <a class="cegg-aa__extlink" href="<?php echo esc_url($docs_url); ?>" target="_blank" rel="noopener">
                <?php esc_html_e('Full guide', 'content-egg'); ?><span class="dashicons dashicons-external"></span>
            </a>
        </div>

        <ol class="cegg-aa__steps">
            <li>
                <span class="cegg-aa__stepn">1</span>
                <span><?php esc_html_e('Turn on Agent Access (the switch above).', 'content-egg'); ?></span>
            </li>
            <li>
                <span class="cegg-aa__stepn">2</span>
                <span>
                    <?php esc_html_e('Create an application password for your assistant.', 'content-egg'); ?>
                    <a class="cegg-aa__steplink" href="<?php echo esc_url($profile_url); ?>"><?php esc_html_e('Create one', 'content-egg'); ?> &rarr;</a>
                </span>
            </li>
            <li>
                <span class="cegg-aa__stepn">3</span>
                <span><?php esc_html_e('Set up your assistant — pick yours below.', 'content-egg'); ?></span>
            </li>
        </ol>

        <?php
        /* ---------- One tab per assistant ----------
        Organised by assistant, not by artifact: a Claude user should not have to
        work out that the ChatGPT profile isn't theirs. The agent guide row is
        repeated in every panel on purpose — it is the one artifact every assistant
        needs, and a single shared row outside the tabs would break the
        self-contained "do these steps" reading of each panel. */

        // ChatGPT caps Custom GPT Actions at 30 operations and can't call the core
        // GET endpoint, so the chatgpt profile is a trimmed, all-POST spec against
        // the proxy. The Full profile is every operation, for other HTTP clients.
        $openapi_chatgpt_url = add_query_arg('profile', 'chatgpt', $openapi_url);
        $site_host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
        ?>
        <div class="cegg-aa__how">
            <div class="cegg-aa__tabs" role="tablist" aria-label="<?php esc_attr_e('Choose your assistant', 'content-egg'); ?>">
                <button type="button" class="cegg-aa__tab is-active" role="tab" id="cegg-aa-tab-chatgpt" aria-controls="cegg-aa-panel-chatgpt" aria-selected="true"><?php esc_html_e('ChatGPT', 'content-egg'); ?></button>
                <button type="button" class="cegg-aa__tab" role="tab" id="cegg-aa-tab-claude" aria-controls="cegg-aa-panel-claude" aria-selected="false" tabindex="-1"><?php esc_html_e('Claude', 'content-egg'); ?></button>
                <button type="button" class="cegg-aa__tab" role="tab" id="cegg-aa-tab-other" aria-controls="cegg-aa-panel-other" aria-selected="false" tabindex="-1"><?php esc_html_e('Other tools', 'content-egg'); ?></button>
            </div>

            <?php /* ---- ChatGPT ---- */ ?>
            <div class="cegg-aa__panel" id="cegg-aa-panel-chatgpt" role="tabpanel" aria-labelledby="cegg-aa-tab-chatgpt">
                <div class="cegg-aa__panel-head">
                    <div class="cegg-aa__tool-desc">
                        <?php esc_html_e('Create a Custom GPT, paste the guide text into its Instructions, then import this action and set Authentication to Basic.', 'content-egg'); ?>
                    </div>
                    <a class="cegg-aa__tool-link" href="<?php echo esc_url($docs_url . '/connect-chatgpt'); ?>" target="_blank" rel="noopener">
                        <?php esc_html_e('Step by step', 'content-egg'); ?><span class="dashicons dashicons-external"></span>
                    </a>
                </div>
                <ul class="cegg-aa__links">
                    <li class="cegg-aa__link">
                        <div class="cegg-aa__link-info">
                            <span class="cegg-aa__link-name"><?php esc_html_e('Agent guide', 'content-egg'); ?></span>
                            <?php // Custom GPT Instructions boxes never fetch a URL, so ChatGPT users want the text. ?>
                            <span class="cegg-aa__link-for"><?php esc_html_e('Copy the text, paste it into your GPT’s Instructions', 'content-egg'); ?></span>
                        </div>
                        <?php echo $guide_copy_row; ?>
                    </li>

                    <li class="cegg-aa__link">
                        <div class="cegg-aa__link-info">
                            <span class="cegg-aa__link-name"><?php esc_html_e('OpenAPI — ChatGPT profile', 'content-egg'); ?></span>
                            <span class="cegg-aa__link-for"><?php esc_html_e('Trimmed to fit ChatGPT’s action limit', 'content-egg'); ?></span>
                        </div>
                        <div class="cegg-aa__link-copy">
                            <input type="text" class="cegg-aa__url" readonly value="<?php echo esc_url($openapi_chatgpt_url); ?>" onfocus="this.select()">
                            <button type="button" class="button cegg-copy cegg-copy--icon" data-copy="<?php echo esc_attr($openapi_chatgpt_url); ?>" title="<?php esc_attr_e('Copy link', 'content-egg'); ?>" aria-label="<?php esc_attr_e('Copy ChatGPT OpenAPI URL', 'content-egg'); ?>">
                                <?php echo $copy_icon; ?>
                            </button>
                        </div>
                    </li>
                </ul>

                <?php /* Basic-auth token helper — encodes entirely in the browser (see agent-access.js); nothing is sent to the server. */ ?>
                <details class="cegg-aa__token">
                    <summary class="cegg-aa__token-summary">
                        <?php esc_html_e('Make a Basic auth token', 'content-egg'); ?>
                    </summary>
                    <div class="cegg-aa__token-body">
                        <p class="cegg-aa__token-note">
                            <span class="dashicons dashicons-lock"></span>
                            <?php esc_html_e('Paste this under Authentication → API Key → Auth Type: Basic. The token is equivalent to your password: treat it the same and don’t share it.', 'content-egg'); ?>
                        </p>
                        <div class="cegg-aa__token-fields">
                            <label class="cegg-aa__token-field">
                                <span><?php esc_html_e('WordPress username', 'content-egg'); ?></span>
                                <input type="text" id="cegg-aa-user" value="<?php echo esc_attr(wp_get_current_user()->user_login); ?>" autocomplete="off" autocapitalize="off" spellcheck="false">
                            </label>
                            <label class="cegg-aa__token-field">
                                <span><?php esc_html_e('Application password', 'content-egg'); ?></span>
                                <input type="text" id="cegg-aa-pass" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx" autocomplete="off" autocapitalize="off" spellcheck="false">
                            </label>
                        </div>
                        <div class="cegg-aa__link-copy">
                            <input type="text" id="cegg-aa-token" class="cegg-aa__url" readonly placeholder="<?php esc_attr_e('Your Basic auth token appears here', 'content-egg'); ?>" onfocus="this.select()">
                            <button type="button" class="button cegg-copy cegg-copy--icon" id="cegg-aa-token-copy" data-copy="" title="<?php esc_attr_e('Copy', 'content-egg'); ?>" aria-label="<?php esc_attr_e('Copy Basic auth token', 'content-egg'); ?>">
                                <?php echo $copy_icon; ?>
                            </button>
                        </div>
                    </div>
                </details>
            </div>

            <?php /* ---- Claude ---- */ ?>
            <div class="cegg-aa__panel" id="cegg-aa-panel-claude" role="tabpanel" aria-labelledby="cegg-aa-tab-claude" hidden>
                <div class="cegg-aa__panel-head">
                    <div class="cegg-aa__tool-desc">
                        <?php
                        printf(
                            /* translators: %s: this site's domain */
                            wp_kses(__('Open Settings → Capabilities and add <code>%s</code> to the allowed domains. Then paste this link into a chat, together with your username and application password.', 'content-egg'), array('code' => array())),
                            esc_html($site_host)
                        );
                        ?>
                    </div>
                    <a class="cegg-aa__tool-link" href="<?php echo esc_url($docs_url . '/connect-claude'); ?>" target="_blank" rel="noopener">
                        <?php esc_html_e('Step by step', 'content-egg'); ?><span class="dashicons dashicons-external"></span>
                    </a>
                </div>
                <ul class="cegg-aa__links">
                    <li class="cegg-aa__link">
                        <div class="cegg-aa__link-info">
                            <span class="cegg-aa__link-name"><?php esc_html_e('Agent guide', 'content-egg'); ?></span>
                            <span class="cegg-aa__link-for"><?php esc_html_e('Paste this link into the chat', 'content-egg'); ?></span>
                        </div>
                        <?php echo $guide_copy_row; ?>
                    </li>
                </ul>

                <?php /* Claude Desktop MCP config — pre-filled with this site's endpoint + username (server-side). */ ?>
                <?php if ($mcp_active):
                    $mcp_config = wp_json_encode(array(
                        'mcpServers' => array(
                            'content-egg' => array(
                                'command' => 'npx',
                                'args'    => array('-y', '@automattic/mcp-wordpress-remote@latest'),
                                'env'     => array(
                                    'WP_API_URL'      => $mcp_endpoint,
                                    'WP_API_USERNAME' => wp_get_current_user()->user_login,
                                    'WP_API_PASSWORD' => 'your-application-password',
                                ),
                            ),
                        ),
                    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    ?>
                    <details class="cegg-aa__token">
                        <summary class="cegg-aa__token-summary">
                            <?php esc_html_e('Permanent connection for Claude Desktop (MCP)', 'content-egg'); ?>
                        </summary>
                        <div class="cegg-aa__token-body">
                            <p class="cegg-aa__token-note">
                                <span class="dashicons dashicons-info-outline"></span>
                                <?php esc_html_e('Optional, and needs Node.js. Your password lives in a file on your computer instead of the chat. In Claude Desktop → Settings → Developer → Edit Config, paste this and replace “your-application-password”.', 'content-egg'); ?>
                            </p>
                            <div class="cegg-aa__code-row">
                                <pre class="cegg-aa__code"><?php echo esc_html($mcp_config); ?></pre>
                                <button type="button" class="button cegg-copy cegg-copy--icon" data-copy="<?php echo esc_attr($mcp_config); ?>" title="<?php esc_attr_e('Copy', 'content-egg'); ?>" aria-label="<?php esc_attr_e('Copy Claude Desktop config', 'content-egg'); ?>">
                                    <?php echo $copy_icon; ?>
                                </button>
                            </div>
                        </div>
                    </details>
                <?php endif; ?>
            </div>

            <?php /* ---- Everything else ---- */ ?>
            <div class="cegg-aa__panel" id="cegg-aa-panel-other" role="tabpanel" aria-labelledby="cegg-aa-tab-other" hidden>
                <div class="cegg-aa__panel-head">
                    <div class="cegg-aa__tool-desc">
                        <?php esc_html_e('Claude Code, Cursor and your own scripts need no setup — give them the guide link plus your username and application password. Anything that imports an API spec can use the full profile.', 'content-egg'); ?>
                    </div>
                    <a class="cegg-aa__tool-link" href="<?php echo esc_url($docs_url . '/connect-other'); ?>" target="_blank" rel="noopener">
                        <?php esc_html_e('Step by step', 'content-egg'); ?><span class="dashicons dashicons-external"></span>
                    </a>
                </div>
                <ul class="cegg-aa__links">
                    <li class="cegg-aa__link">
                        <div class="cegg-aa__link-info">
                            <span class="cegg-aa__link-name"><?php esc_html_e('Agent guide', 'content-egg'); ?></span>
                            <span class="cegg-aa__link-for"><?php esc_html_e('Paste this link into your tool', 'content-egg'); ?></span>
                        </div>
                        <?php echo $guide_copy_row; ?>
                    </li>

                    <li class="cegg-aa__link">
                        <div class="cegg-aa__link-info">
                            <span class="cegg-aa__link-name"><?php esc_html_e('OpenAPI — Full profile', 'content-egg'); ?></span>
                            <span class="cegg-aa__link-for"><?php esc_html_e('Every operation, for HTTP clients & code generators', 'content-egg'); ?></span>
                        </div>
                        <div class="cegg-aa__link-copy">
                            <input type="text" class="cegg-aa__url" readonly value="<?php echo esc_url($openapi_url); ?>" onfocus="this.select()">
                            <button type="button" class="button cegg-copy cegg-copy--icon" data-copy="<?php echo esc_attr($openapi_url); ?>" title="<?php esc_attr_e('Copy link', 'content-egg'); ?>" aria-label="<?php esc_attr_e('Copy full OpenAPI URL', 'content-egg'); ?>">
                                <?php echo $copy_icon; ?>
                            </button>
                        </div>
                    </li>

                    <li class="cegg-aa__link">
                        <div class="cegg-aa__link-info">
                            <span class="cegg-aa__link-name"><?php esc_html_e('MCP endpoint', 'content-egg'); ?></span>
                            <span class="cegg-aa__link-for"><?php esc_html_e('Optional — for Cursor and other MCP apps', 'content-egg'); ?></span>
                        </div>
                        <?php if ($mcp_active): ?>
                            <div class="cegg-aa__link-copy">
                                <input type="text" class="cegg-aa__url" readonly value="<?php echo esc_url($mcp_endpoint); ?>" onfocus="this.select()">
                                <button type="button" class="button cegg-copy cegg-copy--icon" data-copy="<?php echo esc_attr($mcp_endpoint); ?>" title="<?php esc_attr_e('Copy link', 'content-egg'); ?>" aria-label="<?php esc_attr_e('Copy MCP endpoint', 'content-egg'); ?>">
                                    <?php echo $copy_icon; ?>
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="cegg-aa__link-note">
                                <span class="dashicons dashicons-info-outline"></span>
                                <?php // One span, or the flex row would break the sentence apart at the link. ?>
                                <span>
                                    <?php
                                    printf(
                                        /* translators: %s: MCP Adapter GitHub releases URL */
                                        wp_kses(__('Only if you want one: add the free <a href="%s" target="_blank" rel="noopener">WordPress MCP Adapter</a> under Plugins → Add New → Upload Plugin.', 'content-egg'), array('a' => array('href' => array(), 'target' => array(), 'rel' => array()))),
                                        'https://github.com/WordPress/mcp-adapter/releases'
                                    );
                                    ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <?php /* ---------- What your assistant can do ---------- */ ?>
    <div class="cegg-aa__card">
        <details class="cegg-aa__abilities">
            <summary class="cegg-aa__summary">
                <span class="cegg-aa__summary-title">
                    <?php esc_html_e('What your assistant can do', 'content-egg'); ?>
                    <span class="cegg-aa__pill"><?php echo (int) $ability_count; ?></span>
                </span>
                <span class="cegg-aa__summary-hint"><?php esc_html_e('Ask in your own words — tap to see examples', 'content-egg'); ?></span>
                <?php if (!$is_paid): ?>
                    <a class="cegg-aa__gopro"
                       href="<?php echo esc_url(\ContentEgg\application\Plugin::pluginPricingUrl('ce_agent_access', 'what_agent_can_do')); ?>"
                       target="_blank" rel="noopener noreferrer"
                       onclick="event.stopPropagation();">
                        <?php esc_html_e('Go Pro', 'content-egg'); ?>
                        <span class="dashicons dashicons-external"></span>
                    </a>
                <?php endif; ?>
            </summary>

            <div class="cegg-aa__abilities-body">
                <?php if (!$is_paid): ?>
                    <p class="cegg-aa__pronote">
                        <span class="dashicons dashicons-lock"></span>
                        <?php esc_html_e('Your free version includes every read-only ability. Pro unlocks building pages and editing products, modules, and settings — shown locked below.', 'content-egg'); ?>
                    </p>
                <?php endif; ?>

                <?php foreach ($ability_groups as $group): ?>
                    <div class="cegg-aa__group">
                        <h3 class="cegg-aa__group-title"><?php echo esc_html($group['title']); ?></h3>
                        <div class="cegg-aa__grid">
                            <?php foreach ($group['abilities'] as $a): ?>
                                <div class="cegg-aa__ability <?php echo $a['available'] ? '' : 'is-locked'; ?>">
                                    <div class="cegg-aa__ability-head">
                                        <span class="cegg-aa__ability-label"><?php echo esc_html($a['label']); ?></span>
                                        <?php if (!$is_paid): ?>
                                            <?php if ($a['tier'] === 'pro'): ?>
                                                <span class="cegg-aa__tier cegg-aa__tier--pro"><?php esc_html_e('Pro', 'content-egg'); ?></span>
                                            <?php else: ?>
                                                <span class="cegg-aa__tier cegg-aa__tier--free"><?php esc_html_e('Free', 'content-egg'); ?></span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if (!empty($a['chatgpt_excluded'])): ?>
                                            <span class="cegg-aa__no-gpt" title="<?php esc_attr_e('Not in the ChatGPT Actions profile (ChatGPT caps imports at 30 operations). Still available via Claude, MCP and REST.', 'content-egg'); ?>"><?php esc_html_e('Not in ChatGPT', 'content-egg'); ?></span>
                                        <?php endif; ?>
                                        <?php if (!$a['available']): ?>
                                            <span class="dashicons dashicons-lock cegg-aa__ability-lock" title="<?php esc_attr_e('Requires Pro', 'content-egg'); ?>"></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="cegg-aa__ability-desc"><?php echo esc_html($a['desc']); ?></div>
                                    <div class="cegg-aa__ability-eg">
                                        <code>&ldquo;<?php echo esc_html($a['example']); ?>&rdquo;</code>
                                        <button type="button" class="button-link cegg-copy cegg-copy--mini" data-copy="<?php echo esc_attr($a['example']); ?>" title="<?php esc_attr_e('Copy example', 'content-egg'); ?>">
                                            <?php echo $copy_icon; ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>
    </div>

    <?php /* ---------- Recent activity ---------- */ ?>
    <div class="cegg-aa__card">
        <div class="cegg-aa__card-head">
            <h2><?php esc_html_e('Recent activity', 'content-egg'); ?></h2>
        </div>

        <?php if (!$log_rows): ?>
            <p class="cegg-aa__empty">
                <?php esc_html_e('Nothing yet. Once your assistant does something, it will show up here — every action is logged.', 'content-egg'); ?>
            </p>
        <?php else: ?>
            <table class="cegg-aa__log">
                <thead>
                    <tr>
                        <th><?php esc_html_e('When', 'content-egg'); ?></th>
                        <th><?php esc_html_e('User', 'content-egg'); ?></th>
                        <th><?php esc_html_e('Action', 'content-egg'); ?></th>
                        <th><?php esc_html_e('Result', 'content-egg'); ?></th>
                        <th><?php esc_html_e('Time', 'content-egg'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($log_rows as $row): ?>
                        <?php
                        $ts = strtotime($row['created_at'] . ' UTC');
                        $ok = ($row['status'] === 'ok');
                        $user = get_userdata((int) $row['user_id']);
                        ?>
                        <tr>
                            <td title="<?php echo esc_attr($row['created_at'] . ' UTC'); ?>">
                                <?php echo esc_html($ts ? sprintf(__('%s ago', 'content-egg'), human_time_diff($ts)) : $row['created_at']); ?>
                            </td>
                            <td><?php echo esc_html($user ? $user->user_login : ('#' . (int) $row['user_id'])); ?></td>
                            <td><code class="cegg-aa__act"><?php echo esc_html(str_replace('content-egg/', '', $row['ability'])); ?></code></td>
                            <td>
                                <span class="cegg-aa__result <?php echo $ok ? 'is-ok' : 'is-err'; ?>">
                                    <?php echo esc_html($ok ? __('ok', 'content-egg') : $row['code']); ?>
                                </span>
                            </td>
                            <td><?php echo (int) $row['duration_ms']; ?> ms</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</div>
