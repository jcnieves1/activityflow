(function () {
  window.afOnActivityCreated = function () { location.reload(); };

  // ---- Drag-to-reorder the planned list ----
  const list = document.getElementById('plannedList');
  let dragEl = null;

  if (list) {
    list.addEventListener('dragstart', (e) => {
      const item = e.target.closest('.af-activity-item');
      if (!item) return;
      dragEl = item;
      item.classList.add('dragging');
    });
    list.addEventListener('dragend', () => {
      dragEl && dragEl.classList.remove('dragging');
      dragEl = null;
      saveOrder();
    });
    list.addEventListener('dragover', (e) => {
      e.preventDefault();
      const after = getDragAfterElement(list, e.clientY);
      if (!dragEl) return;
      if (after == null) {
        list.appendChild(dragEl);
      } else {
        list.insertBefore(dragEl, after);
      }
    });
  }

  function getDragAfterElement(container, y) {
    const items = [...container.querySelectorAll('.af-activity-item:not(.dragging)')];
    return items.reduce((closest, child) => {
      const box = child.getBoundingClientRect();
      const offset = y - box.top - box.height / 2;
      if (offset < 0 && offset > closest.offset) {
        return { offset, element: child };
      }
      return closest;
    }, { offset: Number.NEGATIVE_INFINITY }).element;
  }

  function saveOrder() {
    if (!list) return;
    const ids = [...list.querySelectorAll('.af-activity-item')].map((el) => el.dataset.id);
    if (!ids.length) return;
    afFetch(window.AF_BASE_URL + 'api/activities.php', { method: 'POST', body: { action: 'reorder', ordered_ids: ids } })
      .catch((err) => afToast(err.message, 'danger'));
  }

  window.afMyDay = {
    scheduleToday(id) {
      const time = window.prompt('Schedule this task for what time today? (HH:MM, 24-hour)', '09:00');
      if (!time) return;
      afFetch(window.AF_BASE_URL + 'api/activities.php', {
        method: 'POST',
        body: { action: 'reschedule', id, planned_start_at: window.AF_MYDAY_DATE + ' ' + time + ':00' },
      }).then(() => location.reload()).catch((err) => afToast(err.message, 'danger'));
    },
    copyToToday(id) {
      afFetch(window.AF_BASE_URL + 'api/activities.php', { method: 'POST', body: { action: 'copy_to_date', id, new_date: window.AF_MYDAY_DATE } })
        .then(() => { afToast('Copied to today.'); location.reload(); })
        .catch((err) => afToast(err.message, 'danger'));
    },
  };
})();
