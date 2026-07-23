(function () {
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
