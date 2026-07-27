(function () {
  const modalEl = document.getElementById('releaseModal');
  // Adding/editing/deleting releases is Administrator-only (see releases.php) —
  // the modals and their trigger buttons only exist in the DOM for admins, so
  // a non-admin visiting this page simply has nothing here to wire up.
  if (!modalEl) { window.afReleases = {}; return; }

  const i18n = window.AF_I18N_RELEASES || {};
  const modal = new bootstrap.Modal(modalEl);
  const deleteModal = new bootstrap.Modal(document.getElementById('releaseDeleteModal'));
  const form = document.getElementById('releaseForm');

  let deleting = null; // { id, name, project_count }

  function openCreate() {
    form.reset();
    document.getElementById('release_id').value = '';
    document.getElementById('releaseModalTitle').textContent = i18n.newTitle || 'New release';
  }

  function openEdit(r) {
    document.getElementById('release_id').value = r.id;
    document.getElementById('release_name_input').value = r.name;
    document.getElementById('release_description_input').value = r.description || '';
    document.getElementById('release_start_date_input').value = r.start_date;
    document.getElementById('release_end_date_input').value = r.end_date;
    document.getElementById('releaseModalTitle').textContent = i18n.editTitle || 'Edit release';
    modal.show();
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());
    afFetch(window.AF_BASE_URL + 'api/admin.php', { method: 'POST', body: Object.assign({ action: 'release_save' }, data) })
      .then(() => location.reload())
      .catch((err) => afToast(err.message, 'danger'));
  });

  // Deleting a release never deletes its projects — they're only
  // disassociated (see delete_release() server-side) — so the confirmation
  // just informs the admin of that rather than requiring a reassignment step.
  function openDelete(r) {
    deleting = r;
    const introEl = document.getElementById('releaseDeleteIntro');
    introEl.textContent = (r.project_count > 0
      ? (i18n.deleteConfirmWithProjects || 'Delete the release "{name}"? Its {count} associated project(s) will NOT be deleted — they will just be disassociated from this release.')
      : (i18n.deleteConfirmSimple || 'Delete the release "{name}"? This cannot be undone.')
    ).replace(/\{name\}/g, r.name).replace('{count}', r.project_count);
    deleteModal.show();
  }

  document.getElementById('releaseDeleteConfirmBtn').addEventListener('click', function () {
    if (!deleting) return;
    afFetch(window.AF_BASE_URL + 'api/admin.php', { method: 'POST', body: { action: 'release_delete', id: deleting.id } })
      .then(() => {
        afToast('Release deleted.');
        setTimeout(() => location.reload(), 300);
      })
      .catch((err) => afToast(err.message, 'danger'));
  });

  window.afReleases = { openCreate, openEdit, openDelete };
})();
