// ActivityFlow shared front-end helpers.
(function () {
  'use strict';

  // ---- Sidebar toggle (mobile) ----
  const sidebar = document.getElementById('afSidebar');
  const backdrop = document.getElementById('afBackdrop');
  const toggleBtn = document.getElementById('afSidebarToggle');
  const closeBtn = document.getElementById('afSidebarClose');
  function openSidebar() { sidebar && sidebar.classList.add('show'); backdrop && backdrop.classList.add('show'); }
  function closeSidebar() { sidebar && sidebar.classList.remove('show'); backdrop && backdrop.classList.remove('show'); }
  toggleBtn && toggleBtn.addEventListener('click', openSidebar);
  closeBtn && closeBtn.addEventListener('click', closeSidebar);
  backdrop && backdrop.addEventListener('click', closeSidebar);

  // ---- Fetch wrapper with CSRF + JSON ----
  window.afFetch = function (url, options) {
    options = options || {};
    const headers = Object.assign(
      { 'X-CSRF-Token': window.AF_CSRF, 'Content-Type': 'application/json' },
      options.headers || {}
    );
    const opts = Object.assign({ credentials: 'same-origin' }, options, { headers });
    if (opts.body && typeof opts.body !== 'string') {
      opts.body = JSON.stringify(Object.assign({ csrf_token: window.AF_CSRF }, opts.body));
    }
    return fetch(url, opts).then(async (res) => {
      let data = null;
      try { data = await res.json(); } catch (e) { /* non-JSON response */ }
      // API responses always answer with HTTP 200 (see includes/functions.php::json_response) —
      // success/failure is carried by the `ok` flag in the body, not the transport status. A
      // non-OK transport status here means something outside the app intercepted the response
      // (e.g. a proxy/server error page), so data will typically be null in that case too.
      if (!res.ok || !data || data.ok === false) {
        const message = (data && data.error) ? data.error : 'Something went wrong. Please try again.';
        throw new Error(message);
      }
      return data;
    });
  };

  // ---- Toasts ----
  window.afToast = function (message, type) {
    type = type || 'success';
    let holder = document.getElementById('afToastHolder');
    if (!holder) {
      holder = document.createElement('div');
      holder.id = 'afToastHolder';
      holder.style.cssText = 'position:fixed;bottom:1rem;right:1rem;z-index:2000;display:flex;flex-direction:column;gap:.5rem;';
      document.body.appendChild(holder);
    }
    const el = document.createElement('div');
    el.className = `toast align-items-center text-bg-${type} border-0 show`;
    el.setAttribute('role', 'alert');
    el.innerHTML = `<div class="d-flex"><div class="toast-body">${message}</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
    holder.appendChild(el);
    setTimeout(() => el.remove(), 5000);
  };

  window.afConfirm = function (message) {
    return window.confirm(message);
  };

  // ---- Rich text (progressive enhancement over a plain <textarea>) ----
  // Turns a <textarea> into a Quill WYSIWYG editor while keeping the textarea
  // itself as the real form field (still submitted via its `name` under normal
  // FormData serialization — no submit-handler changes needed anywhere this is
  // used). If the Quill CDN script/CSS failed to load, or anything else goes
  // wrong, this quietly leaves the plain textarea visible and working instead
  // of throwing — a third-party CDN script failing here should never be able
  // to break the form around it (see project_detail.js's Chart.js handling for
  // the same principle).
  window.afInitRichText = function (textareaId) {
    const textarea = document.getElementById(textareaId);
    if (!textarea || typeof Quill === 'undefined') return null;

    try {
      const editorEl = document.createElement('div');
      textarea.insertAdjacentElement('afterend', editorEl);

      const quill = new Quill(editorEl, {
        theme: 'snow',
        modules: {
          toolbar: [
            [{ header: [1, 2, 3, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['blockquote', 'link'],
            ['clean'],
          ],
        },
      });
      quill.root.innerHTML = textarea.value;

      const sync = () => { textarea.value = quill.root.innerHTML; };
      quill.on('text-change', sync);

      const form = textarea.closest('form');
      // Capture phase + belt-and-braces sync in case text-change ever lags a
      // fast programmatic submit.
      form && form.addEventListener('submit', sync, true);

      textarea.classList.add('d-none');
      return quill;
    } catch (err) {
      console.warn('Rich text editor failed to initialize; using plain text area instead.', err);
      return null;
    }
  };

  // ---- Notification bell ----
  const notifList = document.getElementById('afNotifList');
  function loadNotifications() {
    if (!notifList) return;
    afFetch(window.AF_BASE_URL + 'api/notifications.php?action=list&limit=8')
      .then((data) => {
        const items = (data && data.notifications) || [];
        const i18n = window.AF_I18N || {};
        if (!items.length) {
          notifList.innerHTML = `<div class="p-3 text-muted small">${i18n.no_notifications || 'No notifications yet.'}</div>`;
          return;
        }
        notifList.innerHTML = items.map((n) => `
          <div class="notif-item ${n.is_read ? '' : 'unread'}">
            <div class="fw-semibold">${escapeHtml(n.title)}</div>
            ${n.body ? `<div class="text-muted">${escapeHtml(n.body)}</div>` : ''}
            <div class="text-muted" style="font-size:.72rem">${n.created_at}</div>
          </div>`).join('');
      })
      .catch(() => {
        const i18n = window.AF_I18N || {};
        notifList.innerHTML = `<div class="p-3 text-muted small">${i18n.unable_to_load_notifications || 'Unable to load notifications.'}</div>`;
      });
  }
  document.addEventListener('DOMContentLoaded', loadNotifications);

  function escapeHtml(str) {
    const d = document.createElement('div');
    d.innerText = str == null ? '' : String(str);
    return d.innerHTML;
  }
  window.afEscapeHtml = escapeHtml;
})();
