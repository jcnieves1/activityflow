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

  // ---- Global "server is busy" loading overlay ----
  // A single blocking overlay (markup lives in includes/layout_footer.php, on
  // every page) shown for the duration of any in-flight server request, so
  // the user gets a clear "please wait" signal and can't click/tab into
  // anything else while it's outstanding. Uses a counter rather than a
  // boolean because more than one request can be in flight at once (e.g. a
  // background notification poll overlapping a manual save) — the overlay
  // should only hide once ALL of them have finished. A short show-delay
  // avoids an annoying flash for requests that resolve almost instantly.
  const SHOW_DELAY_MS = 150;
  let afLoadingCount = 0;
  let afLoadingShowTimer = null;
  const afLoadingOverlay = document.getElementById('afLoadingOverlay');

  window.afLoadingShow = function () {
    afLoadingCount++;
    if (afLoadingCount === 1 && afLoadingOverlay) {
      clearTimeout(afLoadingShowTimer);
      afLoadingShowTimer = setTimeout(() => {
        if (afLoadingCount > 0) {
          afLoadingOverlay.classList.add('show');
          afLoadingOverlay.setAttribute('aria-hidden', 'false');
        }
      }, SHOW_DELAY_MS);
    }
  };
  window.afLoadingHide = function () {
    afLoadingCount = Math.max(0, afLoadingCount - 1);
    if (afLoadingCount === 0 && afLoadingOverlay) {
      clearTimeout(afLoadingShowTimer);
      afLoadingOverlay.classList.remove('show');
      afLoadingOverlay.setAttribute('aria-hidden', 'true');
    }
  };

  // Plain, non-AJAX forms (login, register, forgot/reset password, profile,
  // the GET-based board filter form, etc.) navigate the whole page on submit
  // rather than going through afFetch, so they need their own trigger for the
  // overlay. This listener runs in the bubble phase, which — per the DOM
  // event dispatch order — always fires AFTER any listener attached directly
  // to the form itself (those run during the "target phase", before bubbling
  // reaches ancestors like document). So by the time this runs,
  // e.defaultPrevented already reflects whether some other script called
  // preventDefault() to handle the submission itself (e.g. an AJAX form like
  // "Edit Project", which manages the overlay via its own afFetch call) —
  // this listener only acts on forms nobody else intercepted, so it can never
  // double up with or fight afFetch's own show/hide. No matching hide() call
  // is needed here: since preventDefault() was NOT called, the browser is
  // about to navigate away, which discards this page (and the overlay with
  // it) regardless.
  document.addEventListener('submit', function (e) {
    if (e.defaultPrevented) return;
    if (e.target && e.target.tagName === 'FORM') {
      window.afLoadingShow();
    }
  });

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
    window.afLoadingShow();
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
      // Every afFetch call represents the user doing something in the app
      // (saving, filtering, opening a task, etc.) — piggyback the online
      // presence widget's refresh on that instead of polling on a timer. See
      // notifyUserActivity() below; it excludes the presence endpoint itself
      // to avoid refreshing in response to its own request.
      if (typeof url === 'string' && url.indexOf('presence.php') === -1) {
        window.afNotifyUserActivity && window.afNotifyUserActivity();
      }
      return data;
    }).finally(() => window.afLoadingHide());
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

  // ---- Online presence widget ----
  // Who else currently has the app open, approximated by recency of
  // activity (see includes/models/presence.php). The topbar already
  // server-renders the current list on page load (includes/layout_header.php),
  // so there's no need to re-fetch immediately. Rather than polling on a
  // timer, it's refreshed opportunistically whenever the user does something
  // that hits the server — see afNotifyUserActivity()/afFetch() below —
  // which keeps the count reasonably fresh without any background requests
  // while a tab just sits idle.
  const onlineList = document.getElementById('afOnlineList');
  const onlineCountEl = document.getElementById('afOnlineCount');
  function loadPresence() {
    if (!onlineList && !onlineCountEl) return;
    afFetch(window.AF_BASE_URL + 'api/presence.php?action=list')
      .then((data) => {
        const users = (data && data.users) || [];
        const i18n = window.AF_I18N || {};
        if (onlineCountEl) {
          const template = i18n.online_count || 'Online ({count})';
          onlineCountEl.textContent = template.replace('{count}', String(users.length));
        }
        if (onlineList) {
          if (!users.length) {
            onlineList.innerHTML = `<div class="p-3 text-muted small">${i18n.no_one_online || 'No one is online right now.'}</div>`;
            return;
          }
          onlineList.innerHTML = users.map((u) => `
            <div class="af-online-item">
              <span class="af-status-dot af-status-dot-online"></span>
              <span>${escapeHtml(u.full_name)}${u.is_self ? ' ' + escapeHtml(i18n.you_suffix || '(You)') : ''}</span>
            </div>`).join('');
        }
      })
      .catch(() => {
        const i18n = window.AF_I18N || {};
        if (onlineList) onlineList.innerHTML = `<div class="p-3 text-muted small">${i18n.unable_to_load_online || 'Unable to load online users.'}</div>`;
      });
  }
  // Throttled trigger called from afFetch() on every successful request (any
  // save, filter, task open, etc. counts as "the user performed an action").
  // Throttled to once per 10s so a burst of several requests in quick
  // succession (e.g. a page that fires off a few fetches on load) only
  // refreshes the widget once, rather than once per request.
  const PRESENCE_REFRESH_THROTTLE_MS = 10000;
  let lastPresenceRefreshAt = 0;
  window.afNotifyUserActivity = function () {
    if (!onlineList && !onlineCountEl) return;
    const now = Date.now();
    if (now - lastPresenceRefreshAt < PRESENCE_REFRESH_THROTTLE_MS) return;
    lastPresenceRefreshAt = now;
    loadPresence();
  };

  function escapeHtml(str) {
    const d = document.createElement('div');
    d.innerText = str == null ? '' : String(str);
    return d.innerHTML;
  }
  window.afEscapeHtml = escapeHtml;
})();
