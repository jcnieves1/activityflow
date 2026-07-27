<?php
declare(strict_types=1);
/** Shared create/edit modal for planned activities and project tasks. Reused by My Tasks, Team Activities, and the Project Board. */
$amPeople = list_people(['is_active' => 1]);
$amProjects = list_projects(['is_archived' => 0]);
$amCategories = db()->query('SELECT * FROM activity_categories WHERE is_active = 1 ORDER BY name')->fetchAll();
// Only offer projects the current user is actually allowed to clone/move tasks
// into (admin/PM, or a member of that project) — narrower than $amProjects
// (used for the task's own Project field) so the destination dropdown never
// offers a choice the server would just reject.
$amTargetProjects = array_values(array_filter($amProjects, fn($p) => can_add_task_to_project($p)));

// Powers the "filter assignee list to this project's members" behavior in
// assets/js/activities.js: each assignee <option> below gets a data-projects
// attribute listing every project (by id) that person belongs to, so the
// script can show/hide options client-side with no extra round trip. A person
// with no project memberships at all gets an empty attribute, which reads as
// "not a member of anything" rather than "member of everything".
$amProjectMemberIds = list_project_members_map(array_column($amProjects, 'id'));
$amPersonProjectIds = [];
foreach ($amProjectMemberIds as $projId => $memberIds) {
    foreach ($memberIds as $personId) {
        $amPersonProjectIds[$personId][] = $projId;
    }
}
?>
<div class="modal fade" id="activityModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="activityModalTitle"><?= e(t('activity.new_title')) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="alert alert-warning d-none mx-3 mt-2 mb-0 py-2" id="am_vacation_warning" role="alert"><i class="bi bi-exclamation-triangle-fill"></i> <span id="am_vacation_warning_text"></span></div>
      <ul class="nav nav-tabs px-3 pt-2" id="activityTabs" style="display:none">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#am_tab_details"><?= e(t('activity.tab_details')) ?></button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#am_tab_time"><?= e(t('activity.tab_time')) ?></button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#am_tab_comments"><?= e(t('activity.tab_comments')) ?></button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#am_tab_interruptions"><?= e(t('activity.tab_interruptions')) ?></button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#am_tab_history"><?= e(t('activity.tab_history')) ?></button></li>
      </ul>
      <div class="modal-body tab-content">
        <div class="tab-pane fade show active" id="am_tab_details">
          <form id="activityForm">
            <input type="hidden" name="id" id="am_id">
            <div class="mb-2"><label class="form-label"><?= e(t('activity.field_title')) ?></label><input class="form-control" name="title" id="am_title" required></div>
            <div class="mb-2"><label class="form-label"><?= e(t('activity.field_description')) ?></label><textarea class="form-control" name="description" id="am_description" rows="2"></textarea></div>
            <div class="row">
              <div class="col-md-6 mb-2"><label class="form-label"><?= e(t('activity.field_project')) ?></label>
                <select class="form-select" name="project_id" id="am_project_id">
                  <option value=""><?= e(t('activity.no_project')) ?></option>
                  <?php foreach ($amProjects as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6 mb-2"><label class="form-label"><?= e(t('activity.field_category')) ?></label>
                <select class="form-select" name="category_id" id="am_category_id">
                  <option value="">—</option>
                  <?php foreach ($amCategories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-2"><label class="form-label"><?= e(t('activity.field_assignee')) ?></label>
                <select class="form-select" name="assignee_id" id="am_assignee_id" required>
                  <?php foreach ($amPeople as $p): ?><option value="<?= (int)$p['id'] ?>" data-projects="<?= e(implode(',', $amPersonProjectIds[(int)$p['id']] ?? [])) ?>"><?= e($p['full_name']) ?></option><?php endforeach; ?>
                </select>
                <div class="form-text" id="am_assignee_filter_hint" style="display:none"><?= e(t('activity.assignee_filtered_hint')) ?></div>
              </div>
              <div class="col-md-6 mb-2"><label class="form-label"><?= e(t('activity.field_requester')) ?></label>
                <div class="input-group">
                  <select class="form-select" name="requester_id" id="am_requester_id" required>
                    <?php foreach ($amPeople as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['full_name']) ?></option><?php endforeach; ?>
                  </select>
                  <button class="btn btn-outline-secondary" type="button" onclick="afQuickAdd.newPerson('am_requester_id')"><i class="bi bi-person-plus"></i></button>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-2"><label class="form-label"><?= e(t('activity.field_planned_start')) ?></label><input type="datetime-local" class="form-control" name="planned_start_at" id="am_planned_start_at"></div>
              <div class="col-md-6 mb-2"><label class="form-label"><?= e(t('activity.field_target_completion')) ?></label><input type="datetime-local" class="form-control" name="target_completion_at" id="am_target_completion_at"></div>
            </div>
            <div class="row">
              <div class="col-md-4 mb-2"><label class="form-label"><?= e(t('activity.field_estimated_hours')) ?></label><input type="number" min="0" step="0.25" class="form-control" name="estimated_hours" id="am_estimated_hours" placeholder="e.g. 1.5"></div>
              <div class="col-md-4 mb-2"><label class="form-label"><?= e(t('common.priority')) ?></label>
                <select class="form-select" name="priority" id="am_priority">
                  <option value="low"><?= e(t('activity.priority_low')) ?></option><option value="normal"><?= e(t('activity.priority_normal')) ?></option><option value="high"><?= e(t('activity.priority_high')) ?></option><option value="urgent"><?= e(t('activity.priority_urgent')) ?></option>
                </select>
              </div>
              <div class="col-md-4 mb-2"><label class="form-label"><?= e(t('activity.field_request_channel')) ?></label>
                <select class="form-select" name="request_channel" id="am_request_channel">
                  <option value="">—</option>
                  <?php foreach (list_request_channels() as $rc): ?><option value="<?= e($rc['slug']) ?>"><?= e($rc['label']) ?></option><?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="mb-2"><label class="form-label"><?= e(t('activity.field_parent_task')) ?></label>
              <select class="form-select" name="parent_activity_id" id="am_parent_activity_id">
                <option value=""><?= e(t('activity.parent_none')) ?></option>
              </select>
            </div>
            <div class="mb-2"><label class="form-label"><?= e(t('activity.field_tags')) ?></label><input class="form-control" name="tags" id="am_tags"></div>
            <div class="mb-2 form-check">
              <input type="checkbox" class="form-check-input" name="is_milestone" id="am_is_milestone" value="1">
              <label class="form-check-label" for="am_is_milestone"><?= e(t('activity.field_milestone')) ?></label>
            </div>
            <div class="row" id="am_repeat_block">
              <div class="col-md-6 mb-2"><label class="form-label"><?= e(t('activity.field_repeat')) ?></label>
                <select class="form-select" name="repeat_frequency" id="am_repeat_frequency">
                  <option value=""><?= e(t('activity.repeat_none')) ?></option>
                  <option value="daily"><?= e(t('activity.repeat_daily')) ?></option>
                  <option value="weekly"><?= e(t('activity.repeat_weekly')) ?></option>
                </select>
              </div>
              <div class="col-md-6 mb-2"><label class="form-label"><?= e(t('activity.field_repeat_until')) ?></label><input type="date" class="form-control" name="repeat_until" id="am_repeat_until"></div>
            </div>
            <div class="mb-2"><label class="form-label"><?= e(t('activity.field_notes')) ?></label><textarea class="form-control" name="notes" id="am_notes" rows="2"></textarea></div>

            <div id="am_reclassify_block" class="border rounded p-2 bg-light d-none">
              <div class="d-flex justify-content-between align-items-center">
                <div><strong><?= e(t('activity.classification_label')) ?></strong> <span id="am_current_type"></span></div>
                <div>
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="afActivities.reclassify()"><?= e(t('activity.reclassify')) ?></button>
                </div>
              </div>
              <div id="am_interrupted_task_row" class="mt-2 small d-none">
                <strong><?= e(t('activity.interrupted_task_label')) ?></strong> <span id="am_interrupted_task_name"></span>
              </div>
            </div>
          </form>
        </div>

        <div class="tab-pane fade" id="am_tab_time">
          <div class="d-flex gap-2 mb-2">
            <button type="button" class="btn btn-sm btn-success" onclick="afActivities.startTimer()"><i class="bi bi-play-fill"></i> <?= e(t('activity.start_timer')) ?></button>
            <button type="button" class="btn btn-sm btn-warning" onclick="afActivities.stopTimer()"><i class="bi bi-pause-fill"></i> <?= e(t('activity.pause_timer')) ?></button>
          </div>
          <div class="row mb-2">
            <div class="col-6"><label class="form-label"><?= e(t('activity.field_status')) ?></label>
              <select class="form-select form-select-sm" id="am_status" onchange="afActivities.updateStatus()">
                <?php foreach (list_task_statuses() as $st): ?><option value="<?= e($st['slug']) ?>"><?= e($st['label']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-6"><label class="form-label"><?= e(t('activity.field_completion_pct')) ?></label>
              <input type="range" min="0" max="100" class="form-range" id="am_completion_pct" oninput="document.getElementById('am_pct_label').textContent=this.value+'%'" onchange="afActivities.updateProgress()">
              <span id="am_pct_label">0%</span>
            </div>
          </div>
          <div class="small text-muted mb-2" id="am_time_totals"></div>
          <h6 class="mt-3"><?= e(t('activity.manual_time_entry')) ?></h6>
          <form id="manualTimeForm" class="row g-2 mb-3">
            <div class="col-4"><input type="datetime-local" class="form-control form-control-sm" name="started_at" required placeholder="<?= e(t('activity.time_start')) ?>"></div>
            <div class="col-4"><input type="datetime-local" class="form-control form-control-sm" name="ended_at" placeholder="<?= e(t('activity.time_end')) ?>"></div>
            <div class="col-2"><input type="number" min="0" class="form-control form-control-sm" name="duration_minutes" placeholder="<?= e(t('activity.time_minutes')) ?>"></div>
            <div class="col-2"><button class="btn btn-sm btn-outline-primary w-100"><?= e(t('common.add')) ?></button></div>
            <div class="col-12"><input class="form-control form-control-sm" name="notes" placeholder="<?= e(t('activity.session_notes')) ?>"></div>
          </form>
          <div id="am_time_entries"></div>
        </div>

        <div class="tab-pane fade" id="am_tab_comments">
          <div id="am_comments" class="mb-3"></div>
          <form id="commentForm">
            <textarea class="form-control" id="am_new_comment_body" name="body" rows="2" placeholder="<?= e(t('activity.add_comment_placeholder')) ?>" required></textarea>
            <div class="text-end mt-2"><button class="btn btn-outline-primary"><?= e(t('activity.post')) ?></button></div>
          </form>
        </div>

        <div class="tab-pane fade" id="am_tab_interruptions">
          <p class="text-muted small"><?= e(t('activity.interruptions_tab_hint')) ?></p>
          <div id="am_interruptions_list"></div>
        </div>

        <div class="tab-pane fade" id="am_tab_history">
          <div id="am_history" class="small"></div>
        </div>
      </div>
      <div class="modal-footer">
        <div class="me-auto d-flex gap-2">
          <button type="button" class="btn btn-outline-danger d-none" id="am_delete_btn" onclick="afActivities.deleteActivity()"><i class="bi bi-trash3"></i> <?= e(t('activity.delete_task')) ?></button>
          <button type="button" class="btn btn-outline-secondary d-none" id="am_clone_btn" onclick="afActivities.openMoveOrClone('clone')"><i class="bi bi-files"></i> <?= e(t('activity.clone')) ?></button>
          <button type="button" class="btn btn-outline-secondary d-none" id="am_move_btn" onclick="afActivities.openMoveOrClone('move')"><i class="bi bi-arrow-left-right"></i> <?= e(t('activity.move')) ?></button>
        </div>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.close')) ?></button>
        <button type="button" class="btn btn-primary" id="activitySaveBtn" onclick="afActivities.save()"><?= e(t('activity.save')) ?></button>
      </div>
    </div>
  </div>
</div>

<script>
window.AF_I18N_VACATION_WARNING = <?= json_encode(t('activity.vacation_conflict_warning')) ?>;
</script>
<div class="modal fade" id="taskMoveCloneModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="taskMoveCloneTitle"><?= e(t('activity.move_title')) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p id="taskMoveCloneSummary" class="text-muted small"></p>
        <label class="form-label"><?= e(t('activity.destination_project')) ?></label>
        <select class="form-select" id="taskMoveCloneProject" <?= !$amTargetProjects ? 'disabled' : '' ?>>
          <?php if (!$amTargetProjects): ?>
            <option value=""><?= e(t('activity.no_projects_available')) ?></option>
          <?php else: ?>
            <?php foreach ($amTargetProjects as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['code']) ?>)</option><?php endforeach; ?>
          <?php endif; ?>
        </select>
        <?php if (!$amTargetProjects): ?>
          <p class="text-danger small mt-2 mb-0"><?= e(t('activity.no_eligible_projects')) ?></p>
        <?php endif; ?>
        <p class="text-muted small mt-2 mb-0" id="taskMoveCloneNote"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(t('common.cancel')) ?></button>
        <button type="button" class="btn btn-primary" id="taskMoveCloneConfirmBtn" onclick="afActivities.confirmMoveOrClone()"><?= e(t('common.confirm')) ?></button>
      </div>
    </div>
  </div>
</div>
