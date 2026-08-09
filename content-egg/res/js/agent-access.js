/* Content Egg — "AI Agents" (Agent Access) admin screen */
(function () {
    'use strict';

    var cfg = window.ceggAgentAccess || {};
    var i18n = cfg.i18n || {};

    document.addEventListener('DOMContentLoaded', function () {
        initToggle();
        initCopyButtons();
        initBasicToken();
        initGuideText();
        initTabs();
    });

    /* ---- "How to connect" tabs: one assistant visible at a time ---- */
    function initTabs() {
        var list = document.querySelector('.cegg-aa__tabs');
        if (!list) {
            return;
        }
        var tabs = [].slice.call(list.querySelectorAll('.cegg-aa__tab'));

        function select(tab) {
            tabs.forEach(function (t) {
                var on = t === tab;
                var panel = document.getElementById(t.getAttribute('aria-controls'));
                t.classList.toggle('is-active', on);
                t.setAttribute('aria-selected', on ? 'true' : 'false');
                t.tabIndex = on ? 0 : -1;
                if (panel) {
                    panel.hidden = !on;
                }
            });
        }

        list.addEventListener('click', function (e) {
            var tab = e.target.closest ? e.target.closest('.cegg-aa__tab') : null;
            if (tab) {
                select(tab);
            }
        });

        // Arrow-key navigation, as the tablist role implies.
        list.addEventListener('keydown', function (e) {
            var dir = e.key === 'ArrowRight' ? 1 : (e.key === 'ArrowLeft' ? -1 : 0);
            var i = tabs.indexOf(document.activeElement);
            if (!dir || i < 0) {
                return;
            }
            e.preventDefault();
            var next = tabs[(i + dir + tabs.length) % tabs.length];
            select(next);
            next.focus();
        });
    }

    /* ---- "Copy guide text": fetch the guide endpoint, copy its markdown ---- */
    function initGuideText() {
        // Bound by class, not id: the guide row is repeated per assistant panel.
        [].slice.call(document.querySelectorAll('.cegg-aa__guide-text')).forEach(bindGuideText);
    }

    function bindGuideText(btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var url = btn.getAttribute('data-guide-url') || '';
            if (!url) {
                return;
            }
            btn.disabled = true;
            fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var md = data && typeof data.markdown === 'string' ? data.markdown : '';
                    if (!md) {
                        throw new Error('empty');
                    }
                    return copyText(md).then(function () { showCopied(btn); });
                })
                .catch(function () {
                    // Fall back to opening the guide so the user can copy it by hand.
                    window.open(url, '_blank', 'noopener');
                })
                .then(function () { btn.disabled = false; });
        });
    }

    /* ---- Basic-auth token helper: base64(user:pass), entirely in-browser ---- */
    function initBasicToken() {
        var user = document.getElementById('cegg-aa-user');
        var pass = document.getElementById('cegg-aa-pass');
        var out = document.getElementById('cegg-aa-token');
        var copy = document.getElementById('cegg-aa-token-copy');
        if (!user || !pass || !out) {
            return;
        }

        function update() {
            var u = (user.value || '').trim();
            // WordPress ignores spaces in application passwords; strip all
            // whitespace so the canonical token is produced either way.
            var p = (pass.value || '').replace(/\s+/g, '');
            var token = '';
            if (u && p) {
                try {
                    // UTF-8-safe base64 (btoa is Latin1-only on its own).
                    token = btoa(unescape(encodeURIComponent(u + ':' + p)));
                } catch (e) {
                    token = '';
                }
            }
            out.value = token;
            if (copy) {
                copy.setAttribute('data-copy', token);
            }
        }

        user.addEventListener('input', update);
        pass.addEventListener('input', update);
        update();
    }

    /* ---- Master switch: save over REST, no page reload ---- */
    function initToggle() {
        var toggle = document.getElementById('cegg-aa-toggle');
        var wrap = document.querySelector('.cegg-aa');
        var save = document.getElementById('cegg-aa-save');
        var count = document.getElementById('cegg-aa-count');
        if (!toggle || !wrap) {
            return;
        }

        toggle.addEventListener('change', function () {
            var enabled = toggle.checked;
            toggle.disabled = true;
            setSave(save, '', false);

            fetch(cfg.restUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': cfg.nonce
                },
                body: JSON.stringify({ enabled: enabled })
            })
                .then(function (r) {
                    if (!r.ok) {
                        throw new Error('http ' + r.status);
                    }
                    return r.json();
                })
                .then(function (data) {
                    wrap.classList.toggle('is-on', !!data.enabled);
                    wrap.classList.toggle('is-off', !data.enabled);
                    wrap.setAttribute('data-enabled', data.enabled ? '1' : '0');
                    if (count && typeof data.ability_count !== 'undefined') {
                        count.textContent = data.ability_count;
                    }
                    setSave(save, i18n.saved || 'Saved', false);
                })
                .catch(function () {
                    // revert on failure
                    toggle.checked = !enabled;
                    setSave(save, i18n.saveError || 'Could not save', true);
                })
                .then(function () {
                    toggle.disabled = false;
                });
        });
    }

    function setSave(el, text, isError) {
        if (!el) {
            return;
        }
        el.textContent = text;
        el.classList.toggle('is-error', !!isError);
        if (text && !isError) {
            window.clearTimeout(el._t);
            el._t = window.setTimeout(function () {
                el.textContent = '';
            }, 2000);
        }
    }

    /* ---- Copy-to-clipboard buttons ---- */
    function initCopyButtons() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.cegg-copy') : null;
            if (!btn) {
                return;
            }
            e.preventDefault();
            var text = btn.getAttribute('data-copy') || '';
            copyText(text).then(function () {
                showCopied(btn);
            });
        });
    }

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text).catch(function () {
                return legacyCopy(text);
            });
        }
        return legacyCopy(text);
    }

    function legacyCopy(text) {
        return new Promise(function (resolve) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); } catch (err) {}
            document.body.removeChild(ta);
            resolve();
        });
    }

    function showCopied(btn) {
        // .is-copied swaps the clipboard icon to a green check via CSS. The
        // label text is left unchanged so the button width doesn't jump.
        btn.classList.add('is-copied');
        window.clearTimeout(btn._t);
        btn._t = window.setTimeout(function () {
            btn.classList.remove('is-copied');
        }, 1500);
    }
})();
