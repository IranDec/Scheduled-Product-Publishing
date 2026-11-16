/**
 * Scheduled Product Publishing - back office (product tab, list page, settings page).
 * No dependencies; everything the server sends is inserted as text, never as HTML.
 *
 * @author Mohammad Babaei | https://adschi.com
 */
(function () {
    'use strict';

    if (window.ProductSchedulerAdmin) {
        window.ProductSchedulerAdmin.scan();
        return;
    }

    var loadedAt = Date.now();

    function q(sel, root) { return (root || document).querySelector(sel); }
    function qa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
    function pad(n) { return (n < 10 ? '0' : '') + n; }

    function el(tag, cls, text) {
        var node = document.createElement(tag);
        if (cls) { node.className = cls; }
        if (text !== undefined && text !== null) { node.textContent = String(text); }
        return node;
    }

    function config(root) {
        try {
            return JSON.parse(root.getAttribute('data-pps-config') || '{}') || {};
        } catch (e) {
            return {};
        }
    }

    // The shop's wall clock (its time zone), independent of the browser's time zone.
    function serverNow(cfg) {
        var m = /^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/.exec(cfg.serverNow || '');
        var base = m ? new Date(+m[1], +m[2] - 1, +m[3], +m[4], +m[5], +m[6]) : new Date();
        return new Date(base.getTime() + (Date.now() - loadedAt));
    }

    function toInput(d) {
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    function quickDate(cfg, kind) {
        var d = serverNow(cfg);
        if (kind === '1h') {
            d = new Date(d.getTime() + 3600 * 1000);
            d.setSeconds(0, 0);
        } else if (kind === 'tomorrow') {
            d.setDate(d.getDate() + 1);
            d.setHours(8, 0, 0, 0);
        } else if (kind === 'monday') {
            d.setDate(d.getDate() + ((8 - d.getDay()) % 7 || 7));
            d.setHours(8, 0, 0, 0);
        }
        return toInput(d);
    }

    function relative(t, seconds) {
        if (seconds === null || seconds === undefined || isNaN(seconds)) { return ''; }
        var abs = Math.abs(seconds);
        if (abs < 60) { return t.now || 'now'; }
        var d = Math.floor(abs / 86400);
        var h = Math.floor((abs % 86400) / 3600);
        var m = Math.floor((abs % 3600) / 60);
        var parts = [];
        if (d) { parts.push(d + ' ' + t.unitDay); }
        if (h && d < 7) { parts.push(h + ' ' + t.unitHour); }
        if (!d && m) { parts.push(m + ' ' + t.unitMin); }
        return seconds > 0 ? (t.inPrefix + ' ' + parts.join(' ')) : (parts.join(' ') + ' ' + t.agoSuffix);
    }

    function secondsLeft(initial) {
        return initial - Math.round((Date.now() - loadedAt) / 1000);
    }

    function post(cfg, action, fields) {
        var body = new FormData();
        body.append('ppsAjax', '1');
        body.append('ppsAction', action);
        body.append('pps_token', cfg.token || '');
        Object.keys(fields || {}).forEach(function (key) {
            var value = fields[key];
            if (Array.isArray(value)) {
                value.forEach(function (v) { body.append(key + '[]', v); });
            } else if (value !== undefined && value !== null) {
                body.append(key, value);
            }
        });
        return fetch(cfg.ajaxUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            return response.text();
        }).then(function (text) {
            try {
                return JSON.parse(text);
            } catch (e) {
                return { success: false, error: (cfg.i18n || {}).serverError };
            }
        }, function () {
            return { success: false, error: (cfg.i18n || {}).serverError };
        });
    }

    // ------------------------------------------------------------------ shared form behaviour

    /** Action select -> "unpublish/publish again" label and the "show as new" option. */
    function bindActionForm(root, actionSelect) {
        var label = q('[data-pps-end-label]', root);
        var newWrap = q('[data-pps-new-wrap]', root);
        function sync() {
            var publish = actionSelect.value === 'publish';
            if (label) { label.textContent = label.getAttribute(publish ? 'data-publish' : 'data-unpublish'); }
            if (newWrap) {
                newWrap.hidden = !publish;
                if (!publish) { q('input', newWrap).checked = false; }
            }
        }
        actionSelect.addEventListener('change', sync);
        sync();
    }

    function bindToggle(checkbox, target) {
        if (!checkbox || !target) { return; }
        function sync() { target.disabled = !checkbox.checked; }
        checkbox.addEventListener('change', sync);
        sync();
    }

    function bindShopAll(all, shops) {
        if (!all) { return; }
        function sync() {
            shops.forEach(function (box) {
                box.disabled = all.checked;
                if (all.checked) { box.checked = true; }
            });
        }
        all.addEventListener('change', sync);
        sync();
    }

    function bindQuick(root, cfg, input) {
        qa('[data-pps-quick]', root).forEach(function (btn) {
            btn.addEventListener('click', function () {
                input.value = quickDate(cfg, btn.getAttribute('data-pps-quick'));
                input.dispatchEvent(new Event('change'));
            });
        });
    }

    function refreshRelative(root, t) {
        qa('[data-pps-in]', root).forEach(function (node) {
            var left = secondsLeft(parseInt(node.getAttribute('data-pps-in'), 10));
            node.textContent = (node.getAttribute('data-pps-pending') && left <= 0) ? t.overdue : relative(t, left);
        });
    }

    // ------------------------------------------------------------------ product tab

    function initProductTab(root, cfg) {
        var t = cfg.i18n || {};
        var data = cfg.data;
        if (!data) { return; }

        var action = q('#pps-action', root);
        var at = q('#pps-at', root);
        var endOn = q('#pps-end-on', root);
        var end = q('#pps-end', root);
        var shopAll = q('#pps-shop-all', root);
        var shopBoxes = qa('.pps-shop', root);
        var markNew = q('#pps-mark-new', root);
        var addBtn = q('[data-pps-add]', root);
        var msg = q('[data-pps-msg]', root);

        // The product page is one form: never let Enter in these fields submit it.
        root.addEventListener('keydown', function (e) {
            if ((e.key === 'Enter' || e.keyCode === 13) && e.target.tagName === 'INPUT') {
                e.preventDefault();
            }
        });

        at.min = toInput(serverNow(cfg));
        end.min = at.min;
        bindActionForm(root, action);
        bindToggle(endOn, end);
        bindShopAll(shopAll, shopBoxes);
        bindQuick(root, cfg, at);
        at.addEventListener('change', function () {
            if (at.value && (!end.value || end.value <= at.value)) { end.min = at.value; }
        });

        function say(text, ok) {
            msg.textContent = text || '';
            msg.className = 'pps-msg ' + (ok ? 'is-ok' : 'is-error');
        }

        function render(d) {
            data = d;
            renderState(q('[data-pps-state]', root), d, t);
            renderList(q('[data-pps-upcoming]', root), d.upcoming, true);
            renderList(q('[data-pps-history]', root), d.history, false);
            var runner = q('[data-pps-runner]', root);
            runner.className = 'pps-runner is-' + d.runner.level;
            runner.textContent = d.runner.text + (d.runner.last_run ? ' ' + t.lastRun + ' ' + d.runner.last_run + '.' : '');
        }

        function renderList(box, rows, upcoming) {
            box.textContent = '';
            if (!rows.length) {
                box.appendChild(el('div', 'pps-empty', upcoming ? t.noUpcoming : t.noHistory));
                return;
            }
            rows.forEach(function (r) {
                var item = el('div', 'pps-item');
                item.appendChild(el('span', 'pps-badge pps-badge-' + r.action, r.action_label));

                var main = el('div', 'pps-item-main');
                main.appendChild(el('div', 'pps-when', r.when));
                var meta = [];
                if (upcoming) {
                    var left = secondsLeft(r.in_seconds);
                    meta.push(left <= 0 ? t.overdue : relative(t, left));
                } else if (r.executed) {
                    meta.push(t.executed + ' ' + r.executed);
                }
                if (d_multistore()) { meta.push(r.shop_name); }
                if (r.mark_new) { meta.push(t.markedNew); }
                if (r.employee) { meta.push(t.by + ' ' + r.employee); }
                main.appendChild(el('div', 'pps-sub', meta.join(' · ')));
                if (!upcoming && r.message) { main.appendChild(el('div', 'pps-sub', r.message)); }
                item.appendChild(main);

                var actions = el('div', 'pps-item-actions');
                if (upcoming) {
                    if (r.status === 'pending') {
                        actions.appendChild(button(t.runNow, 'btn btn-default btn-sm', function () { act('run', r.id, t.confirmRun); }));
                        actions.appendChild(button(t.cancel, 'btn btn-default btn-sm pps-btn-danger', function () { act('cancel', r.id, t.confirmCancel); }));
                    } else {
                        actions.appendChild(el('span', 'pps-status pps-status-' + r.status, r.status_label));
                    }
                } else {
                    actions.appendChild(el('span', 'pps-status pps-status-' + r.status, r.status_label));
                    if (r.status !== 'running') {
                        actions.appendChild(button('×', 'btn btn-link btn-sm', function () { act('delete', r.id, t.confirmDelete); }, t.delete));
                    }
                }
                item.appendChild(actions);
                box.appendChild(item);
            });
        }

        function d_multistore() { return !!(data && data.multistore); }

        function button(text, cls, onClick, title) {
            var b = el('button', cls, text);
            b.type = 'button';
            if (title) { b.title = title; b.setAttribute('aria-label', title); }
            b.addEventListener('click', onClick);
            return b;
        }

        function act(op, id, question) {
            if (question && !window.confirm(question)) { return; }
            post(cfg, op, { id_product: cfg.idProduct, id: id }).then(function (res) {
                if (res.success) {
                    render(res.data);
                    say(res.message, true);
                } else {
                    say(res.error, false);
                }
            });
        }

        addBtn.addEventListener('click', function () {
            if (!at.value) { say(t.chooseDate, false); at.focus(); return; }
            if (endOn.checked && !end.value) { say(t.chooseDate, false); end.focus(); return; }
            var fields = {
                id_product: cfg.idProduct,
                pps_action: action.value,
                pps_at: at.value,
                pps_end_at: endOn.checked ? end.value : '',
                pps_mark_new: markNew && markNew.checked ? '1' : ''
            };
            if (shopAll) {
                if (shopAll.checked) {
                    fields.pps_shop_all = '1';
                } else {
                    fields.pps_shops = shopBoxes.filter(function (b) { return b.checked; }).map(function (b) { return b.value; });
                    if (!fields.pps_shops.length) { say(t.chooseShop, false); return; }
                }
            }
            addBtn.disabled = true;
            say(t.saving, true);
            post(cfg, 'add', fields).then(function (res) {
                addBtn.disabled = false;
                if (res.success) {
                    render(res.data);
                    say(res.message || t.saved, true);
                    at.value = '';
                    end.value = '';
                    endOn.checked = false;
                    end.disabled = true;
                } else {
                    say(res.error, false);
                }
            });
        });

        render(data);
        window.setInterval(function () {
            renderList(q('[data-pps-upcoming]', root), data.upcoming, true);
        }, 30000);
    }

    function renderState(box, d, t) {
        if (!box) { return; }
        box.textContent = '';
        (d.shops || []).forEach(function (shop) {
            var text = (d.multistore ? shop.name + ': ' : '') + (shop.active ? t.online : t.offline);
            box.appendChild(el('span', 'pps-pill ' + (shop.active ? 'pps-pill-on' : 'pps-pill-off'), text));
        });
    }

    // ------------------------------------------------------------------ list page

    function initList(root, cfg) {
        var t = cfg.i18n || {};
        var form = q('[data-pps-add-form]', root);
        if (form) {
            bindActionForm(form, q('[data-pps-action]', form));
            bindToggle(q('[data-pps-end-on]', form), q('[data-pps-end]', form));
            bindShopAll(q('[data-pps-shop-all]', form), qa('[data-pps-shop]', form));
            bindQuick(form, cfg, q('[data-pps-at]', form));
            initPicker(form, cfg);
        }

        var all = q('[data-pps-check-all]', root);
        if (all) {
            all.addEventListener('change', function () {
                qa('[data-pps-check]', root).forEach(function (box) { box.checked = all.checked; });
            });
        }

        refreshRelative(root, t);
        window.setInterval(function () { refreshRelative(root, t); }, 30000);
    }

    function initPicker(form, cfg) {
        var t = cfg.i18n || {};
        var input = q('[data-pps-picker]', form);
        var results = q('[data-pps-results]', form);
        var chips = q('[data-pps-chips]', form);
        var hidden = q('[data-pps-picked]', form);
        var picked = [];
        var timer = null;
        var seq = 0;

        function sync() {
            hidden.value = picked.map(function (p) { return p.id; }).join(',');
            chips.textContent = '';
            picked.forEach(function (p) {
                var chip = el('span', 'pps-chip-picked');
                chip.appendChild(el('span', '', '#' + p.id + ' ' + p.name));
                var x = el('button', '', '×');
                x.type = 'button';
                x.title = t.remove;
                x.setAttribute('aria-label', t.remove);
                x.addEventListener('click', function () {
                    picked = picked.filter(function (o) { return o.id !== p.id; });
                    sync();
                });
                chip.appendChild(x);
                chips.appendChild(chip);
            });
        }

        function pick(p) {
            if (!picked.some(function (o) { return o.id === p.id; })) { picked.push(p); }
            sync();
            input.value = '';
            results.hidden = true;
            input.focus();
        }

        function show(list) {
            results.textContent = '';
            if (!list.length) {
                results.appendChild(el('div', 'pps-empty', t.noResults));
            }
            list.forEach(function (p, i) {
                var b = el('button', 'pps-result' + (i === 0 ? ' is-active' : ''));
                b.type = 'button';
                b.appendChild(el('span', 'pps-result-name', p.name || ('#' + p.id)));
                b.appendChild(el('span', 'pps-sub', '#' + p.id + (p.reference ? ' · ' + p.reference : '')));
                b.appendChild(el('span', 'pps-pill ' + (p.active ? 'pps-pill-on' : 'pps-pill-off'), p.active ? t.online : t.offline));
                b.addEventListener('click', function () { pick(p); });
                b._pps = p;
                results.appendChild(b);
            });
            results.hidden = false;
        }

        input.addEventListener('input', function () {
            window.clearTimeout(timer);
            var value = input.value.trim();
            if (value.length < 2 && !/^\d+$/.test(value)) {
                results.hidden = true;
                return;
            }
            timer = window.setTimeout(function () {
                var mine = ++seq;
                post(cfg, 'search', { q: value }).then(function (res) {
                    if (mine !== seq) { return; }
                    show(res.success ? res.data : []);
                });
            }, 250);
        });

        input.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.keyCode !== 13) { return; }
            e.preventDefault();
            var first = q('.pps-result.is-active', results);
            if (!results.hidden && first && first._pps) { pick(first._pps); }
        });

        document.addEventListener('click', function (e) {
            if (!results.contains(e.target) && e.target !== input) { results.hidden = true; }
        });
    }

    // ------------------------------------------------------------------ everywhere

    document.addEventListener('click', function (e) {
        var confirmBtn = e.target.closest ? e.target.closest('[data-pps-confirm]') : null;
        if (confirmBtn && !window.confirm(confirmBtn.getAttribute('data-pps-confirm'))) {
            e.preventDefault();
            return;
        }

        var toggle = e.target.closest ? e.target.closest('[data-pps-toggle]') : null;
        if (toggle) {
            var target = q(toggle.getAttribute('data-pps-toggle'));
            if (target) { target.hidden = !target.hidden; }
        }

        var copy = e.target.closest ? e.target.closest('[data-pps-copy]') : null;
        if (copy) {
            var field = q(copy.getAttribute('data-pps-copy'));
            if (field) {
                field.select();
                var label = copy.textContent;
                var done = function () {
                    var root = copy.closest('[data-pps-config]');
                    copy.textContent = (root ? (config(root).i18n || {}).copied : '') || '✓';
                    window.setTimeout(function () { copy.textContent = label; }, 1500);
                };
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(field.value).then(done, function () { document.execCommand('copy'); done(); });
                } else {
                    document.execCommand('copy');
                    done();
                }
            }
        }
    });

    document.addEventListener('change', function (e) {
        if (e.target.hasAttribute && e.target.hasAttribute('data-pps-autosubmit') && e.target.form) {
            e.target.form.submit();
        }
    });

    function scan() {
        qa('[data-pps-config]').forEach(function (root) {
            if (root.getAttribute('data-pps-ready')) { return; }
            root.setAttribute('data-pps-ready', '1');
            var cfg = config(root);
            if (cfg.mode === 'product') {
                initProductTab(root, cfg);
            } else if (cfg.mode === 'list') {
                initList(root, cfg);
            }
        });
    }

    window.ProductSchedulerAdmin = { scan: scan };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scan);
    } else {
        scan();
    }
})();
