(function () {
  // Persist the status filter locally (per project, per browser/PC) so it's
  // still applied the next time this board is opened, without needing a
  // server-side "saved view" concept. We piggyback on the existing GET-based
  // filter form/query-string mechanism (project_board.php already reads
  // $_GET['statuses'] and pre-checks the boxes) rather than duplicating the
  // filtering logic in JS:
  //   1. On every filter form submit, save the checked statuses (or [] for
  //      "All statuses") to localStorage, keyed by project id.
  //   2. On page load, if the URL has no `statuses[]` param at all (i.e. the
  //      user just navigated here plainly, not via an explicit filtered
  //      link), and a non-empty saved preference exists, redirect once to
  //      the same URL with those statuses appended — the server then
  //      renders the filtered board and pre-checks the right boxes.
  // An explicit "statuses[]" in the URL always wins over the saved value, and
  // an explicitly-saved "All statuses" (empty array) never triggers a
  // redirect, so there's no possibility of a redirect loop.
  (function persistStatusFilter() {
    const params = new URLSearchParams(window.location.search);
    const projectId = params.get('id');
    if (!projectId) return;
    const storageKey = 'af_board_statuses_' + projectId;

    if (!params.has('statuses[]')) {
      let saved = null;
      try { saved = localStorage.getItem(storageKey); } catch (e) { /* storage unavailable (private mode, etc.) */ }
      if (saved !== null) {
        try {
          const list = JSON.parse(saved);
          if (Array.isArray(list) && list.length) {
            list.forEach((s) => params.append('statuses[]', s));
            window.location.search = params.toString();
            return; // navigation is about to happen; skip the rest of setup
          }
        } catch (e) { /* malformed value from an older version — ignore */ }
      }
    }

    const filterForm = document.getElementById('boardFilterForm');
    if (filterForm) {
      filterForm.addEventListener('submit', function () {
        const checked = Array.from(document.querySelectorAll('.board-status-checkbox:checked')).map((cb) => cb.value);
        try { localStorage.setItem(storageKey, JSON.stringify(checked)); } catch (e) { /* storage unavailable */ }
      });
    }
  })();

  // Compact view: a pure display toggle (hide everything on a card but its
  // title via CSS — see .af-board-compact in app.css) that's global across
  // every project's board, not per-project, since it's a personal density
  // preference rather than a filter. Cards, drag-and-drop, click-to-open,
  // and the member/status filters are all untouched — this only ever adds
  // or removes a class on the board container plus toggles the Bootstrap
  // Tooltip instances that expose the hidden info on hover.
  (function initCompactView() {
    const STORAGE_KEY = 'af_board_compact';
    const toggle = document.getElementById('boardCompactToggle');
    const board = document.querySelector('.af-board');
    if (!toggle || !board) return;

    let tooltips = [];
    function enableTooltips() {
      if (tooltips.length || typeof bootstrap === 'undefined') return;
      document.querySelectorAll('.af-activity-item[data-bs-toggle="tooltip"]').forEach((el) => {
        tooltips.push(new bootstrap.Tooltip(el));
      });
    }
    function disableTooltips() {
      tooltips.forEach((t) => t.dispose());
      tooltips = [];
    }

    function apply(isCompact) {
      board.classList.toggle('af-board-compact', isCompact);
      toggle.checked = isCompact;
      if (isCompact) {
        enableTooltips();
      } else {
        disableTooltips();
      }
    }

    let saved = null;
    try { saved = localStorage.getItem(STORAGE_KEY); } catch (e) { /* storage unavailable (private mode, etc.) */ }
    apply(saved === '1');

    toggle.addEventListener('change', function () {
      apply(toggle.checked);
      try { localStorage.setItem(STORAGE_KEY, toggle.checked ? '1' : '0'); } catch (e) { /* storage unavailable */ }
    });
  })();

  let draggedId = null;

  document.addEventListener('dragstart', function (e) {
    const card = e.target.closest('.af-activity-item');
    if (!card) return;
    draggedId = card.dataset.id;
    card.classList.add('dragging');
  });
  document.addEventListener('dragend', function (e) {
    const card = e.target.closest('.af-activity-item');
    card && card.classList.remove('dragging');
  });

  document.querySelectorAll('.af-dropzone').forEach((zone) => {
    zone.addEventListener('dragover', (e) => { e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
    zone.addEventListener('drop', (e) => {
      e.preventDefault();
      zone.classList.remove('dragover');
      if (!draggedId) return;
      const status = zone.dataset.status;
      afFetch(window.AF_BASE_URL + 'api/activities.php', { method: 'POST', body: { action: 'update_status', id: draggedId, status } })
        .then(() => location.reload())
        .catch((err) => afToast(err.message, 'danger'));
    });
  });

  window.afOnActivityCreated = function () { location.reload(); };

  // Shared wiring for the "All X" / multi-select checkbox groups in the
  // board filter dropdowns (team members, statuses): checking "All" clears
  // individual picks, checking any individual pick clears "All", and
  // unchecking the last individual pick reverts to "All" so the filter can
  // never end up in an ambiguous nothing-selected state.
  function wireFilterGroup(allId, itemSelector, labelId, allText, singular, plural) {
    const allCb = document.getElementById(allId);
    const itemCbs = Array.from(document.querySelectorAll(itemSelector));
    function updateLabel() {
      const label = document.getElementById(labelId);
      if (!label) return;
      const checked = itemCbs.filter((cb) => cb.checked);
      if (!checked.length) { label.textContent = allText; return; }
      const noun = checked.length === 1 ? singular : plural;
      const suffix = (window.AF_I18N && window.AF_I18N.board_selected_suffix) || '{count} {noun} selected';
      label.textContent = suffix.replace('{count}', checked.length).replace('{noun}', noun);
    }
    if (allCb) {
      allCb.addEventListener('change', function () {
        if (allCb.checked) itemCbs.forEach((cb) => { cb.checked = false; });
        updateLabel();
      });
    }
    itemCbs.forEach((cb) => {
      cb.addEventListener('change', function () {
        if (cb.checked && allCb) allCb.checked = false;
        if (allCb && !itemCbs.some((c) => c.checked)) allCb.checked = true;
        updateLabel();
      });
    });
  }

  const i18n = window.AF_I18N || {};
  wireFilterGroup('boardFilterAll', '.board-member-checkbox', 'boardMemberFilterLabel',
    i18n.board_all_team_members || 'All team members', i18n.board_member_singular || 'member', i18n.board_member_plural || 'members');
  wireFilterGroup('boardStatusFilterAll', '.board-status-checkbox', 'boardStatusFilterLabel',
    i18n.board_all_statuses || 'All statuses', i18n.board_status_singular || 'status', i18n.board_status_plural || 'statuses');
})();
