<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_login();
require_role([ROLE_ADMIN]);

$channels = list_request_channels();
$channelsWithCounts = array_map(function ($c) {
    $c['task_count'] = count_activities_with_request_channel($c['slug']);
    return $c;
}, $channels);

$pageTitle = t('admin.request_channels_title');
$activeNav = 'admin_request_channels';
$breadcrumbs = [['label' => t('admin.breadcrumb')], ['label' => t('admin.request_channels_title')]];
$pageScripts = [base_url('assets/js/admin_request_channels.js')];
require __DIR__ . '/../includes/layout_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-0"><?= e(t('admin.request_channels_title')) ?></h4>
    <p class="text-muted small mb-0"><?= e(t('admin.request_channels_subtitle')) ?></p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#channelModal" onclick="afRequestChannels.openCreate()"><i class="bi bi-plus-lg"></i> <?= e(t('admin.add_request_channel')) ?></button>
</div>
<div class="af-card p-0">
  <table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr>
      <th><?= e(t('admin.col_channel_text')) ?></th>
      <th><?= e(t('admin.col_slug')) ?></th>
      <th><?= e(t('admin.col_tasks_using')) ?></th>
      <th></th>
      <th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($channelsWithCounts as $c): ?>
      <tr>
        <td class="fw-semibold"><?= e($c['label']) ?></td>
        <td><code class="small text-muted"><?= e($c['slug']) ?></code></td>
        <td><?= (int)$c['task_count'] ?></td>
        <td><?php if ($c['is_system']): ?><span class="badge bg-secondary" title="<?= e(t('admin.system_channel_hint')) ?>"><?= e(t('admin.system_channel')) ?></span><?php endif; ?></td>
        <td class="text-end">
          <button class="btn btn-sm btn-outline-secondary" onclick='afRequestChannels.openEdit(<?= json_encode(['id' => (int)$c['id'], 'label' => $c['label']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><?= e(t('common.edit')) ?></button>
          <?php if (!$c['is_system']): ?>
            <button class="btn btn-sm btn-outline-danger" onclick='afRequestChannels.openDelete(<?= json_encode(['id' => (int)$c['id'], 'label' => $c['label'], 'count' => (int)$c['task_count']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'><?= e(t('common.delete')) ?></button>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="modal fade" id="channelModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form id="channelForm">
    <div class="modal-header"><h5 class="modal-title" id="channelModalTitle"><?= e(t('admin.new_request_channel')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <input type="hidden" name="id" id="channel_id">
      <div class="mb-2">
        <label class="form-label"><?= e(t('admin.channel_text_label')) ?></label>
        <input class="form-control" name="label" id="channel_label_input" required maxlength="80">
        <div class="form-text"><?= e(t('admin.channel_text_hint')) ?></div>
      </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button><button class="btn btn-primary"><?= e(t('common.save')) ?></button></div>
  </form>
</div></div></div>

<div class="modal fade" id="channelDeleteModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= e(t('admin.delete_channel_title')) ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <p id="channelDeleteIntro"></p>
    <div id="channelDeleteReassignBlock" class="d-none">
      <div class="alert alert-warning" id="channelDeleteWarning"></div>
      <label class="form-label"><?= e(t('admin.replacement_channel')) ?></label>
      <select class="form-select" id="channelDeleteReplacement"></select>
    </div>
  </div>
  <div class="modal-footer">
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button>
    <button type="button" class="btn btn-danger" id="channelDeleteConfirmBtn"><i class="bi bi-trash3"></i> <span id="channelDeleteConfirmBtnLabel"><?= e(t('common.delete')) ?></span></button>
  </div>
</div></div></div>

<script>
window.AF_REQUEST_CHANNELS = <?= json_encode(array_map(fn($c) => ['id' => (int)$c['id'], 'slug' => $c['slug'], 'label' => $c['label']], $channels)) ?>;
window.AF_I18N_REQUEST_CHANNELS = {
  deleteSimple: <?= json_encode(t('admin.delete_channel_simple_confirm')) ?>,
  inUseWarning: <?= json_encode(t('admin.delete_channel_in_use_warning')) ?>,
  reassignAndDelete: <?= json_encode(t('admin.reassign_and_delete')) ?>,
  newTitle: <?= json_encode(t('admin.new_request_channel')) ?>,
  editTitle: <?= json_encode(t('admin.edit_request_channel')) ?>,
  deleteLabel: <?= json_encode(t('common.delete')) ?>
};
</script>
<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
