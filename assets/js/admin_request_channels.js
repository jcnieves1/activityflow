(function () {
  const i18n = window.AF_I18N_REQUEST_CHANNELS || {};
  const modal = new bootstrap.Modal(document.getElementById('channelModal'));
  const deleteModal = new bootstrap.Modal(document.getElementById('channelDeleteModal'));
  const form = document.getElementById('channelForm');

  let deleting = null; // { id, label, count }

  function openCreate() {
    form.reset();
    document.getElementById('channel_id').value = '';
    document.getElementById('channelModalTitle').textContent = i18n.newTitle || 'New channel';
  }

  function openEdit(c) {
    document.getElementById('channel_id').value = c.id;
    document.getElementById('channel_label_input').value = c.label;
    document.getElementById('channelModalTitle').textContent = i18n.editTitle || 'Edit channel';
    modal.show();
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(form).entries());
    afFetch(window.AF_BASE_URL + 'api/admin.php', { method: 'POST', body: Object.assign({ action: 'request_channel_save' }, data) })
      .then(() => location.reload())
      .catch((err) => afToast(err.message, 'danger'));
  });

  // Delete is a two-step flow: a channel with zero tasks on it can just be
  // removed, but one that's currently in use needs a replacement chosen
  // first — count_activities_with_request_channel()/delete_request_channel()
  // on the server are the source of truth for the count, this just presents it.
  function openDelete(c) {
    deleting = c;
    const introEl = document.getElementById('channelDeleteIntro');
    const reassignBlock = document.getElementById('channelDeleteReassignBlock');
    const warningEl = document.getElementById('channelDeleteWarning');
    const replacementSelect = document.getElementById('channelDeleteReplacement');
    const confirmLabel = document.getElementById('channelDeleteConfirmBtnLabel');

    if (c.count > 0) {
      introEl.textContent = '';
      reassignBlock.classList.remove('d-none');
      warningEl.textContent = (i18n.inUseWarning || '{count} task(s) currently use "{label}". Choose a replacement.')
        .replace('{count}', c.count).replace(/\{label\}/g, c.label);
      const options = (window.AF_REQUEST_CHANNELS || []).filter((rc) => String(rc.id) !== String(c.id));
      replacementSelect.innerHTML = options.map((rc) => `<option value="${afEscapeHtml(rc.slug)}">${afEscapeHtml(rc.label)}</option>`).join('');
      confirmLabel.textContent = i18n.reassignAndDelete || 'Reassign & delete';
    } else {
      reassignBlock.classList.add('d-none');
      introEl.textContent = (i18n.deleteSimple || 'Delete the channel "{label}"? This cannot be undone.').replace(/\{label\}/g, c.label);
      confirmLabel.textContent = i18n.deleteLabel || 'Delete';
    }
    deleteModal.show();
  }

  document.getElementById('channelDeleteConfirmBtn').addEventListener('click', function () {
    if (!deleting) return;
    const body = { action: 'request_channel_delete', id: deleting.id };
    if (deleting.count > 0) {
      const replacementSelect = document.getElementById('channelDeleteReplacement');
      if (!replacementSelect.value) { afToast('Choose a replacement channel.', 'danger'); return; }
      body.replacement_slug = replacementSelect.value;
    }
    afFetch(window.AF_BASE_URL + 'api/admin.php', { method: 'POST', body })
      .then((res) => {
        // needs_replacement can still come back true if two admins raced each
        // other (another task got moved onto this channel between opening the
        // dialog and confirming) — surface it rather than silently no-op-ing.
        if (res.needs_replacement) {
          afToast('More tasks started using this channel just now — please try again.', 'danger');
          return;
        }
        afToast('Channel deleted.');
        setTimeout(() => location.reload(), 300);
      })
      .catch((err) => afToast(err.message, 'danger'));
  });

  window.afRequestChannels = { openCreate, openEdit, openDelete };
})();
