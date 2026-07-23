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

  // ---- Notification bell ----
  const notifList = document.getElementById('afNotifList');
  function loadNotifications() {
    if (!notifList) return;
    afFetch(window.AF_BASE_URL + 'api/notifications.php?action=list&limit=8')
      .then((data) => {
        const items = (data && data.notifications) || [];
        if (!items.length) {
          notifList.innerHTML = '<div class="p-3 text-muted small">No notifications yet.</div>';
          return;
        }
        notifList.innerHTML = items.map((n) => `
          <div class="notif-item ${n.is_read ? '' : 'unread'}">
            <div class="fw-semibold">${escapeHtml(n.title)}</div>
            ${n.body ? `<div class="text-muted">${escapeHtml(n.body)}</div>` : ''}
            <div class="text-muted" style="font-size:.72rem">${n.created_at}</div>
          </div>`).join('');
      })
      .catch(() => { notifList.innerHTML = '<div class="p-3 text-muted small">Unable to load notifications.</div>'; });
  }
  document.addEventListener('DOMContentLoaded', loadNotifications);

  function escapeHtml(str) {
    const d = document.createElement('div');
    d.innerText = str == null ? '' : String(str);
    return d.innerHTML;
  }
  window.afEscapeHtml = escapeHtml;
})();
