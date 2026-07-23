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

  const allCb = document.getElementById('boardFilterAll');
  const memberCbs = Array.from(document.querySelectorAll('.board-member-checkbox'));
  function updateBoardFilterLabel() {
    const label = document.getElementById('boardMemberFilterLabel');
    if (!label) return;
    const checked = memberCbs.filter((cb) => cb.checked);
    if (!checked.length) { label.textContent = 'All team members'; return; }
    label.textContent = checked.length + ' member' + (checked.length === 1 ? '' : 's') + ' selected';
  }
  if (allCb) {
    allCb.addEventListener('change', function () {
      if (allCb.checked) memberCbs.forEach((cb) => { cb.checked = false; });
      updateBoardFilterLabel();
    });
  }
  memberCbs.forEach((cb) => {
    cb.addEventListener('change', function () {
      if (cb.checked && allCb) allCb.checked = false;
      if (allCb && !memberCbs.some((c) => c.checked)) allCb.checked = true;
      updateBoardFilterLabel();
    });
  });
})();
