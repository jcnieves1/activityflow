(function () {
  const tbody = document.querySelector('#usersTable tbody');
  const rolesModalEl = document.getElementById('rolesModal');
  const rolesModal = new bootstrap.Modal(rolesModalEl);
  let editingUserId = null;

  function load() {
    afFetch(window.AF_BASE_URL + 'api/admin.php?action=users')
      .then((res) => {
        tbody.innerHTML = res.users.map((u) => `
          <tr>
            <td class="fw-semibold">${afEscapeHtml(u.full_name)}</td>
            <td>${afEscapeHtml(u.email)}</td>
            <td>${(u.roles || '').split(',').filter(Boolean).map((r) => `<span class="badge bg-primary me-1">${afEscapeHtml(r)}</span>`).join('') || '<span class="text-muted">None</span>'}</td>
            <td><span class="badge bg-${u.status === 'active' ? 'success' : u.status === 'locked' ? 'danger' : 'secondary'}">${afEscapeHtml(u.status)}</span></td>
            <td class="small">${u.last_login_at ? afEscapeHtml(u.last_login_at) : 'Never'}</td>
            <td class="text-end">
              <button class="btn btn-sm btn-outline-secondary" onclick="afAdminUsers.editRoles(${u.id})">Roles</button>
              ${u.status === 'active'
                ? `<button class="btn btn-sm btn-outline-danger" onclick="afAdminUsers.setStatus(${u.id}, 'inactive')">Deactivate</button>`
                : `<button class="btn btn-sm btn-outline-success" onclick="afAdminUsers.setStatus(${u.id}, 'active')">Activate</button>`}
            </td>
          </tr>`).join('');
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
  };

  document.getElementById('saveRolesBtn').addEventListener('click', () => {
    const roleIds = [...document.querySelectorAll('#rolesModalBody input:checked')].map((cb) => cb.value);
    afFetch(window.AF_BASE_URL + 'api/admin.php', { method: 'POST', body: { action: 'set_roles', id: editingUserId, role_ids: roleIds } })
      .then(() => { afToast('Roles updated.'); rolesModal.hide(); load(); })
      .catch((err) => afToast(err.message, 'danger'));
  });

  load();
})();
