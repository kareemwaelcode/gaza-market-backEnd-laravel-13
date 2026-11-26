'use strict';
(function () {
var CONFIG = Object.assign({ API_BASE: '/api', TIMEOUT_MS: 15000, CREDENTIALS: 'same-origin' }, window.GM_CONFIG || {});
var API_BASE = String(CONFIG.API_BASE).replace(/\/+$/, '');

var DELAY_MS = 350;
var MIN_CHARS = 2;
var MAX_CHARS = 50;
var MAX_ITEMS = 8;

var TARGETS = [
{ selector: 'input.search-input', pickFills: false },
{ selector: '#productSearch', pickFills: true },
];

function debounce(fn, wait) {
var timer = null;
function debounced() {
    var ctx = this;
    var args = arguments;
    clearTimeout(timer);
    timer = setTimeout(function () {
    timer = null;
    fn.apply(ctx, args);
    }, wait);
}
debounced.cancel = function () {
    clearTimeout(timer);
    timer = null;
};
return debounced;
}

function isEnglish() {
return document.documentElement.lang === 'en';
}

function text(ar, en) {
return isEnglish() ? en : ar;
}

function pickName(item) {
var ar = item.name_ar;
var en = item.name_en;
return (isEnglish() ? en || ar : ar || en) || '';
}

function sizeText(item) {
var qty = Number(item.quantity);
var unit = item.unit ? (isEnglish() ? item.unit.name_en || item.unit.name_ar : item.unit.name_ar || item.unit.name_en) : '';
var parts = [];
if (isFinite(qty) && item.quantity !== null && item.quantity !== undefined && item.quantity !== '') parts.push(String(+qty.toFixed(2)));
if (unit) parts.push(unit);
return parts.join(' ');
}

function apiSearch(q, signal) {
var timeoutController = typeof AbortController === 'function' ? new AbortController() : null;
var timer = timeoutController ? setTimeout(function () { timeoutController.abort(); }, CONFIG.TIMEOUT_MS) : null;

if (signal && timeoutController) {
    signal.addEventListener('abort', function () { timeoutController.abort(); });
}

return fetch(API_BASE + '/products/search?q=' + encodeURIComponent(q), {
    method: 'GET',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    credentials: CONFIG.CREDENTIALS,
    signal: timeoutController ? timeoutController.signal : undefined,
})
    .then(function (res) {
    if (!res.ok) throw new Error('HTTP ' + res.status);
    return res.json();
    })
    .then(function (json) {
    return json && Array.isArray(json.data) ? json.data : [];
    })
    .then(
    function (rows) { if (timer) clearTimeout(timer); return rows; },
    function (err) { if (timer) clearTimeout(timer); throw err; }
    );
}

function injectStyles() {
if (document.getElementById('gm-psearch-style')) return;
var style = document.createElement('style');
style.id = 'gm-psearch-style';
style.textContent =
    '.gm-psearch-list{position:absolute;top:calc(100% + 4px);inset-inline:0;z-index:1080;margin:0;padding:4px;list-style:none;' +
    'background:#fff;color:#212529;border:1px solid rgba(0,0,0,.12);border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.12);' +
    'max-height:320px;overflow-y:auto;text-align:start}' +
    '.gm-psearch-item{display:flex;flex-direction:column;gap:2px;padding:8px 10px;border-radius:8px;cursor:pointer}' +
    '.gm-psearch-item:hover,.gm-psearch-item[aria-selected="true"]{background:rgba(0,0,0,.06)}' +
    '.gm-psearch-name{font-weight:600}' +
    '.gm-psearch-meta{font-size:.82em;opacity:.75}' +
    '.gm-psearch-empty{padding:10px;font-size:.9em;opacity:.75;cursor:default}' +
    '.gm-psearch-suggest{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;margin-top:10px;padding:10px 14px;' +
    'font:inherit;font-size:.92em;font-weight:700;color:var(--blue,#3866df);background:rgba(56,102,223,.08);' +
    'border:1.5px dashed rgba(56,102,223,.45);border-radius:12px;cursor:pointer;text-align:center;transition:background .15s,border-color .15s}' +
    '.gm-psearch-suggest[hidden]{display:none}' +
    '.gm-psearch-suggest:hover,.gm-psearch-suggest:focus-visible{background:rgba(56,102,223,.15);border-color:var(--blue,#3866df);outline:none}' +
    '.gm-psearch-suggest-plus{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;' +
    'background:var(--blue,#3866df);color:#fff;font-size:16px;line-height:1}' +
    'body.dark-mode .gm-psearch-suggest{color:#8fb0ff;background:rgba(143,176,255,.1);border-color:rgba(143,176,255,.45)}' +
    'body.dark-mode .gm-psearch-suggest:hover,body.dark-mode .gm-psearch-suggest:focus-visible{background:rgba(143,176,255,.18);border-color:#8fb0ff}' +
    'body.dark-mode .gm-psearch-suggest-plus{background:#8fb0ff;color:#1f2328}' +
    'body.dark-mode .gm-psearch-list{background:#1f2328;color:#e6e6e6;border-color:rgba(255,255,255,.14)}' +
    'body.dark-mode .gm-psearch-item:hover,body.dark-mode .gm-psearch-item[aria-selected="true"]{background:rgba(255,255,255,.08)}';
document.head.appendChild(style);
}

var uid = 0;

function attach(input, opts) {
if (!input || input.dataset.gmPsearch === '1') return;
input.dataset.gmPsearch = '1';

var host = input.parentElement;
if (!host) return;
if (window.getComputedStyle(host).position === 'static') host.style.position = 'relative';

var list = document.createElement('ul');
list.className = 'gm-psearch-list';
list.id = 'gm-psearch-' + (++uid);
list.setAttribute('role', 'listbox');
list.hidden = true;
host.appendChild(list);

var suggestBtn = null;
if (opts.pickFills) {
    suggestBtn = document.createElement('button');
    suggestBtn.type = 'button';
    suggestBtn.className = 'gm-psearch-suggest';
    suggestBtn.hidden = true;
    var plus = document.createElement('span');
    plus.className = 'gm-psearch-suggest-plus';
    plus.setAttribute('aria-hidden', 'true');
    plus.textContent = '+';
    var label = document.createElement('span');
    label.className = 'gm-psearch-suggest-label';
    suggestBtn.appendChild(plus);
    suggestBtn.appendChild(label);
    host.insertAdjacentElement('afterend', suggestBtn);
}

function setSuggestLabel() {
    if (suggestBtn) suggestBtn.lastChild.textContent = text('المنتج مش موجود؟ اقترحه كمنتج جديد', 'Product not found? Suggest it as a new product');
}

input.setAttribute('aria-autocomplete', 'list');
input.setAttribute('aria-controls', list.id);
input.setAttribute('aria-expanded', 'false');

var controller = null;
var seq = 0;

function hideList() {
    list.hidden = true;
    list.textContent = '';
    input.setAttribute('aria-expanded', 'false');
}

function hideSuggest() {
    if (suggestBtn) suggestBtn.hidden = true;
}

function hide() {
    hideList();
    hideSuggest();
}

function cancelPending() {
    seq++;
    if (controller) {
    controller.abort();
    controller = null;
    }
}

function render(rows) {
    list.textContent = '';

    if (!rows.length) {
    var empty = document.createElement('li');
    empty.className = 'gm-psearch-empty';
    empty.textContent = text('ما في نتائج', 'No results');
    list.appendChild(empty);

    if (suggestBtn) {
        setSuggestLabel();
        suggestBtn.hidden = false;
    }
    } else {
    hideSuggest();
    }

    rows.slice(0, MAX_ITEMS).forEach(function (item) {
    var li = document.createElement('li');
    li.className = 'gm-psearch-item';
    li.setAttribute('role', 'option');
    li.dataset.name = pickName(item);

    var name = document.createElement('span');
    name.className = 'gm-psearch-name';
    name.textContent = pickName(item);
    li.appendChild(name);

    var size = sizeText(item);
    li.dataset.id = String(item.id);
    li.dataset.label = size ? pickName(item) + ' (' + size + ')' : pickName(item);

    var metaEl = document.createElement('span');
    metaEl.className = 'gm-psearch-meta';
    metaEl.textContent = size;
    li.appendChild(metaEl);

    list.appendChild(li);
    });

    list.hidden = false;
    input.setAttribute('aria-expanded', 'true');
}

function run() {
    var q = input.value.trim();
    if (q.length < MIN_CHARS) {
    hide();
    return;
    }
    q = q.slice(0, MAX_CHARS);

    cancelPending();
    var mine = seq;
    controller = typeof AbortController === 'function' ? new AbortController() : null;

    var request = apiSearch(q, controller ? controller.signal : undefined);

    request.then(
    function (rows) {
        if (mine !== seq) return;
        controller = null;
        render(rows);
    },
    function (err) {
        if (mine !== seq) return;
        controller = null;
        if (err && err.name === 'AbortError') return;
        hide();
    }
    );
}

var debouncedRun = debounce(run, DELAY_MS);

input.addEventListener('input', function () {
    if (opts.pickFills) delete input.dataset.productId;

    if (input.value.trim().length < MIN_CHARS) {
    debouncedRun.cancel();
    cancelPending();
    hide();
    return;
    }
    debouncedRun();
});

input.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
    debouncedRun.cancel();
    cancelPending();
    hide();
    }
});

