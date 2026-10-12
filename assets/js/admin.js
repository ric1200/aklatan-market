/* =============================================================================
   Aklatan Market - admin script (progressive enhancement only)
   ========================================================================== */
(function () {
  'use strict';
  var doc = document;
  function $$(s, c) { return Array.prototype.slice.call((c || doc).querySelectorAll(s)); }
  function on(el, e, fn) { if (el) { el.addEventListener(e, fn); } }

  /* Sidebar on small screens */
  var toggle = doc.querySelector('[data-admin-menu]');
  var sidebar = doc.querySelector('.admin-sidebar');
  on(toggle, 'click', function () {
    if (!sidebar) { return; }
    var open = sidebar.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  /* Confirmation for destructive submissions */
  $$('form[data-confirm]').forEach(function (form) {
    on(form, 'submit', function (e) {
      if (!window.confirm(form.getAttribute('data-confirm'))) { e.preventDefault(); }
    });
  });
  $$('[data-confirm-link]').forEach(function (link) {
    on(link, 'click', function (e) {
      if (!window.confirm(link.getAttribute('data-confirm-link'))) { e.preventDefault(); }
    });
  });

  /* Live filter for short tables (no server round trip) */
  $$('[data-table-filter]').forEach(function (input) {
    var table = doc.getElementById(input.getAttribute('data-table-filter'));
    if (!table) { return; }
    on(input, 'input', function () {
      var needle = input.value.trim().toLowerCase();
      $$('tbody tr', table).forEach(function (row) {
        row.hidden = needle !== '' && row.textContent.toLowerCase().indexOf(needle) === -1;
      });
    });
  });

  /* Image previews */
  $$('input[type="file"][data-preview]').forEach(function (input) {
    on(input, 'change', function () {
      var target = doc.getElementById(input.getAttribute('data-preview'));
      if (!target || !input.files || !input.files[0]) { return; }
      var reader = new FileReader();
      reader.onload = function (e) { target.src = e.target.result; target.hidden = false; };
      reader.readAsDataURL(input.files[0]);
    });
  });

  /* Slug suggestions from the product name */
  var nameInput = doc.getElementById('name');
  var slugInput = doc.getElementById('slug');
  if (nameInput && slugInput) {
    var touched = slugInput.value !== '';
    on(slugInput, 'input', function () { touched = true; });
    on(nameInput, 'input', function () {
      if (touched) { return; }
      slugInput.value = nameInput.value.toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '').trim()
        .replace(/\s+/g, '-').replace(/-+/g, '-').slice(0, 200);
    });
  }

  /* Bulk price/stock calculations on the reports page */
  $$('[data-print]').forEach(function (btn) {
    on(btn, 'click', function () { window.print(); });
  });

  /* Payment proof lightbox: reuse the shared modal in main.js by clicking the
     hidden trigger, so there is only one modal implementation. */
  $$('[data-proof]').forEach(function (thumb) {
    on(thumb, 'click', function () {
      var modal = doc.getElementById('proof-modal');
      if (!modal) { return; }
      var img = doc.getElementById('proof-modal-image');
      if (img) { img.src = thumb.getAttribute('data-proof'); }
      modal.hidden = false;
      var closeBtn = modal.querySelector('[data-modal-close]');
      if (closeBtn) { closeBtn.focus(); }
      doc.body.style.overflow = 'hidden';
    });
  });
})();
