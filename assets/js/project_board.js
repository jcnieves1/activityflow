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
})();
