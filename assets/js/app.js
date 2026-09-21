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
    // A FormData body (file uploads — see the paste-image handler in
    // afInitRichText() below) must NOT get 'Content-Type: application/json'
    // or get JSON.stringify'd: the browser needs to set its own
    // 'multipart/form-data; boundary=...' Content-Type itself, which only
    // happens if we don't set one ourselves. CSRF still travels fine via the
    // X-CSRF-Token header either way.
    const isFormData = typeof FormData !== 'undefined' && options.body instanceof FormData;
    const headers = Object.assign(
      isFormData
        ? { 'X-CSRF-Token': window.AF_CSRF }
        : { 'X-CSRF-Token': window.AF_CSRF, 'Content-Type': 'application/json' },
      options.headers || {}
    );
    const opts = Object.assign({ credentials: 'same-origin' }, options, { headers });
    if (isFormData) {
      opts.body = options.body;
    } else if (opts.body && typeof opts.body !== 'string') {
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
  // Registers a "Monospace" option on Quill's built-in Font format (once —
  // Quill.register() on the same format repeatedly is harmless but pointless).
  // This is what powers the Font dropdown's "Monospace" choice below: every
  // character comes from a fixed-width typeface, so letters, digits, and
  // punctuation/symbols all render at the same width — exactly what makes
  // pasted code, logs, or aligned/tabular text line up correctly instead of
  // the proportional body font (where e.g. "i" and "m" differ in width)
  // throwing the alignment off.
  let richTextFontRegistered = false;
  function ensureRichTextFontRegistered() {
    if (richTextFontRegistered) return;
    try {
      const Font = Quill.import('formats/font');
      Font.whitelist = ['monospace'];
      Quill.register(Font, true);
    } catch (err) {
      console.warn('Could not register the monospace font option for rich text editors.', err);
    }
    richTextFontRegistered = true;
  }

  // `opts.wrapToggle: true` adds a small "Wrap text" switch above the editor
  // (off by default is NOT the default — wrapping starts ON, matching normal
  // text behavior). Switching it off stops long lines from reflowing and lets
  // the editor scroll horizontally instead, so content whose original layout
  // matters (pasted logs, code, ASCII tables/diagrams) stays exactly as
  // pasted and can be read by scrolling rather than being squashed by
  // word-wrap. See .af-richtext-nowrap in app.css.
  //
  // `opts.allowImagePaste: true` lets the user paste an image straight from
  // the clipboard (a screenshot, typically) into the editor. Quill's own
  // default behavior for a pasted image is to embed it as a giant base64
  // data: URL right in the document — great for a demo, terrible for a
  // database column that gets fetched on every page load — so this
  // intercepts the paste ourselves, uploads the image to the server (where
  // it's downscaled/re-encoded — see process_description_image_upload() in
  // includes/models/description_images.php) and inserts a normal <img
  // src="..."> pointing at the stored file instead. The
  // quill-image-resize-module CDN script (loaded alongside Quill on pages
  // that need this — see team_activities.php etc.) adds the actual
  // click-and-drag resize handles once an image is inserted; if that script
  // didn't load for some reason, the image still pastes in and displays
  // fine, it just can't be resized by hand.
  window.afInitRichText = function (textareaId, opts) {
    opts = opts || {};
    const textarea = document.getElementById(textareaId);
    if (!textarea || typeof Quill === 'undefined') return null;

    try {
      ensureRichTextFontRegistered();
      const wrapperEl = document.createElement('div');
      wrapperEl.className = 'af-richtext-wrapper';
      textarea.insertAdjacentElement('afterend', wrapperEl);

      if (opts.wrapToggle) {
        const i18n = window.AF_I18N || {};
        const toggleId = textareaId + '_wraptoggle';
        const toggleEl = document.createElement('div');
        toggleEl.className = 'form-check form-switch af-richtext-wraptoggle';
        toggleEl.innerHTML = `<input class="form-check-input" type="checkbox" role="switch" id="${toggleId}" checked>
          <label class="form-check-label small text-muted" for="${toggleId}" title="${escapeHtml(i18n.richtext_wrap_text_hint || '')}">${escapeHtml(i18n.richtext_wrap_text || 'Wrap text')}</label>`;
        toggleEl.querySelector('input').addEventListener('change', function () {
          wrapperEl.classList.toggle('af-richtext-nowrap', !this.checked);
        });

        // Put the switch on the same line as the field's own <label>
        // (e.g. "Description") rather than tucked in above the toolbar,
        // where it reads as if it belongs to a different field entirely.
        // Falls back to stacking above the editor if there's no adjacent
        // <label> to share a row with.
        const fieldLabel = textarea.previousElementSibling;
        if (fieldLabel && fieldLabel.tagName === 'LABEL') {
          const labelRow = document.createElement('div');
          labelRow.className = 'd-flex align-items-center justify-content-between flex-wrap gap-2';
          fieldLabel.parentNode.insertBefore(labelRow, fieldLabel);
          labelRow.appendChild(fieldLabel);
          labelRow.appendChild(toggleEl);
        } else {
          toggleEl.classList.add('mb-1');
          wrapperEl.appendChild(toggleEl);
        }
      }

      const editorEl = document.createElement('div');
      wrapperEl.appendChild(editorEl);

      const modules = {
        toolbar: [
          [{ font: ['monospace'] }, { header: [1, 2, 3, false] }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ list: 'ordered' }, { list: 'bullet' }],
          ['blockquote', 'link'],
          ['clean'],
        ],
      };
      // Only wire in the resize module if its CDN script actually loaded and
      // self-registered (see the comment above) — referencing an unregistered
      // module name would throw when Quill starts up, taking the *whole*
      // editor down with it (caught below, falling back to a plain textarea)
      // over what should only cost this one optional capability.
      if (opts.allowImagePaste && Quill.import('modules/imageResize')) {
        modules.imageResize = { modules: ['Resize', 'DisplaySize'] };
      }

      const quill = new Quill(editorEl, { theme: 'snow', modules });
      quill.root.innerHTML = textarea.value;

      const sync = () => { textarea.value = quill.root.innerHTML; };
      quill.on('text-change', sync);

      if (opts.allowImagePaste) {
        const i18n = window.AF_I18N || {};
        const hintEl = document.createElement('div');
        hintEl.className = 'form-text';
        hintEl.textContent = i18n.richtext_paste_image_hint || 'Tip: paste an image from your clipboard to add it here.';
        wrapperEl.appendChild(hintEl);

        // Capture phase so this runs — and can preventDefault/stopPropagation
        // — before Quill's own paste handler (bound in the bubble phase on
        // this same element) ever sees the event.
        quill.root.addEventListener('paste', function (e) {
          const items = e.clipboardData && e.clipboardData.items;
          if (!items) return;
          let imageItem = null;
          for (let i = 0; i < items.length; i++) {
            if (items[i].type && items[i].type.indexOf('image/') === 0) { imageItem = items[i]; break; }
          }
          if (!imageItem) return; // no image on the clipboard — let Quill handle the paste normally

          e.preventDefault();
          e.stopPropagation();
          const file = imageItem.getAsFile();
          if (!file) return;

          const range = quill.getSelection(true);
          const insertIndex = range ? range.index : quill.getLength();
          const formData = new FormData();
          formData.append('action', 'upload_description_image');
          formData.append('image', file, file.name || 'pasted-image.png');

          afFetch(window.AF_BASE_URL + 'api/activities.php', { method: 'POST', body: formData })
            .then((res) => {
              quill.insertEmbed(insertIndex, 'image', res.url, 'user');
              const imgEl = quill.root.querySelector('img[src="' + res.url + '"]');
              if (imgEl) imgEl.setAttribute('alt', i18n.richtext_pasted_image_alt || 'Pasted image');
              quill.setSelection(insertIndex + 1, 0, 'user');
              sync();
            })
            .catch((err) => afToast(err.message, 'danger'));
        }, true);
      }

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
        notifList.innerHTML = items.map((n) => {
          // account_pending_approval notifications are only ever sent to
          // administrators (see notify_admins_of_pending_account() in
          // includes/models/account_approvals.php), so this link is always
          // safe to show without a separate role check here.
          const titleHtml = n.type === 'account_pending_approval'
            ? `<a href="${window.AF_BASE_URL}admin/account_approvals.php" class="text-reset">${escapeHtml(n.title)}</a>`
            : escapeHtml(n.title);
          return `
          <div class="notif-item ${n.is_read ? '' : 'unread'}">
            <div class="fw-semibold">${titleHtml}</div>
            ${n.body ? `<div class="text-muted">${escapeHtml(n.body)}</div>` : ''}
            <div class="text-muted" style="font-size:.72rem">${n.created_at}</div>
          </div>`;
        }).join('');
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
              ${avatarMarkup(u.avatar_url, u.full_name, 22)}
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

  // Mirrors avatar_html() in includes/models/avatars.php: a circular photo
  // when one's available, otherwise the same initials-circle fallback used
  // everywhere else (.af-avatar) — so server-rendered and client-rendered
  // avatars always look identical.
  function avatarMarkup(url, name, sizePx) {
    sizePx = sizePx || 28;
    if (url) {
      return `<img src="${escapeHtml(url)}" alt="${escapeHtml(name)}" class="af-avatar-photo" style="width:${sizePx}px;height:${sizePx}px" loading="lazy">`;
    }
    const fontPx = Math.max(10, Math.round(sizePx * 0.4));
    const initial = (name || '').slice(0, 1);
    return `<span class="af-avatar" style="width:${sizePx}px;height:${sizePx}px;font-size:${fontPx}px">${escapeHtml(initial)}</span>`;
  }
  window.afAvatarMarkup = avatarMarkup;

  // ---- Attachments ("Supporting documents") ----
  // Shared by the Edit Project modal (assets/js/project_detail.js) and the
  // Edit Activity dialog's Attachments tab (assets/js/activities.js) — both
  // wire a file input + a list container to api/attachments.php through this
  // one implementation rather than duplicating render/upload/delete logic.
  function formatFileSize(bytes) {
    bytes = Number(bytes) || 0;
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (Math.round((bytes / 1024) * 10) / 10) + ' KB';
    return (Math.round((bytes / (1024 * 1024)) * 10) / 10) + ' MB';
  }

  function renderAttachmentList(listEl, attachments, canManage, onDelete) {
    const i18n = window.AF_I18N || {};
    if (!attachments.length) {
      listEl.innerHTML = `<p class="text-muted small mb-0">${escapeHtml(i18n.attachments_empty || 'No files uploaded yet.')}</p>`;
      return;
    }
    listEl.innerHTML = attachments.map((a) => {
      const url = window.AF_BASE_URL + 'api/attachments.php?action=download&id=' + encodeURIComponent(a.id);
      const metaParts = [formatFileSize(a.size_bytes)];
      if (a.uploaded_by_name) metaParts.push(escapeHtml(a.uploaded_by_name));
      if (a.created_at) metaParts.push(escapeHtml(a.created_at));
      return `<div class="d-flex justify-content-between align-items-center border rounded p-2 mb-1 small af-attachment-item">
        <div class="text-truncate me-2">
          <a href="${escapeHtml(url)}" target="_blank" rel="noopener"><i class="bi bi-paperclip"></i> ${escapeHtml(a.original_filename)}</a>
          <div class="text-muted">${metaParts.join(' · ')}</div>
        </div>
        ${canManage ? `<button type="button" class="btn btn-sm btn-link text-danger p-0 flex-shrink-0" data-attachment-id="${a.id}" title="${escapeHtml(i18n.attachments_delete || 'Delete')}"><i class="bi bi-trash3"></i></button>` : ''}
      </div>`;
    }).join('');
    if (canManage) {
      listEl.querySelectorAll('button[data-attachment-id]').forEach((btn) => {
        btn.addEventListener('click', function () { onDelete(this.dataset.attachmentId); });
      });
    }
  }

  /**
   * Wires a "Supporting documents" widget: a list container showing existing
   * files (download links, and delete buttons when canManage), plus an
   * optional file input for uploading more. entityId is read via
   * opts.getEntityId() at call time (not captured once) because the Edit
   * Activity dialog reuses the same DOM elements across different tasks —
   * see refresh() being called fresh from fillForm() on every open. Returns
   * { refresh } so the caller can (re)load the list whenever the relevant
   * project/task changes or the modal opens.
   */
  window.afInitAttachments = function (opts) {
    const listEl = opts.listEl;
    const inputEl = opts.uploadInputEl || null;
    if (!listEl) return null;
    const i18n = window.AF_I18N || {};

    function canManage() {
      return typeof opts.canManage === 'function' ? !!opts.canManage() : !!opts.canManage;
    }

    function refresh() {
      const entityId = opts.getEntityId();
      if (!entityId) { listEl.innerHTML = ''; return; }
      afFetch(window.AF_BASE_URL + 'api/attachments.php?action=list&entity_type=' + opts.entityType + '&entity_id=' + encodeURIComponent(entityId))
        .then((res) => renderAttachmentList(listEl, res.attachments || [], canManage(), handleDelete))
        .catch((err) => { listEl.innerHTML = `<p class="text-danger small mb-0">${escapeHtml(err.message)}</p>`; });
    }

    // Called after a successful delete or upload, in addition to re-rendering
    // this widget's own list — lets a caller that renders the SAME entity's
    // files in a second, separate widget (e.g. project_detail.js's read-only
    // page-body list alongside the Edit Project modal's manageable one) keep
    // that sibling list in sync too, without a full page reload.
    function notifyChanged() {
      if (typeof opts.onChange === 'function') opts.onChange();
    }

    function handleDelete(id) {
      if (!window.afConfirm(i18n.attachments_confirm_delete || 'Delete this file? This cannot be undone.')) return;
      afFetch(window.AF_BASE_URL + 'api/attachments.php', { method: 'POST', body: { action: 'delete', id: id } })
        .then((res) => { renderAttachmentList(listEl, res.attachments || [], canManage(), handleDelete); notifyChanged(); })
        .catch((err) => afToast(err.message, 'danger'));
    }

    if (inputEl) {
      inputEl.addEventListener('change', function () {
        const entityId = opts.getEntityId();
        const files = Array.prototype.slice.call(inputEl.files || []);
        if (!entityId || !files.length) return;
        const formData = new FormData();
        formData.append('action', 'upload');
        formData.append('entity_type', opts.entityType);
        formData.append('entity_id', entityId);
        files.forEach((f) => formData.append('files[]', f, f.name));
        afFetch(window.AF_BASE_URL + 'api/attachments.php', { method: 'POST', body: formData })
          .then((res) => {
            renderAttachmentList(listEl, res.attachments || [], canManage(), handleDelete);
            notifyChanged();
            if (res.errors && res.errors.length) {
              afToast(res.errors.join(' '), 'danger');
            } else if (res.uploaded_count) {
              const template = res.uploaded_count === 1
                ? (i18n.attachments_uploaded_one || 'File uploaded.')
                : (i18n.attachments_uploaded_many || '{count} files uploaded.');
              afToast(template.replace('{count}', String(res.uploaded_count)), 'success');
            }
          })
          .catch((err) => afToast(err.message, 'danger'))
          .finally(() => { inputEl.value = ''; });
      });
    }

    return { refresh: refresh };
  };
})();
