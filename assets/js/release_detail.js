(function () {
  const releaseModalEl = document.getElementById('releaseModal');
  // Everything this file wires up (edit/delete release, phase CRUD, project
  // association/move/disassociation) is Administrator-only (see
  // release_detail.php) — those modals and their trigger buttons only exist
  // in the DOM for admins, so a non-admin viewing this page simply has
  // nothing here to wire up.
  if (!releaseModalEl) { window.afReleaseDetail = {}; return; }

  const i18n = window.AF_I18N_RELEASE_DETAIL || {};
  const releaseId = window.AF_RELEASE_ID;

  const releaseModal = new bootstrap.Modal(releaseModalEl);
  const releaseDeleteModal = new bootstrap.Modal(document.getElementById('releaseDeleteModal'));
  const releaseForm = document.getElementById('releaseForm');

  const phaseModal = new bootstrap.Modal(document.getElementById('phaseModal'));
  const phaseDeleteModal = new bootstrap.Modal(document.getElementById('phaseDeleteModal'));
  const phaseForm = document.getElementById('phaseForm');

  const disassociateModal = new bootstrap.Modal(document.getElementById('disassociateModal'));

  let deletingRelease = null; // { id, name, project_count }
  let deletingPhase = null; // { id, name }
  let disassociatingProject = null; // { id, name }

  // ---- Release (edit/delete the release itself from its detail page) ----

  function openEditRelease(r) {
    document.getElementById('release_id').value = r.id;
    document.getElementById('release_name_input').value = r.name;
    document.getElementById('release_description_input').value = r.description || '';
    document.getElementById('release_start_date_input').value = r.start_date;
    document.getElementById('release_end_date_input').value = r.end_date;
    document.getElementById('releaseModalTitle').textContent = i18n.editReleaseTitle || 'Edit release';
    releaseModal.show();
  }

  releaseForm.addEventListener('submit', function (e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(releaseForm).entries());
    afFetch(window.AF_BASE_URL + 'api/admin.php', { method: 'POST', body: Object.assign({ action: 'release_save' }, data) })
      .then(() => location.reload())
      .catch((err) => afToast(err.message, 'danger'));
  });

  function openDeleteRelease(r) {
    deletingRelease = r;
    const introEl = document.getElementById('releaseDeleteIntro');
    introEl.textContent = (r.project_count > 0
      ? (i18n.deleteReleaseConfirmWithProjects || 'Delete the release "{name}"? Its {count} associated project(s) will NOT be deleted — they will just be disassociated from this release.')
      : (i18n.deleteReleaseConfirmSimple || 'Delete the release "{name}"? This cannot be undone.')
    ).replace(/\{name\}/g, r.name).replace('{count}', r.project_count);
    releaseDeleteModal.show();
  }

  document.getElementById('releaseDeleteConfirmBtn').addEventListener('click', function () {
    if (!deletingRelease) return;
    afFetch(window.AF_BASE_URL + 'api/admin.php', { method: 'POST', body: { action: 'release_delete', id: deletingRelease.id } })
      .then(() => {
        afToast('Release deleted.');
        setTimeout(() => { window.location.href = i18n.releasesUrl || (window.AF_BASE_URL + 'releases.php'); }, 300);
      })
      .catch((err) => afToast(err.message, 'danger'));
  });

  // ---- Phases ----

  function openCreatePhase() {
    phaseForm.reset();
    document.getElementById('phase_id').value = '';
    document.getElementById('phaseModalTitle').textContent = i18n.newPhaseTitle || 'New phase';
    phaseModal.show();
  }

  function openEditPhase(p) {
    document.getElementById('phase_id').value = p.id;
    document.getElementById('phase_name_input').value = p.name;
    document.getElementById('phase_start_date_input').value = p.start_date;
    document.getElementById('phase_end_date_input').value = p.end_date;
    document.getElementById('phaseModalTitle').textContent = i18n.editPhaseTitle || 'Edit phase';
    phaseModal.show();
  }

  phaseForm.addEventListener('submit', function (e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(phaseForm).entries());
    data.release_id = releaseId;
    afFetch(window.AF_BASE_URL + 'api/admin.php', { method: 'POST', body: Object.assign({ action: 'release_phase_save' }, data) })
      .then(() => location.reload())
      .catch((err) => afToast(err.message, 'danger'));
  });

  function openDeletePhase(p) {
    deletingPhase = p;
    document.getElementById('phaseDeleteIntro').textContent =
      (i18n.deletePhaseConfirm || 'Delete the phase "{name}"? This cannot be undone.').replace(/\{name\}/g, p.name);
    phaseDeleteModal.show();
  }

  document.getElementById('phaseDeleteConfirmBtn').addEventListener('click', function () {
    if (!deletingPhase) return;
    afFetch(window.AF_BASE_URL + 'api/admin.php', { method: 'POST', body: { action: 'release_phase_delete', id: deletingPhase.id } })
      .then(() => {
        afToast('Phase deleted.');
        setTimeout(() => location.reload(), 300);
      })
      .catch((err) => afToast(err.message, 'danger'));
  });

  // ---- Project association ----

  function associateProject() {
    const select = document.getElementById('associateProjectSelect');
    if (!select || !select.value) return;
    afFetch(window.AF_BASE_URL + 'api/admin.php', {
      method: 'POST',
      body: { action: 'release_associate_project', release_id: releaseId, project_id: select.value },
    })
      .then(() => location.reload())
      .catch((err) => afToast(err.message, 'danger'));
  }

  function openDisassociateProject(p) {
    disassociatingProject = p;
    document.getElementById('disassociateIntro').textContent =
      (i18n.disassociateConfirm || 'Remove "{name}" from this release? The project itself will not be deleted.').replace(/\{name\}/g, p.name);
    disassociateModal.show();
  }

  document.getElementById('disassociateConfirmBtn').addEventListener('click', function () {
    if (!disassociatingProject) return;
    afFetch(window.AF_BASE_URL + 'api/admin.php', { method: 'POST', body: { action: 'release_disassociate_project', project_id: disassociatingProject.id } })
      .then(() => {
        afToast('Project disassociated.');
        setTimeout(() => location.reload(), 300);
      })
      .catch((err) => afToast(err.message, 'danger'));
  });

  function moveProject(projectId, targetReleaseId) {
    if (!afConfirm('Move this project to the other release?')) return;
    afFetch(window.AF_BASE_URL + 'api/admin.php', {
      method: 'POST',
      body: { action: 'release_move_project', release_id: targetReleaseId, project_id: projectId },
    })
      .then(() => location.reload())
      .catch((err) => afToast(err.message, 'danger'));
  }

  window.afReleaseDetail = {
    openEditRelease, openDeleteRelease,
    openCreatePhase, openEditPhase, openDeletePhase,
    associateProject, openDisassociateProject, moveProject,
  };
})();
