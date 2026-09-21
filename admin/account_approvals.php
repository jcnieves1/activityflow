<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();
require_role([ROLE_ADMIN]);

$pending = list_pending_accounts();
$history = list_account_approval_history();

$pageTitle = t('admin.account_approvals_title');
$activeNav = 'admin_account_approvals';
$breadcrumbs = [['label' => t('admin.breadcrumb')], ['label' => t('admin.account_approvals_title')]];
$pageScripts = [base_url('assets/js/admin_account_approvals.js')];
require __DIR__ . '/../includes/layout_header.php';
?>
<div class="mb-3">
  <h4 class="mb-0"><?= e(t('admin.account_approvals_title')) ?></h4>
  <p class="text-muted small mb-0"><?= e(t('admin.account_approvals_subtitle')) ?></p>
</div>

<h6 class="mb-2"><?= e(t('admin.pending_accounts_heading')) ?></h6>
<div class="af-card p-0 mb-4">
  <?php if (!$pending): ?>
    <div class="af-empty"><i class="bi bi-person-check"></i><?= e(t('admin.no_pending_accounts')) ?></div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th><?= e(t('admin.col_name')) ?></th>
          <th><?= e(t('admin.col_email')) ?></th>
          <th><?= e(t('admin.col_requested_at')) ?></th>
          <th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($pending as $p): ?>
          <tr>
            <td class="fw-semibold"><?= e($p['full_name']) ?></td>
            <td><?= e($p['email']) ?></td>
            <td class="small text-muted"><?= e(format_datetime($p['created_at'])) ?></td>
            <td class="text-end">
              <button class="btn btn-sm btn-success" onclick='afAccountApprovals.openApprove(<?= json_encode(['id' => (int)$p['id'], 'name' => $p['full_name']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="bi bi-check-lg"></i> <?= e(t('admin.approve')) ?></button>
              <button class="btn btn-sm btn-outline-danger" onclick='afAccountApprovals.openReject(<?= json_encode(['id' => (int)$p['id'], 'name' => $p['full_name']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="bi bi-x-lg"></i> <?= e(t('admin.reject')) ?></button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<h6 class="mb-2"><?= e(t('admin.decision_history_heading')) ?></h6>
<div class="af-card p-0">
  <?php if (!$history): ?>
    <div class="af-empty"><i class="bi bi-clock-history"></i><?= e(t('admin.no_decision_history')) ?></div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th><?= e(t('admin.col_date')) ?></th>
          <th><?= e(t('admin.col_account')) ?></th>
          <th><?= e(t('admin.col_decision')) ?></th>
          <th><?= e(t('admin.col_reason')) ?></th>
          <th><?= e(t('admin.col_decided_by')) ?></th>
          <th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($history as $h): ?>
          <tr>
            <td class="small text-muted"><?= e(format_datetime($h['created_at'])) ?></td>
            <td>
              <div class="fw-semibold"><?= e($h['user_full_name']) ?></div>
              <div class="small text-muted"><?= e($h['user_email']) ?></div>
            </td>
            <td>
              <?php if ($h['action'] === 'approved'): ?>
                <span class="badge bg-success"><?= e(t('admin.decision_approved')) ?></span>
              <?php else: ?>
                <span class="badge bg-danger"><?= e(t('admin.decision_rejected')) ?></span>
              <?php endif; ?>
            </td>
            <td class="small"><?= $h['reason'] ? e($h['reason']) : '<span class="text-muted">—</span>' ?></td>
            <td class="small text-muted"><?= e($h['actor_full_name'] ?? t('admin.system_actor')) ?></td>
            <td class="text-end">
              <?php if ($h['user_current_status'] === 'rejected'): ?>
                <button class="btn btn-sm btn-outline-success" onclick='afAccountApprovals.openApprove(<?= json_encode(['id' => (int)$h['user_id'], 'name' => $h['user_full_name'], 'reapprove' => true], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><i class="bi bi-arrow-counterclockwise"></i> <?= e(t('admin.reapprove')) ?></button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="modal fade" id="approveAccountModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title" id="approveAccountTitle"><?= e(t('admin.approve_account_title')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <p id="approveAccountIntro" class="mb-2"></p>
    <label class="form-label"><?= e(t('admin.approval_reason_label')) ?></label>
    <textarea class="form-control" id="approveAccountReason" rows="2" maxlength="1000"></textarea>
    <div class="form-text"><?= e(t('admin.approval_reason_hint')) ?></div>
  </div>
  <div class="modal-footer">
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button>
    <button type="button" class="btn btn-success" id="approveAccountConfirmBtn"><i class="bi bi-check-lg"></i> <?= e(t('admin.approve')) ?></button>
  </div>
</div></div></div>

<div class="modal fade" id="rejectAccountModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= e(t('admin.reject_account_title')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <p id="rejectAccountIntro" class="mb-2"></p>
    <label class="form-label"><?= e(t('admin.rejection_reason_label')) ?></label>
    <textarea class="form-control" id="rejectAccountReason" rows="2" maxlength="1000" required></textarea>
    <div class="form-text"><?= e(t('admin.rejection_reason_hint')) ?></div>
  </div>
  <div class="modal-footer">
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button>
    <button type="button" class="btn btn-danger" id="rejectAccountConfirmBtn" disabled><i class="bi bi-x-lg"></i> <?= e(t('admin.reject')) ?></button>
  </div>
</div></div></div>

<script>
window.AF_I18N_ACCOUNT_APPROVALS = {
  approveIntro: <?= json_encode(t('admin.approve_account_intro')) ?>,
  reapproveIntro: <?= json_encode(t('admin.reapprove_account_intro')) ?>,
  rejectIntro: <?= json_encode(t('admin.reject_account_intro')) ?>,
  approved: <?= json_encode(t('admin.account_approved_toast')) ?>,
  rejected: <?= json_encode(t('admin.account_rejected_toast')) ?>,
  reasonRequired: <?= json_encode(t('admin.rejection_reason_required')) ?>
};
</script>
<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
