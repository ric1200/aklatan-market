/* =============================================================================
   Aklatan Market - storefront script
   Plain JavaScript. Everything here is progressive enhancement: the store is
   fully usable with JavaScript disabled.
   ========================================================================== */
(function () {
  'use strict';

  var doc = document;
  var $ = function (sel, ctx) { return (ctx || doc).querySelector(sel); };
  var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || doc).querySelectorAll(sel)); };

  /* ------------------------------------------------------------ helpers -- */
  function on(el, evt, fn) { if (el) { el.addEventListener(evt, fn); } }

  function toast(message, type) {
    var host = $('#toast-host');
    if (!host) {
      host = doc.createElement('div');
      host.id = 'toast-host';
      host.className = 'toast-host';
      host.setAttribute('role', 'status');
      host.setAttribute('aria-live', 'polite');
      doc.body.appendChild(host);
    }
    var item = doc.createElement('div');
    item.className = 'toast toast-' + (type || 'success');
    item.textContent = message;
    host.appendChild(item);
    window.setTimeout(function () { item.classList.add('is-out'); }, 3600);
    window.setTimeout(function () { if (item.parentNode) { item.parentNode.removeChild(item); } }, 4200);
  }

  function updateCartCount(count) {
    if (typeof count !== 'number') { return; }
    $$('.count-badge').forEach(function (badge) {
      if (badge.closest('.icon-link') && badge.closest('.icon-link').getAttribute('href') &&
          badge.closest('.icon-link').getAttribute('href').indexOf('cart') === -1) { return; }
      badge.textContent = String(count);
      badge.classList.toggle('is-empty', count === 0);
    });
  }

  /* ------------------------------------------------ mobile navigation ---- */
  var navToggle = $('[data-nav-toggle]');
  var mobileNav = $('[data-mobile-nav]');
  on(navToggle, 'click', function () {
    var open = navToggle.getAttribute('aria-expanded') === 'true';
    navToggle.setAttribute('aria-expanded', open ? 'false' : 'true');
    if (mobileNav) { mobileNav.classList.toggle('is-open', !open); }
  });

  /* --------------------------------------------------- account dropdown -- */
  var accountToggle = $('.account-toggle');
  var accountPanel = $('.account-panel');
  if (accountToggle && accountPanel) {
    on(accountToggle, 'click', function (e) {
      e.stopPropagation();
      var open = accountToggle.getAttribute('aria-expanded') === 'true';
      accountToggle.setAttribute('aria-expanded', open ? 'false' : 'true');
      accountPanel.hidden = open;
    });
    doc.addEventListener('click', function (e) {
      if (!accountPanel.hidden && !accountPanel.contains(e.target) && e.target !== accountToggle) {
        accountPanel.hidden = true;
        accountToggle.setAttribute('aria-expanded', 'false');
      }
    });
    doc.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !accountPanel.hidden) {
        accountPanel.hidden = true;
        accountToggle.setAttribute('aria-expanded', 'false');
        accountToggle.focus();
      }
    });
  }

  /* ------------------------------------------------- quantity steppers --- */
  $$('[data-qty]').forEach(function (wrap) {
    var input = $('input', wrap);
    if (!input) { return; }
    var max = parseInt(input.getAttribute('max'), 10) || 99;
    var min = parseInt(input.getAttribute('min'), 10) || 1;
    $$('button[data-step]', wrap).forEach(function (btn) {
      on(btn, 'click', function () {
        var step = parseInt(btn.getAttribute('data-step'), 10) || 1;
        var next = (parseInt(input.value, 10) || min) + step;
        input.value = Math.max(min, Math.min(max, next));
        input.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
    on(input, 'change', function () {
      var v = parseInt(input.value, 10);
      if (isNaN(v) || v < min) { v = min; }
      if (v > max) { v = max; }
      input.value = v;
    });
  });

  /* ------------------------------------------------ AJAX add to cart ----- */
  $$('form[data-ajax-cart]').forEach(function (form) {
    on(form, 'submit', function (e) {
      if (!window.fetch || !window.FormData) { return; }
      e.preventDefault();
      var button = $('button[type="submit"]', form);
      var original = button ? button.innerHTML : '';
      if (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); }

      fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      }).then(function (r) {
        return r.json();
      }).then(function (data) {
        if (data && typeof data.count === 'number') { updateCartCount(data.count); }
        toast((data && data.message) || 'Done.', data && data.ok ? 'success' : 'error');
      }).catch(function () {
        form.submit();
      }).then(function () {
        if (button) { button.disabled = false; button.removeAttribute('aria-busy'); button.innerHTML = original; }
      });
    });
  });

  /* ---------------------------------------------- wishlist heart toggle -- */
  $$('form[data-wishlist-toggle]').forEach(function (form) {
    on(form, 'submit', function (e) {
      if (!window.fetch) { return; }
      e.preventDefault();
      fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      }).then(function (r) { return r.json(); }).then(function (data) {
        var btn = $('.wish-toggle', form);
        if (btn) { btn.classList.toggle('is-on', !!data.on); }
        toast(data.message || 'Wishlist updated.', data.ok ? 'success' : 'info');
      }).catch(function () { form.submit(); });
    });
  });

  /* ------------------------------------------------------------- modal --- */
  var lastFocused = null;
  function openModal(id) {
    var backdrop = doc.getElementById(id);
    if (!backdrop) { return; }
    lastFocused = doc.activeElement;
    backdrop.hidden = false;
    var focusable = $$('button, [href], input, select, textarea', backdrop).filter(function (el) { return !el.disabled; });
    (focusable[0] || backdrop).focus();
    doc.body.style.overflow = 'hidden';
  }
  function closeModal(backdrop) {
    if (!backdrop) { return; }
    backdrop.hidden = true;
    doc.body.style.overflow = '';
    if (lastFocused && lastFocused.focus) { lastFocused.focus(); }
  }
  $$('[data-modal-open]').forEach(function (btn) {
    on(btn, 'click', function (e) { e.preventDefault(); openModal(btn.getAttribute('data-modal-open')); });
  });
  $$('.modal-backdrop').forEach(function (backdrop) {
    on(backdrop, 'click', function (e) { if (e.target === backdrop) { closeModal(backdrop); } });
    $$('[data-modal-close]', backdrop).forEach(function (btn) {
      on(btn, 'click', function () { closeModal(backdrop); });
    });
    on(backdrop, 'keydown', function (e) {
      if (e.key === 'Escape') { closeModal(backdrop); return; }
      if (e.key !== 'Tab') { return; }
      var focusable = $$('button, [href], input, select, textarea', backdrop).filter(function (el) { return !el.disabled && el.offsetParent !== null; });
      if (!focusable.length) { return; }
      var first = focusable[0];
      var last = focusable[focusable.length - 1];
      if (e.shiftKey && doc.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && doc.activeElement === last) { e.preventDefault(); first.focus(); }
    });
  });

  /* --------------------------------------------- checkout interactions -- */
  var payOptions = $$('input[name="payment_method"]');
  function syncPaymentDetails() {
    $$('[data-pay-detail]').forEach(function (block) {
      var match = payOptions.some(function (opt) { return opt.checked && opt.value === block.getAttribute('data-pay-detail'); });
      block.classList.toggle('is-shown', match);
    });
    var submitLabel = $('#place-order-label');
    var chosen = payOptions.filter(function (o) { return o.checked; })[0];
    if (submitLabel && chosen) {
      submitLabel.textContent = chosen.value === 'cod'
        ? 'Place order'
        : (chosen.value === 'pickup' ? 'Place pickup order' : 'Place order and send payment details');
    }
  }
  payOptions.forEach(function (opt) { on(opt, 'change', syncPaymentDetails); });
  if (payOptions.length) { syncPaymentDetails(); }

  var addressChoice = $$('input[name="address_id"]');
  function syncAddressFields() {
    var wrap = $('#new-address-fields');
    if (!wrap) { return; }
    var isNew = addressChoice.some(function (r) { return r.checked && r.value === 'new'; }) || !addressChoice.length;
    wrap.hidden = !isNew;
  }
  addressChoice.forEach(function (r) { on(r, 'change', syncAddressFields); });
  if (addressChoice.length) { syncAddressFields(); }

  /* -------------------------------------------- mobile filter disclosure -- */
  var filterToggle = $('.filter-toggle');
  var filterPanel = $('.filter-panel');
  if (filterToggle && filterPanel) {
    filterToggle.setAttribute('aria-expanded', 'false');
    on(filterToggle, 'click', function () {
      var open = filterPanel.classList.toggle('is-open');
      filterToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  /* ------------------------------------------------ auto-submit selects -- */
  $$('[data-autosubmit]').forEach(function (select) {
    on(select, 'change', function () { if (select.form) { select.form.submit(); } });
  });

  /* -------------------------------------------------- image file preview -- */
  $$('input[type="file"][data-preview]').forEach(function (input) {
    on(input, 'change', function () {
      var target = doc.getElementById(input.getAttribute('data-preview'));
      if (!target || !input.files || !input.files[0]) { return; }
      var reader = new FileReader();
      reader.onload = function (e) { target.src = e.target.result; target.hidden = false; };
      reader.readAsDataURL(input.files[0]);
    });
  });

  /* --------------------------------------------------------- copy button -- */
  $$('[data-copy]').forEach(function (btn) {
    on(btn, 'click', function () {
      var text = btn.getAttribute('data-copy');
      var done = function () { toast('Copied to clipboard.', 'success'); };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done, function () { toast('Copy failed — please select the text manually.', 'error'); });
      } else {
        toast('Copy is not supported in this browser.', 'info');
      }
    });
  });

  /* ------------------------------------------------- destructive actions -- */
  $$('[data-confirm]').forEach(function (el) {
    on(el, 'submit', function (e) {
      if (!window.confirm(el.getAttribute('data-confirm'))) { e.preventDefault(); }
    });
  });

  /* ------------------------------------------------ announce page update -- */
  var liveRegion = doc.getElementById('sr-announcer');
  if (liveRegion && $$('.notice').length) {
    liveRegion.textContent = $$('.notice')[0].textContent.trim();
  }
})();
