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

    /** Translations of the nearest module screen (the popup is used on every screen). */
    function texts(node) {
        var root = node && node.closest ? node.closest('[data-pps-config]') : null;
        root = root || q('[data-pps-config]');
        return root ? (config(root).i18n || {}) : {};
    }

    /**
     * Styled confirmation popup instead of window.confirm(). Resolves true (OK) or false
     * (cancel, Esc, click outside). Keyboard: Enter = OK, Esc = cancel, focus stays inside.
     */
    function ask(message, opts) {
        opts = opts || {};
        var t = opts.texts || {};
        return new Promise(function (resolve) {
            var previous = document.activeElement;
            var backdrop = el('div', 'pps pps-modal-backdrop');
            var dialog = el('div', 'pps-modal' + (opts.danger ? ' is-danger' : ''));
            dialog.setAttribute('role', 'alertdialog');
            dialog.setAttribute('aria-modal', 'true');
            var icon = el('div', 'pps-modal-icon', opts.danger ? '!' : '?');
            icon.setAttribute('aria-hidden', 'true');
            var title = el('div', 'pps-modal-title', t.confirmTitle || 'Please confirm');
            title.id = 'pps-modal-title-' + Date.now();
            var text = el('div', 'pps-modal-text', message);
            text.id = title.id + '-text';
            dialog.setAttribute('aria-labelledby', title.id);
            dialog.setAttribute('aria-describedby', text.id);
            var actions = el('div', 'pps-modal-actions');
            var back = el('button', 'pps-modal-btn pps-modal-back', t.confirmBack || 'No, go back');
            var ok = el('button', 'pps-modal-btn pps-modal-ok', t.confirmOk || 'Yes, continue');
            back.type = 'button';
            ok.type = 'button';
            actions.appendChild(back);
            actions.appendChild(ok);
            dialog.appendChild(icon);
            dialog.appendChild(title);
            dialog.appendChild(text);
            dialog.appendChild(actions);
            backdrop.appendChild(dialog);
            document.body.appendChild(backdrop);
            window.requestAnimationFrame(function () { backdrop.classList.add('is-open'); });
            ok.focus();

            function close(result) {
                document.removeEventListener('keydown', onKey, true);
                backdrop.classList.remove('is-open');
                window.setTimeout(function () {
                    if (backdrop.parentNode) { backdrop.parentNode.removeChild(backdrop); }
                    if (previous && previous.focus) { previous.focus(); }
                }, 180);
                resolve(result);
            }
            function onKey(e) {
                if (e.key === 'Escape') { e.preventDefault(); close(false); }
                else if (e.key === 'Enter') { e.preventDefault(); close(document.activeElement !== back); }
                else if (e.key === 'Tab') {
                    e.preventDefault();
                    (document.activeElement === ok ? back : ok).focus();
                }
            }
            document.addEventListener('keydown', onKey, true);
            ok.addEventListener('click', function () { close(true); });
            back.addEventListener('click', function () { close(false); });
            backdrop.addEventListener('mousedown', function (e) { if (e.target === backdrop) { close(false); } });
        });
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
            ask(question, { texts: t, danger: op !== 'run' }).then(function (yes) {
                if (yes) { send(op, id); }
            });
        }

        function send(op, id) {
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
        initBlog(root);

        var all = q('[data-pps-check-all]', root);
        if (all) {
            all.addEventListener('change', function () {
                qa('[data-pps-check]', root).forEach(function (box) { box.checked = all.checked; });
            });
        }

        refreshRelative(root, t);
        window.setInterval(function () { refreshRelative(root, t); }, 30000);
    }

    /** Blog panel: series or one time, live preview of the dates. */
    function initBlog(root) {
        var form = root.querySelector('[data-pps-blog-form]');
        if (!form) {
            return;
        }
        var q = function (sel) { return form.querySelector(sel); };
        var all = q('[data-pps-blog-all]');
        var checks = form.querySelectorAll('[data-pps-blog-check]');
        var preview = q('[data-pps-blog-preview]');
        var pad = function (n) { return (n < 10 ? '0' : '') + n; };
        var fmt = function (d) { return pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + d.getFullYear() + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()); };
        var update = function () {
            var mode = q('[data-pps-blog-mode]:checked').value;
            q('[data-pps-blog-series]').hidden = mode !== 'series';
            q('[data-pps-blog-at]').hidden = mode !== 'at';
            q('[data-pps-blog-new-wrap]').hidden = q('[data-pps-blog-action]').value !== 'publish';
            var n = Array.prototype.filter.call(checks, function (c) { return c.checked; }).length;
            if (!n) { preview.textContent = preview.getAttribute('data-none'); return; }
            var first;
            var last;
            if (mode === 'series') {
                var start = q('[data-pps-blog-start]').value;
                var time = q('[data-pps-blog-time]').value || '10:00';
                if (!start) { preview.textContent = ''; return; }
                first = new Date(start + 'T' + time);
                last = new Date(first.getTime());
                last.setDate(last.getDate() + (n - 1) * parseInt(q('[data-pps-blog-every]').value, 10));
            } else {
                var at = q('[name=pps_blog_at]').value;
                if (!at) { preview.textContent = ''; return; }
                first = new Date(at);
                last = first;
            }
            preview.textContent = n === 1 ? preview.getAttribute('data-one').replace('%s', fmt(first))
                : preview.getAttribute('data-many').replace('%1$d', n).replace('%2$s', fmt(first)).replace('%3$s', fmt(last));
        };
        if (all) {
            all.addEventListener('change', function () {
                Array.prototype.forEach.call(checks, function (c) { c.checked = all.checked; });
                update();
            });
        }
        form.addEventListener('change', update);
        form.addEventListener('input', update);
        update();
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

    // ------------------------------------------------------------------ settings page: cron builder

    function initSettings(root, cfg) {
        var t = cfg.i18n || {};
        var form = q('[data-pps-cron]', root);
        if (!form) { return; }
        var commands = {};
        try { commands = JSON.parse(form.getAttribute('data-pps-cron-commands') || '{}'); } catch (e) { commands = {}; }
        var mode = q('[data-pps-cron-mode]', form);
        var method = q('[data-pps-cron-method]', form);
        var minute = q('[data-pps-cron-minute]', form);
        var time = q('[data-pps-cron-time]', form);
        var custom = q('[data-pps-cron-custom]', form);
        var line = q('[data-pps-cron-line]', form);
        var exprBox = q('[data-pps-cron-expr]', form);
        var note = q('[data-pps-cron-note]', form);
        var field = '(\\*|\\d{1,2}(-\\d{1,2})?)(\\/\\d{1,2})?';
        var list = field + '(,' + field + ')*';
        var valid = new RegExp('^' + list + '( ' + list + '){4}$');

        function expression() {
            var m = mode.value;
            if (m === '1') { return '* * * * *'; }
            if (m === '60') { return Math.max(0, Math.min(59, parseInt(minute.value, 10) || 0)) + ' * * * *'; }
            if (m === 'daily') {
                var parts = (time.value || '06:00').split(':');
                return (parseInt(parts[1], 10) || 0) + ' ' + (parseInt(parts[0], 10) || 0) + ' * * *';
            }
            if (m === 'custom') { return custom.value.trim().replace(/\s+/g, ' '); }
            return '*/' + m + ' * * * *';
        }

        function delayText() {
            var m = mode.value;
            if (m === '1') { return t.cronDelay1; }
            if (m === '60') { return t.cronDelayHour; }
            if (m === 'daily') { return t.cronDelayDaily; }
            if (m === 'custom') { return t.cronDelayCustom; }
            return (t.cronDelayMin || '%d').replace('%d', m);
        }

        function sync() {
            qa('[data-pps-cron-for]', form).forEach(function (box) {
                box.hidden = box.getAttribute('data-pps-cron-for') !== mode.value;
            });
            var expr = expression();
            var ok = mode.value !== 'custom' || valid.test(expr);
            custom.classList.toggle('pps-invalid', !ok);
            exprBox.textContent = ok ? expr : '';
            note.textContent = ok ? delayText() : t.cronInvalid;
            note.className = 'pps-cron-note' + (ok ? (mode.value === '1' ? ' is-best' : '') : ' is-error');
            line.value = ok ? expr + ' ' + (commands[method.value] || '') : '';
        }

        [mode, method, minute, time, custom].forEach(function (input) {
            input.addEventListener('input', sync);
            input.addEventListener('change', sync);
        });
        sync();
    }

    // ------------------------------------------------------------------ everywhere

    document.addEventListener('click', function (e) {
        var confirmBtn = e.target.closest ? e.target.closest('[data-pps-confirm]') : null;
        if (confirmBtn) {
            if (confirmBtn.getAttribute('data-pps-confirmed')) {
                confirmBtn.removeAttribute('data-pps-confirmed');
            } else {
                e.preventDefault();
                ask(confirmBtn.getAttribute('data-pps-confirm'), {
                    texts: texts(confirmBtn),
                    danger: !!confirmBtn.getAttribute('data-pps-confirm-danger')
                }).then(function (yes) {
                    if (yes) {
                        // Repeat the click: keeps the button's name/value (and form="...") in the submission.
                        confirmBtn.setAttribute('data-pps-confirmed', '1');
                        confirmBtn.click();
                    }
                });
                return;
            }
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
            } else if (cfg.mode === 'settings') {
                initSettings(root, cfg);
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