if (suggestBtn) {
    suggestBtn.addEventListener('mousedown', function (e) {
    e.preventDefault();
    });

    suggestBtn.addEventListener('click', function () {
    var typed = input.value.trim().slice(0, MAX_CHARS);
    debouncedRun.cancel();
    cancelPending();
    hide();
    document.dispatchEvent(new CustomEvent('gm:suggest-product', { detail: { name: typed } }));
    });

    var modalHost = input.closest ? input.closest('.modal') : null;
    if (modalHost) modalHost.addEventListener('hidden.bs.modal', hide);
}

list.addEventListener('mousedown', function (e) {
    var li = e.target.closest ? e.target.closest('.gm-psearch-item') : null;
    if (!li) return;
    e.preventDefault();
    if (opts.pickFills) {
    debouncedRun.cancel();
    cancelPending();
    input.value = li.dataset.label || li.dataset.name || '';
    input.dataset.productId = li.dataset.id || '';
    }
    hide();
});

document.addEventListener('mousedown', function (e) {
    if (!host.contains(e.target)) hideList();
});

input.addEventListener('blur', function () {
    setTimeout(function () {
    if (document.activeElement !== input) hideList();
    }, 150);
});
}

function init() {
injectStyles();
TARGETS.forEach(function (target) {
    Array.prototype.forEach.call(document.querySelectorAll(target.selector), function (input) {
    attach(input, target);
    });
});
}

window.GMProductSearch = { debounce: debounce, init: init };

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
else init();
})();