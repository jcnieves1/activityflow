(function () {
  const i18n = window.AF_I18N_ACCOUNT_APPROVALS || {};
  const approveModalEl = document.getElementById('approveAccountModal');
  const rejectModalEl = document.getElementById('rejectAccountModal');
  const approveModal = new bootstrap.Modal(approveModalEl);
  const rejectModal = new bootstrap.Modal(rejectModalEl);

  const approveIntro = document.getElementById('approveAccountIntro');
  const approveReason = document.getElementById('approveAccountReason');
  const approveConfirmBtn = document.getElementById('approveAccountConfirmBtn');

  const rejectIntro = document.getElementById('rejectAccountIntro');
  const rejectReason = document.getElementById('rejectAccountReason');
  const rejectConfirmBtn = document.getElementById('rejectAccountConfirmBtn');

  let approving = null; // { id, name, reapprove }
  let rejecting = null; // { id, name }

  function openApprove(account) {
    approving = account;
    const template = account.reapprove ? (i18n.reapproveIntro || 'Re-approve {name}?') : (i18n.approveIntro || 'Approve {name}?');
    approveIntro.textContent = template.replace('{name}', account.name);
    approveReason.value = '';
    approveModal.show();
  }

  function openReject(account) {
    rejecting = account;
    rejectIntro.textContent = (i18n.rejectIntro || 'Reject {name}?').replace('{name}', account.name);
    rejectReason.value = '';
    rejectConfirmBtn.disabled = true;
    rejectModal.show();
  }

  // The reason is optional when approving, but required when rejecting — the
  // confirm button stays disabled until something is typed, mirroring the
  // same disabled-until-valid pattern used for the project delete
  // confirmation (see project_detail.js).
  rejectReason.addEventListener('input', function () {
    rejectConfirmBtn.disabled = rejectReason.value.trim() === '';
  });

  approveConfirmBtn.addEventListener('click', function () {
    if (!approving) return;
    approveConfirmBtn.disabled = true;
    afFetch(window.AF_BASE_URL + 'api/account_approvals.php', {
      method: 'POST',
      body: { action: 'approve', user_id: approving.id, reason: approveReason.value },
    })
      .then(() => {
        afToast(i18n.approved || 'Account approved.');
        setTimeout(() => location.reload(), 300);
      })
      .catch((err) => {
        afToast(err.message, 'danger');
        approveConfirmBtn.disabled = false;
      });
  });

  rejectConfirmBtn.addEventListener('click', function () {
    if (!rejecting) return;
    if (rejectReason.value.trim() === '') {
      afToast(i18n.reasonRequired || 'Please enter a reason.', 'danger');
      return;
    }
    rejectConfirmBtn.disabled = true;
    afFetch(window.AF_BASE_URL + 'api/account_approvals.php', {
      method: 'POST',
      body: { action: 'reject', user_id: rejecting.id, reason: rejectReason.value },
    })
      .then(() => {
        afToast(i18n.rejected || 'Account rejected.');
        setTimeout(() => location.reload(), 300);
      })
      .catch((err) => {
        afToast(err.message, 'danger');
        rejectConfirmBtn.disabled = false;
      });
  });

  window.afAccountApprovals = { openApprove, openReject };
})();
