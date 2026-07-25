(function () {
  const tbody = document.querySelector('#usersTable tbody');
  const rolesModalEl = document.getElementById('rolesModal');
  const rolesModal = new bootstrap.Modal(rolesModalEl);
  let editingUserId = null;

  function load() {
    afFetch(window.AF_BASE_URL + 'api/admin.php?action=users')
      .then((res) => {
        tbody.innerHTML = res.users.map((u) => {
          const roles = (u.roles || '').split(',').filter(Boolean);
          // Impersonation is only offered for accounts it actually makes sense
          // (and is allowed) to impersonate: not yourself, not a deactivated/
          // locked account, and never another administrator — the server
          // enforces all three regardless, but hiding the button avoids
          // offering an action that would just come back as an error.
          const canImpersonate = u.id !== window.AF_USER_ID && u.status === 'active' && !roles.includes('administrator');
          return `
          <tr>
            <td class="fw-semibold">${afEscapeHtml(u.full_name)}</td>
            <td>${afEscapeHtml(u.email)}</td>
            <td>${roles.map((r) => `<span class="badge bg-primary me-1">${afEscapeHtml(r)}</span>`).join('') || '<span class="text-muted">None</span>'}</td>
            <td><span class="badge bg-${u.status === 'active' ? 'success' : u.status === 'locked' ? 'danger' : 'secondary'}">${afEscapeHtml(u.status)}</span></td>
            <td class="small">${u.last_login_at ? afEscapeHtml(u.last_login_at) : 'Never'}</td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-secondary" onclick="afAdminUsers.editRoles(${u.id})">Roles</button>
              ${u.status === 'active'
                ? `<button class="btn btn-sm btn-outline-danger" onclick="afAdminUsers.setStatus(${u.id}, 'inactive')">Deactivate</button>`
                : `<button class="btn btn-sm btn-outline-success" onclick="afAdminUsers.setStatus(${u.id}, 'active')">Activate</button>`}
              ${canImpersonate ? `<button class="btn btn-sm btn-outline-primary" onclick="afAdminUsers.impersonate(${u.id})"><i class="bi bi-person-badge"></i> ${afEscapeHtml((window.AF_I18N && window.AF_I18N.admin_impersonate) || 'Impersonate')}</button>` : ''}
            </td>
          </tr>`;
        }).join('');
        window.AF_USERS = res.users;
      });
  }

  window.afAdminUsers = {
    editRoles(userId) {
      editingUserId = userId;
      const user = (window.AF_USERS || []).find((u) => u.id === userId);
      const activeRoles = (user && user.roles) ? user.roles.split(',') : [];
      document.querySelectorAll('#rolesModalBody input[type=checkbox]').forEach((cb) => {
        cb.checked = activeRoles.includes(cb.dataset.roleName);
      });
      rolesModal.show();
    },
    setStatus(userId, status) {
      if (!afConfirm(`Set this account to ${status}?`)) return;
      afFetch(window.AF_BASE_URL + 'api/admin.php', { method: 'POST', body: { action: 'set_status', id: userId, status } })
        .then(() => { afToast('Status updated.'); load(); })
        .catch((err) => afToast(err.message, 'danger'));
    },
    impersonate(userId) {
      const user = (window.AF_USERS || []).find((u) => u.id === userId);
      const name = user ? user.full_name : ('#' + userId);
      const template = (window.AF_I18N && window.AF_I18N.admin_impersonate_confirm) || "Log in as {name}? You'll be able to return to your own account at any time.";
      if (!afConfirm(template.replace('{name}', name))) return;
      // Identity itself is changing (nav, permissions, everything derived from
      // the session), so a full navigation to a fresh page is required here —
      // an in-place AJAX-only update would leave stale admin-only UI on screen
      // for a session that's no longer an admin's.
      afFetch(window.AF_BASE_URL + 'api/impersonate.php', { method: 'POST', body: { action: 'start', user_id: userId } })
        .then(() => { window.location.href = window.AF_BASE_URL + 'dashboard.php'; })
        .catch((err) => afToast(err.message, 'danger'));
    },
  };

  document.getElementById('saveRolesBtn').addEventListener('click', () => {
    const roleIds = [...document.querySelectorAll('#rolesModalBody input:checked')].map((cb) => cb.value);
    afFetch(window.AF_BASE_URL + 'api/admin.php', { method: 'POST', body: { action: 'set_roles', id: editingUserId, role_ids: roleIds } })
      .then(() => { afToast('Roles updated.'); rolesModal.hide(); load(); })
      .catch((err) => afToast(err.message, 'danger'));
  });

  load();
})();
