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
          <h5 class="modal-title"><i class="bi bi-lightning-fill"></i> Quick-add unplanned task</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2"><label class="form-label">Task title *</label><input class="form-control" name="title" required autofocus></div>
          <div class="row">
            <div class="col-6 mb-2">
              <label class="form-label">Requester *</label>
              <div class="input-group">
                <select class="form-select" name="requester_id" id="qa_requester_id" required>
                  <option value="">Select…</option>
                  <?php foreach ($qaPeople as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['full_name']) ?></option><?php endforeach; ?>
                </select>
                <button class="btn btn-outline-secondary" type="button" title="Add new requester" onclick="afQuickAdd.newPerson('qa_requester_id')"><i class="bi bi-person-plus"></i></button>
              </div>
            </div>
            <div class="col-6 mb-2">
              <label class="form-label">Assigned to *</label>
              <select class="form-select" name="assignee_id" required>
                <?php foreach ($qaPeople as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $p['id'] == current_person_id() ? 'selected' : '' ?>><?= e($p['full_name']) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="row">
            <div class="col-6 mb-2"><label class="form-label">Requested at</label><input type="datetime-local" class="form-control" name="requested_at" id="qa_requested_at"></div>
            <div class="col-6 mb-2"><label class="form-label">Target completion</label><input type="datetime-local" class="form-control" name="target_completion_at"></div>
          </div>
          <div class="row">
            <div class="col-6 mb-2">
              <label class="form-label">Project</label>
              <select class="form-select" name="project_id">
                <option value="">No project</option>
                <?php foreach ($qaProjects as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-6 mb-2"><label class="form-label">Estimated duration (min)</label><input type="number" min="0" class="form-control" name="estimated_minutes"></div>
          </div>
          <div class="row">
            <div class="col-6 mb-2"><label class="form-label">Priority</label>
              <select class="form-select" name="priority">
                <option value="low">Low</option><option value="normal" selected>Normal</option><option value="high">High</option><option value="urgent">Urgent</option>
              </select>
            </div>
            <div class="col-6 mb-2"><label class="form-label">Request channel</label>
              <select class="form-select" name="request_channel">
                <?php foreach (REQUEST_CHANNELS as $c): ?><option value="<?= $c ?>"><?= e(request_channel_label($c)) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mb-2 form-check">
            <input type="checkbox" class="form-check-input" name="is_adhoc" id="qa_adhoc" value="1" checked>
            <label class="form-check-label" for="qa_adhoc">Ad-hoc (not tied to planned work)</label>
          </div>
          <div class="mb-2" id="qa_interrupt_block">
            <label class="form-label">Interrupted task (optional)</label>
            <select class="form-select" name="interrupted_activity_id" id="qa_interrupted_activity_id">
              <option value="">None — did not interrupt anything</option>
            </select>
          </div>
          <div class="mb-2"><label class="form-label">Brief reason / notes</label><textarea class="form-control" name="notes" rows="2"></textarea></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning"><i class="bi bi-lightning-fill"></i> Log task</button>
        </div>
      </form>
    </div>
  </div>
</div>
<button type="button" class="btn btn-warning af-fab no-print" data-bs-toggle="modal" data-bs-target="#quickAddModal" title="Quick-add unplanned task">
  <i class="bi bi-lightning-fill"></i>
</button>
