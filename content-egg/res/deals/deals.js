/* Content Egg deals: voting and countdowns. No dependencies. */
(function () {
    'use strict';

    var cfg = window.ceggDeals || { rest: '', nonce: '', i18n: {} };

    function left(seconds) {
        var d = Math.floor(seconds / 86400);
        var h = Math.floor((seconds % 86400) / 3600);
        var m = Math.floor((seconds % 3600) / 60);
        return (d ? d + 'd ' : '') + h + 'h ' + m + 'm';
    }

    function tick() {
        var now = Date.now() / 1000;
        document.querySelectorAll('.cegg-deal-countdown[data-ends]').forEach(function (el) {
            var remaining = parseInt(el.getAttribute('data-ends'), 10) - now;
            el.textContent = remaining > 0 ? cfg.i18n.endsIn + ' ' + left(remaining) : cfg.i18n.ended;
        });
    }

    function lock(box) {
        box.setAttribute('data-voted', '1');
        box.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
    }

    function vote(box, dir) {
        var headers = { 'Content-Type': 'application/json' };
        if (cfg.nonce) {
            headers['X-WP-Nonce'] = cfg.nonce;
        }
        box.classList.add('is-busy');
        fetch(cfg.rest + box.getAttribute('data-deal-id') + '/vote', {
            method: 'POST',
            credentials: 'same-origin',
            headers: headers,
            body: JSON.stringify({ dir: dir })
        })
            .then(function (r) {
                return r.json().then(function (body) { return { status: r.status, ok: r.ok, body: body }; });
            })
            .then(function (res) {
                if (res.ok) {
                    // Success: show the new score and lock, same as an already-recorded vote.
                    box.querySelector('.cegg-deal-votes__score').textContent = res.body.score;
                    lock(box);
                } else if (res.status === 409) {
                    // Already voted, or the deal closed under us: nothing left to do here either.
                    box.title = (res.body && res.body.message) ? res.body.message : cfg.i18n.error;
                    lock(box);
                } else {
                    // Rate-limited, login-required, voting disabled, not found, ... : recoverable,
                    // so leave the buttons enabled and just explain why it didn't work this time.
                    box.title = (res.body && res.body.message) ? res.body.message : cfg.i18n.error;
                }
            })
            .catch(function () { box.title = cfg.i18n.error; })
            .then(function () { box.classList.remove('is-busy'); });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('.cegg-deal-votes__btn') : null;
        if (!btn) {
            return;
        }
        var box = btn.closest('.cegg-deal-votes');
        if (!box || box.hasAttribute('data-voted') || box.hasAttribute('data-closed') || box.classList.contains('is-busy')) {
            return;
        }
        vote(box, btn.getAttribute('data-dir'));
    });

    function copyCode(btn) {
        var code = btn.getAttribute('data-code') || '';
        var done = function () {
            if (!btn.getAttribute('data-label')) {
                btn.setAttribute('data-label', btn.textContent);
            }
            btn.textContent = cfg.i18n.copied || btn.getAttribute('data-label');
            setTimeout(function () { btn.textContent = btn.getAttribute('data-label'); }, 2000);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(code).then(done, function () {});
            return;
        }
        // Fallback for plain-http sites: a throwaway textarea and execCommand.
        var ta = document.createElement('textarea');
        ta.value = code;
        ta.setAttribute('readonly', '');
        ta.style.position = 'absolute';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
            if (document.execCommand('copy')) {
                done();
            }
        } catch (err) {}
        document.body.removeChild(ta);
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('.cegg-deal-coupon__copy') : null;
        if (btn) {
            copyCode(btn);
        }
    });

    function init() {
        // The page may come from a cache; the cookie is the truth about this browser.
        document.querySelectorAll('.cegg-deal-votes[data-deal-id]').forEach(function (box) {
            if (document.cookie.indexOf('cegg_dv_' + box.getAttribute('data-deal-id') + '=') !== -1) {
                lock(box);
            }
        });
        tick();
        setInterval(tick, 30000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
