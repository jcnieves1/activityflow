<?php
declare(strict_types=1);
/** Global "record a last-minute task" modal — reachable from any screen in a few clicks. */
$qaPeople = list_people(['is_active' => 1]);
$qaProjects = list_projects(['is_archived' => 0]);
$qaCategories = db()->query('SELECT * FROM activity_categories WHERE is_active = 1 ORDER BY name')->fetchAll();
?>
<div class="modal fade" id="quickAddModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="quickAddForm">
        <div class="modal-header bg-orange">
          <h5 class="modal-title"><i class="bi bi-lightning-fill"></i> <?= e(t('quickadd.title')) ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2"><label class="form-label"><?= e(t('quickadd.task_title')) ?></label><input class="form-control" name="title" required autofocus></div>
          <div class="row">
            <div class="col-6 mb-2">
              <label class="form-label"><?= e(t('quickadd.requester')) ?></label>
              <div class="input-group">
                <select class="form-select" name="requester_id" id="qa_requester_id" required>
                  <option value=""><?= e(t('quickadd.select')) ?></option>
                  <?php foreach ($qaPeople as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['full_name']) ?></option><?php endforeach; ?>
                </select>
                <button class="btn btn-outline-secondary" type="button" title="<?= e(t('quickadd.add_requester')) ?>" onclick="afQuickAdd.newPerson('qa_requester_id')"><i class="bi bi-person-plus"></i></button>
              </div>
            </div>
            <div class="col-6 mb-2">
              <label class="form-label"><?= e(t('quickadd.assigned_to')) ?></label>
              <select class="form-select" name="assignee_id" required>
                <?php foreach ($qaPeople as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $p['id'] == current_person_id() ? 'selected' : '' ?>><?= e($p['full_name']) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="row">
            <div class="col-6 mb-2"><label class="form-label"><?= e(t('quickadd.requested_at')) ?></label><input type="datetime-local" class="form-control" name="requested_at" id="qa_requested_at"></div>
            <div class="col-6 mb-2"><label class="form-label"><?= e(t('quickadd.target_completion')) ?></label><input type="datetime-local" class="form-control" name="target_completion_at"></div>
          </div>
          <div class="row">
            <div class="col-6 mb-2">
              <label class="form-label"><?= e(t('quickadd.project')) ?></label>
              <select class="form-select" name="project_id" id="qa_project_id">
                <option value=""><?= e(t('quickadd.no_project')) ?></option>
                <?php foreach ($qaProjects as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-6 mb-2"><label class="form-label"><?= e(t('quickadd.estimated_minutes')) ?></label><input type="number" min="0" class="form-control" name="estimated_minutes"></div>
          </div>
          <div class="row">
            <div class="col-6 mb-2"><label class="form-label"><?= e(t('quickadd.priority')) ?></label>
              <select class="form-select" name="priority">
                <option value="low"><?= e(t('activity.priority_low')) ?></option><option value="normal" selected><?= e(t('activity.priority_normal')) ?></option><option value="high"><?= e(t('activity.priority_high')) ?></option><option value="urgent"><?= e(t('activity.priority_urgent')) ?></option>
              </select>
            </div>
            <div class="col-6 mb-2"><label class="form-label"><?= e(t('quickadd.request_channel')) ?></label>
              <select class="form-select" name="request_channel">
                <?php foreach (REQUEST_CHANNELS as $c): ?><option value="<?= $c ?>"><?= e(request_channel_label($c)) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mb-2 form-check">
            <input type="checkbox" class="form-check-input" name="is_adhoc" id="qa_adhoc" value="1" checked>
            <label class="form-check-label" for="qa_adhoc"><?= e(t('quickadd.adhoc_label')) ?></label>
          </div>
          <div class="mb-2" id="qa_interrupt_block">
            <label class="form-label"><?= e(t('quickadd.interrupted_task')) ?></label>
            <select class="form-select" name="interrupted_activity_id" id="qa_interrupted_activity_id">
              <option value=""><?= e(t('quickadd.interrupted_none')) ?></option>
            </select>
          </div>
          <div class="mb-2"><label class="form-label"><?= e(t('quickadd.notes')) ?></label><textarea class="form-control" name="notes" rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button>
          <button type="submit" class="btn btn-warning"><i class="bi bi-lightning-fill"></i> <?= e(t('quickadd.log_task')) ?></button>
        </div>
      </form>
    </div>
  </div>
</div>
<button type="button" class="btn btn-warning af-fab no-print" data-bs-toggle="modal" data-bs-target="#quickAddModal" title="<?= e(t('quickadd.fab_title')) ?>">
  <i class="bi bi-lightning-fill"></i>
</button>
