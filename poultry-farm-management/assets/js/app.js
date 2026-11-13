(function () {
  'use strict';
  var A = window.__app || {};
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  // Keep fixed overlays outside animated content so they cover and center in the viewport.
  $$('.modal').forEach(function (modal) {
    if (modal.parentElement !== document.body) document.body.appendChild(modal);
  });

  // ----- persistent color theme -----
  var themeToggle = $('[data-theme-toggle]');
  var themeToggleLabel = $('[data-theme-toggle-label]');
  function applyTheme(theme) {
    var dark = theme === 'dark';
    document.documentElement.dataset.theme = dark ? 'dark' : 'light';
    if (!themeToggle) return;
    themeToggle.checked = dark;
    var label = dark ? 'Switch to light mode' : 'Switch to dark mode';
    themeToggle.setAttribute('aria-label', label);
    if (themeToggleLabel) themeToggleLabel.title = label;
  }
  if (themeToggle) {
    applyTheme(document.documentElement.dataset.theme);
    themeToggle.addEventListener('change', function () {
      var theme = themeToggle.checked ? 'dark' : 'light';
      applyTheme(theme);
      try { localStorage.setItem('pfm-theme', theme); }
      catch (error) { console.error('Theme changed, but the preference could not be saved.', error); }
    });
  }

  $$('.language-switch').forEach(function (form) {
    var toggle = $('[data-language-toggle]', form);
    var language = $('input[name="language"]', form);
    if (!toggle || !language) return;
    toggle.addEventListener('change', function () {
      language.value = toggle.checked ? 'km' : 'en';
      if (form.requestSubmit) form.requestSubmit();
      else form.submit();
    });
  });

  // ----- toasts -----
  window.toast = function (msg, type) {
    var box = $('#toasts'); if (!box) return;
    var t = document.createElement('div'); t.className = 'toast ' + (type || 'success');
    t.setAttribute('role', 'status'); t.textContent = msg; box.appendChild(t);
    setTimeout(function () { t.classList.add('out'); setTimeout(function () { t.remove(); }, 320); }, type === 'error' ? 7000 : 4500);
  };
  (A.flash || []).forEach(function (f) { toast(f.msg, f.type); });

  // ----- modals -----
  // Reset fields to their rendered defaults without discarding fixed hidden IDs.
  function prepModal(id) { var m = document.getElementById(id); if (!m) return; var f = $('form', m); if (f) f.reset(); $$('[data-title]', m).forEach(function (t) { if (t.dataset.orig) t.textContent = t.dataset.orig; }); }
  window.openModal = function (id) { var m = document.getElementById(id); if (!m) return; document.body.appendChild(m); m.hidden = false; document.body.style.overflow = 'hidden'; var f = $('input:not([type=hidden]),select,textarea', m); if (f && !('ontouchstart' in window)) setTimeout(function () { f.focus(); }, 40); };
  window.closeModal = function (m) { m.hidden = true; if (!$$('.modal:not([hidden])').length) document.body.style.overflow = ''; };
  document.addEventListener('click', function (e) {
    var o = e.target.closest('[data-modal-open]'); if (o) { e.preventDefault(); prepModal(o.getAttribute('data-modal-open')); openModal(o.getAttribute('data-modal-open')); return; }
    var c = e.target.closest('[data-modal-close]'); if (c) { closeModal(c.closest('.modal')); }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') $$('.modal:not([hidden])').forEach(closeModal); });
  if (A.openModal) openModal(A.openModal);

  // ----- confirm dialog for forms/buttons with data-confirm -----
  var pendingForm = null;
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (f.dataset.confirmed === '1') return;
    var msg = f.getAttribute('data-confirm');
    if (msg) {
      e.preventDefault(); pendingForm = f;
      $('#confirm-text').textContent = msg;
      $('#confirm-title').textContent = f.getAttribute('data-confirm-title') || 'Please confirm';
      $('#confirm-ok').textContent = f.getAttribute('data-confirm-ok') || 'Confirm';
      openModal('confirm-modal'); return;
    }
    // double-submit guard + loading indicator
    if (!f.hasAttribute('data-no-lock')) {
      var b = $('button[type=submit],button:not([type])', f);
      if (b) setTimeout(function () { b.classList.add('loading'); b.disabled = true; }, 0);
    }
  });
  var ok = $('#confirm-ok');
  if (ok) ok.addEventListener('click', function () { if (!pendingForm) return; closeModal($('#confirm-modal')); pendingForm.dataset.confirmed = '1'; pendingForm.requestSubmit ? pendingForm.requestSubmit() : pendingForm.submit(); });
  window.addEventListener('pageshow', function () { $$('button.loading').forEach(function (b) { b.classList.remove('loading'); b.disabled = false; }); });

  // ----- dropdowns -----
  var notificationToggle = $('[data-notification-toggle]');
  function notificationStorageKey() { return 'pfm-alerts-read-' + notificationToggle.dataset.alertUser; }
  function hideNotificationCount() {
    var count = $('[data-alert-count]', notificationToggle);
    if (count) count.hidden = true;
  }
  if (notificationToggle) {
    try {
      if (localStorage.getItem(notificationStorageKey()) === notificationToggle.dataset.alertKey) hideNotificationCount();
    } catch (error) {
      console.error('Notification read state could not be loaded.', error);
    }
  }
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-dropdown-toggle]');
    $$('[data-dropdown] .dropdown-menu').forEach(function (m) { var own = t && t.closest('[data-dropdown]') === m.closest('[data-dropdown]'); if (!own) m.hidden = true; });
    if (t) {
      var m = $('.dropdown-menu', t.closest('[data-dropdown]')); m.hidden = !m.hidden;
      if (t === notificationToggle && !m.hidden) {
        try {
          localStorage.setItem(notificationStorageKey(), notificationToggle.dataset.alertKey);
          hideNotificationCount();
        } catch (error) {
          console.error('Notification read state could not be saved.', error);
        }
      }
    }
  });

  // ----- sidebar (mobile) -----
  var sb = $('#sidebar'), sc = $('.scrim');
  function sbToggle(open) { if (!sb) return; sb.classList.toggle('open', open); sc.classList.toggle('show', open); }
  $$('[data-sidebar-open]').forEach(function (b) { b.addEventListener('click', function () { sbToggle(true); }); });
  $$('[data-sidebar-close]').forEach(function (b) { b.addEventListener('click', function () { sbToggle(false); }); });
  if (sb && document.body.classList.contains('sidebar-compact')) {
    function expandSidebar() { document.body.classList.add('sidebar-expanded'); }
    function collapseSidebar() { document.body.classList.remove('sidebar-expanded'); }
    sb.addEventListener('pointerenter', expandSidebar);
    sb.addEventListener('pointerleave', collapseSidebar);
    sb.addEventListener('focusin', expandSidebar);
    sb.addEventListener('focusout', function (e) {
      if (!sb.contains(e.relatedTarget)) collapseSidebar();
    });
    sb.addEventListener('click', function (e) {
      if (e.target.closest('.nav-item')) collapseSidebar();
    });
  }
  // ----- button ripple -----
  document.addEventListener('pointerdown', function (e) {
    var b = e.target.closest('.btn'); if (!b) return;
    var r = b.getBoundingClientRect(), s = Math.max(r.width, r.height), d = document.createElement('span');
    d.className = 'ripple'; d.style.cssText = 'width:' + s + 'px;height:' + s + 'px;left:' + (e.clientX - r.left - s / 2) + 'px;top:' + (e.clientY - r.top - s / 2) + 'px';
    b.appendChild(d); setTimeout(function () { d.remove(); }, 600);
  });

  // ----- spotlight on cards -----
  document.addEventListener('pointermove', function (e) {
    var c = e.target.closest('.card'); if (!c) return; var r = c.getBoundingClientRect();
    c.style.setProperty('--mx', (e.clientX - r.left) + 'px'); c.style.setProperty('--my', (e.clientY - r.top) + 'px');
  });

  // ----- animated numbers -----
  $$('[data-count]').forEach(function (el) {
    var target = parseFloat(el.getAttribute('data-count')); if (isNaN(target)) return;
    var dec = parseInt(el.getAttribute('data-dec') || '0', 10), t0 = null, dur = 700;
    // Keep any unit (e.g. the % span) after the number while it counts up.
    var num = document.createElement('span');
    num.textContent = el.firstChild && el.firstChild.nodeType === 3 ? el.firstChild.nodeValue : '';
    if (el.firstChild && el.firstChild.nodeType === 3) el.replaceChild(num, el.firstChild); else el.insertBefore(num, el.firstChild);
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    function step(ts) { if (!t0) t0 = ts; var p = Math.min(1, (ts - t0) / dur), v = target * (1 - Math.pow(1 - p, 3));
      num.textContent = v.toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec }); if (p < 1) requestAnimationFrame(step); }
    requestAnimationFrame(step);
  });

  // ----- tabs (client side) -----
  $$('[data-tabs]').forEach(function (wrap) {
    var tabs = $$('[data-tab]', wrap), panels = $$('[data-panel]', wrap.parentNode);
    function show(name) { tabs.forEach(function (t) { t.classList.toggle('on', t.dataset.tab === name); }); panels.forEach(function (p) { p.hidden = p.dataset.panel !== name; }); try { history.replaceState(null, '', '#' + name); } catch (e) {} }
    tabs.forEach(function (t) { t.addEventListener('click', function () { show(t.dataset.tab); }); });
    var h = location.hash.slice(1); show(tabs.some(function (t) { return t.dataset.tab === h; }) ? h : tabs[0].dataset.tab);
  });

  // ----- range filter custom toggle -----
  $$('[data-range-form]').forEach(function (form) {
    var control = $('.timeframe-control', form);
    if (!control) return;
    var options = $$('[data-range-option]', control);
    var thumb = $('.timeframe-thumb', control);
    var submitTimer;
    function moveThumb(button) {
      if (!thumb || !button) return;
      var trackRect = control.getBoundingClientRect();
      var optionRect = button.getBoundingClientRect();
      thumb.style.left = (optionRect.left - trackRect.left) + 'px';
      thumb.style.width = optionRect.width + 'px';
    }
    moveThumb($('.timeframe-option.is-selected', control));
    window.addEventListener('resize', function () { moveThumb($('.timeframe-option.is-selected', control)); });
    options.forEach(function (button) {
      button.addEventListener('click', function (event) {
        var dates = $('.custom-dates', form);
        if (button.hasAttribute('data-custom-toggle')) {
          event.preventDefault();
          moveThumb(button);
          control.removeAttribute('data-changing');
          options.forEach(function (option) {
            var selected = option === button;
            option.classList.toggle('is-selected', selected);
            option.setAttribute('aria-pressed', selected ? 'true' : 'false');
            option.removeAttribute('data-pending');
          });
          dates.classList.add('show');
          $('input[type="date"]', dates).focus();
          return;
        }
        event.preventDefault();
        clearTimeout(submitTimer);
        control.dataset.changing = '';
        moveThumb(button);
        options.forEach(function (option) {
          var selected = option === button;
          option.classList.toggle('is-selected', selected);
          option.setAttribute('aria-pressed', selected ? 'true' : 'false');
          if (selected) option.dataset.pending = '';
          else option.removeAttribute('data-pending');
        });
        submitTimer = setTimeout(function () {
          if (form.requestSubmit) form.requestSubmit(button);
          else {
            var value = document.createElement('input');
            value.type = 'hidden';
            value.name = button.name;
            value.value = button.value;
            form.appendChild(value);
            form.submit();
          }
        }, 320);
      });
    });
  });

  // ----- fill edit modals from data-* attributes -----
  $$('[data-fill]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var m = document.getElementById(btn.getAttribute('data-fill')); if (!m) return;
      var data = JSON.parse(btn.getAttribute('data-values') || '{}');
      Object.keys(data).forEach(function (k) { var el = m.querySelector('[name="' + k + '"]'); if (!el) return; if (el.type === 'checkbox') el.checked = !!+data[k]; else el.value = data[k]; });
      $$('[data-title]', m).forEach(function (t) { if (!t.dataset.orig) t.dataset.orig = t.textContent; t.textContent = btn.getAttribute('data-title') || t.textContent; });
      openModal(m.id);
    });
  });

  // ----- generate password helper -----
  $$('[data-genpass]').forEach(function (b) { b.addEventListener('click', function () {
    var chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789', out = '', a = new Uint32Array(10); crypto.getRandomValues(a);
    for (var i = 0; i < 10; i++) out += chars[a[i] % chars.length]; var t = document.getElementById(b.getAttribute('data-genpass')); t.type = 'text'; t.value = out; }); });

  // ----- global search -----
  var sWrap = $('[data-search]');
  if (sWrap) {
    var inp = $('[data-search-input]', sWrap), pop = $('[data-search-pop]', sWrap), timer = null, ctl = null, idx = -1;
    function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function suggestions() { pop.innerHTML = '<div class="sp-label">Quick actions</div>' + (A.suggestions || []).map(function (s) { return '<a class="sp-item" href="' + esc(s.url) + '">' + s.icon + '<span>' + esc(s.label) + '</span></a>'; }).join(''); pop.hidden = false; }
    function results(q) {
      if (ctl) ctl.abort(); ctl = new AbortController();
      pop.innerHTML = '<div class="sp-label">Searching…</div>'; pop.hidden = false;
      fetch(A.base + '/api/search.php?q=' + encodeURIComponent(q), { signal: ctl.signal, headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { return r.json(); }).then(function (d) {
        if (!d.groups.length) { pop.innerHTML = '<div class="sp-label">No matches for “' + esc(q) + '”</div>'; return; }
        pop.innerHTML = d.groups.map(function (g) { return '<div class="sp-label">' + esc(g.title) + '</div>' + g.items.map(function (i) { return '<a class="sp-item" href="' + esc(i.url) + '"><span>' + esc(i.label) + '</span><small>' + esc(i.meta || '') + '</small></a>'; }).join(''); }).join('');
      }).catch(function (e) { if (e.name !== 'AbortError') pop.innerHTML = '<div class="sp-label">Search is unavailable right now.</div>'; });
    }
    inp.addEventListener('focus', function () { if (!inp.value.trim()) suggestions(); });
    inp.addEventListener('input', function () { clearTimeout(timer); var v = inp.value.trim(); idx = -1; if (v.length < 1) { suggestions(); return; } timer = setTimeout(function () { results(v); }, 220); });
    inp.addEventListener('keydown', function (e) {
      var items = $$('.sp-item', pop); if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); idx = (idx + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % Math.max(items.length, 1); items.forEach(function (i, n) { i.classList.toggle('hl', n === idx); }); }
      else if (e.key === 'Enter' && idx >= 0 && items[idx]) { e.preventDefault(); location.href = items[idx].href; }
      else if (e.key === 'Escape') { pop.hidden = true; inp.blur(); }
    });
    document.addEventListener('click', function (e) { if (!sWrap.contains(e.target)) pop.hidden = true; });
  }

  // ----- custom select menus -----
  var selectId = 0;
  var customSelects = [];
  function selectOptions(select, list) {
    list.innerHTML = '';
    Array.prototype.forEach.call(select.children, function (child) {
      if (child.tagName === 'OPTGROUP') {
        var group = document.createElement('div');
        group.className = 'select-group';
        group.textContent = child.label;
        list.appendChild(group);
        Array.prototype.forEach.call(child.children, function (option) { addOption(option, list); });
      } else if (child.tagName === 'OPTION') addOption(child, list);
    });
    updateCustomSelect(select);
  }
  function addOption(option, list) {
    var item = document.createElement('button');
    item.type = 'button';
    item.className = 'select-option';
    item.setAttribute('role', 'option');
    item.dataset.value = option.value;
    item.textContent = option.textContent;
    item.disabled = option.disabled || (option.parentElement.tagName === 'OPTGROUP' && option.parentElement.disabled);
    item.setAttribute('aria-selected', option.selected ? 'true' : 'false');
    list.appendChild(item);
  }
  function updateCustomSelect(select) {
    var widget = select._customSelect;
    if (!widget) return;
    var option = select.options[select.selectedIndex];
    widget.trigger.textContent = option ? option.textContent : '';
    widget.trigger.appendChild(widget.arrow);
    widget.trigger.disabled = select.disabled;
    widget.trigger.setAttribute('aria-required', select.required ? 'true' : 'false');
    widget.trigger.setAttribute('aria-invalid', select.getAttribute('aria-invalid') || 'false');
    Array.prototype.forEach.call(widget.list.querySelectorAll('.select-option'), function (item) {
      var selected = item.dataset.value === select.value;
      item.setAttribute('aria-selected', selected ? 'true' : 'false');
      item.disabled = Array.prototype.some.call(select.options, function (candidate) {
        return candidate.value === item.dataset.value && (candidate.disabled || (candidate.parentElement.tagName === 'OPTGROUP' && candidate.parentElement.disabled));
      });
    });
  }
  function closeCustomSelect(widget) {
    widget.root.classList.remove('is-open');
    widget.trigger.setAttribute('aria-expanded', 'false');
  }
  function openCustomSelect(widget, focusOption) {
    widget.root.classList.add('is-open');
    widget.trigger.setAttribute('aria-expanded', 'true');
    if (focusOption) {
      var options = Array.prototype.filter.call(widget.list.querySelectorAll('.select-option'), function (item) { return !item.disabled && item.getAttribute('aria-selected') !== 'true'; });
      if (options.length) options[0].focus();
    }
  }
  function enhanceSelect(select) {
    if (select._customSelect || select.multiple || select.size > 1) return;
    selectId += 1;
    var root = document.createElement('div');
    root.className = 'select';
    var trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'selected';
    trigger.id = select.id ? select.id + '-custom-trigger' : 'custom-select-' + selectId;
    trigger.setAttribute('role', 'combobox');
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', 'custom-select-options-' + selectId);
    var labels = select.labels ? Array.prototype.slice.call(select.labels) : [];
    var labelElement = labels[0] || (select.closest('.field') || select.parentElement).querySelector('label');
    var label = labelElement ? labelElement.textContent.trim() : '';
    if (select.getAttribute('aria-label')) label = select.getAttribute('aria-label');
    if (label) trigger.setAttribute('aria-label', label);
    var arrow = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    arrow.setAttribute('viewBox', '0 0 512 512');
    arrow.setAttribute('aria-hidden', 'true');
    arrow.classList.add('arrow');
    var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', 'M233.4 406.6c12.5 12.5 32.8 12.5 45.3 0l192-192c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L256 338.7 86.6 169.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l192 192z');
    arrow.appendChild(path);
    var list = document.createElement('div');
    list.className = 'options';
    list.id = 'custom-select-options-' + selectId;
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', label || 'Options');
    var widget = { root: root, trigger: trigger, arrow: arrow, list: list, select: select };
    select._customSelect = widget;
    selectOptions(select, list);
    var parent = select.parentNode;
    parent.insertBefore(root, select);
    root.appendChild(trigger);
    root.appendChild(list);
    select.classList.add('select-native');
    select.setAttribute('aria-hidden', 'true');
    select.tabIndex = -1;
    labels.forEach(function (associatedLabel) { associatedLabel.htmlFor = trigger.id; });
    if (labelElement && labels.indexOf(labelElement) === -1) labelElement.htmlFor = trigger.id;
    select.addEventListener('change', function () { updateCustomSelect(select); });
    select.addEventListener('invalid', function (event) {
      event.preventDefault();
      trigger.focus();
      openCustomSelect(widget, true);
    });
    trigger.addEventListener('click', function () {
      if (root.classList.contains('is-open')) closeCustomSelect(widget);
      else openCustomSelect(widget, false);
    });
    trigger.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp' || event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openCustomSelect(widget, true);
      } else if (event.key === 'Escape') closeCustomSelect(widget);
    });
    root.addEventListener('focusout', function (event) {
      if (!root.contains(event.relatedTarget)) closeCustomSelect(widget);
    });
    list.addEventListener('click', function (event) {
      var item = event.target.closest('.select-option');
      if (!item || item.disabled) return;
      if (select.value !== item.dataset.value) {
        select.value = item.dataset.value;
        select.dispatchEvent(new Event('input', { bubbles: true }));
        select.dispatchEvent(new Event('change', { bubbles: true }));
      }
      updateCustomSelect(select);
      closeCustomSelect(widget);
      trigger.focus();
    });
    list.addEventListener('keydown', function (event) {
      var items = Array.prototype.filter.call(list.querySelectorAll('.select-option'), function (item) { return !item.disabled && item.getAttribute('aria-selected') !== 'true'; });
      var current = items.indexOf(document.activeElement);
      var next = -1;
      if (event.key === 'ArrowDown') next = (current + 1) % items.length;
      else if (event.key === 'ArrowUp') next = (current - 1 + items.length) % items.length;
      else if (event.key === 'Home') next = 0;
      else if (event.key === 'End') next = items.length - 1;
      else if (event.key === 'Escape') { event.preventDefault(); closeCustomSelect(widget); trigger.focus(); }
      if (next >= 0 && items.length) { event.preventDefault(); items[next].focus(); }
    });
    customSelects.push(select);
  }
  function refreshCustomSelects() {
    customSelects.forEach(function (select) { selectOptions(select, select._customSelect.list); });
  }
  $$('select').forEach(enhanceSelect);
  document.addEventListener('click', function (event) {
    customSelects.forEach(function (select) {
      if (!select._customSelect.root.contains(event.target)) closeCustomSelect(select._customSelect);
    });
    if (event.target.closest('[data-fill]')) setTimeout(refreshCustomSelects, 0);
  });
  document.addEventListener('reset', function (event) {
    setTimeout(function () {
      customSelects.forEach(function (select) {
        if (select.form === event.target) updateCustomSelect(select);
      });
    }, 0);
  }, true);
})();
