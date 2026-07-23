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
?>
<div class="modal fade" id="activityModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="activityModalTitle">New planned activity</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <ul class="nav nav-tabs px-3 pt-2" id="activityTabs" style="display:none">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#am_tab_details">Details</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#am_tab_time">Time &amp; progress</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#am_tab_comments">Comments</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#am_tab_history">History</button></li>
      </ul>
      <div class="modal-body tab-content">
        <div class="tab-pane fade show active" id="am_tab_details">
          <form id="activityForm">
            <input type="hidden" name="id" id="am_id">
            <div class="mb-2"><label class="form-label">Title *</label><input class="form-control" name="title" id="am_title" required></div>
            <div class="mb-2"><label class="form-label">Description</label><textarea class="form-control" name="description" id="am_description" rows="2"></textarea></div>
            <div class="row">
              <div class="col-md-6 mb-2"><label class="form-label">Project</label>
                <select class="form-select" name="project_id" id="am_project_id">
                  <option value="">No project</option>
                  <?php foreach ($amProjects as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6 mb-2"><label class="form-label">Category</label>
                <select class="form-select" name="category_id" id="am_category_id">
                  <option value="">—</option>
                  <?php foreach ($amCategories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-2"><label class="form-label">Assignee *</label>
                <select class="form-select" name="assignee_id" id="am_assignee_id" required>
                  <?php foreach ($amPeople as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['full_name']) ?></option><?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6 mb-2"><label class="form-label">Requester *</label>
                <div class="input-group">
                  <select class="form-select" name="requester_id" id="am_requester_id" required>
                    <?php foreach ($amPeople as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['full_name']) ?></option><?php endforeach; ?>
                  </select>
                  <button class="btn btn-outline-secondary" type="button" onclick="afQuickAdd.newPerson('am_requester_id')"><i class="bi bi-person-plus"></i></button>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-2"><label class="form-label">Planned start</label><input type="datetime-local" class="form-control" name="planned_start_at" id="am_planned_start_at"></div>
              <div class="col-md-6 mb-2"><label class="form-label">Target completion</label><input type="datetime-local" class="form-control" name="target_completion_at" id="am_target_completion_at"></div>
            </div>
            <div class="row">
              <div class="col-md-4 mb-2"><label class="form-label">Estimated (hours)</label><input type="number" min="0" step="0.25" class="form-control" name="estimated_hours" id="am_estimated_hours" placeholder="e.g. 1.5"></div>
              <div class="col-md-4 mb-2"><label class="form-label">Priority</label>
                <select class="form-select" name="priority" id="am_priority">
                  <option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="urgent">Urgent</option>
                </select>
              </div>
              <div class="col-md-4 mb-2"><label class="form-label">Request channel</label>
                <select class="form-select" name="request_channel" id="am_request_channel">
                  <option value="">—</option>
                  <?php foreach (REQUEST_CHANNELS as $c): ?><option value="<?= $c ?>"><?= e(request_channel_label($c)) ?></option><?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="mb-2"><label class="form-label">Parent task (optional — use to break work into subtasks)</label>
              <select class="form-select" name="parent_activity_id" id="am_parent_activity_id">
                <option value="">None — top-level task</option>
              </select>
            </div>
            <div class="mb-2"><label class="form-label">Tags (comma separated)</label><input class="form-control" name="tags" id="am_tags"></div>
            <div class="mb-2 form-check">
              <input type="checkbox" class="form-check-input" name="is_milestone" id="am_is_milestone" value="1">
              <label class="form-check-label" for="am_is_milestone">Milestone</label>
            </div>
            <div class="row" id="am_repeat_block">
              <div class="col-md-6 mb-2"><label class="form-label">Repeat</label>
                <select class="form-select" name="repeat_frequency" id="am_repeat_frequency">
                  <option value="">Does not repeat</option>
                  <option value="daily">Daily</option>
                  <option value="weekly">Weekly</option>
                </select>
              </div>
              <div class="col-md-6 mb-2"><label class="form-label">Repeat until</label><input type="date" class="form-control" name="repeat_until" id="am_repeat_until"></div>
            </div>
            <div class="mb-2"><label class="form-label">Notes</label><textarea class="form-control" name="notes" id="am_notes" rows="2"></textarea></div>

            <div id="am_reclassify_block" class="border rounded p-2 bg-light d-none">
              <div class="d-flex justify-content-between align-items-center">
                <div><strong>Classification:</strong> <span id="am_current_type"></span></div>
                <div>
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="afActivities.reclassify()">Reclassify…</button>
                </div>
              </div>
            </div>
          </form>
        </div>

        <div class="tab-pane fade" id="am_tab_time">
          <div class="d-flex gap-2 mb-2">
            <button type="button" class="btn btn-sm btn-success" onclick="afActivities.startTimer()"><i class="bi bi-play-fill"></i> Start timer</button>
            <button type="button" class="btn btn-sm btn-warning" onclick="afActivities.stopTimer()"><i class="bi bi-pause-fill"></i> Pause/stop timer</button>
          </div>
          <div class="row mb-2">
            <div class="col-6"><label class="form-label">Status</label>
              <select class="form-select form-select-sm" id="am_status" onchange="afActivities.updateStatus()">
                <?php foreach (ACTIVITY_STATUSES as $s): ?><option value="<?= $s ?>"><?= e(status_label($s)) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-6"><label class="form-label">Completion %</label>
              <input type="range" min="0" max="100" class="form-range" id="am_completion_pct" oninput="document.getElementById('am_pct_label').textContent=this.value+'%'" onchange="afActivities.updateProgress()">
              <span id="am_pct_label">0%</span>
            </div>
          </div>
          <div class="small text-muted mb-2" id="am_time_totals"></div>
          <h6 class="mt-3">Manual time entry</h6>
          <form id="manualTimeForm" class="row g-2 mb-3">
            <div class="col-4"><input type="datetime-local" class="form-control form-control-sm" name="started_at" required placeholder="Start"></div>
            <div class="col-4"><input type="datetime-local" class="form-control form-control-sm" name="ended_at" placeholder="End"></div>
            <div class="col-2"><input type="number" min="0" class="form-control form-control-sm" name="duration_minutes" placeholder="Min"></div>
            <div class="col-2"><button class="btn btn-sm btn-outline-primary w-100">Add</button></div>
            <div class="col-12"><input class="form-control form-control-sm" name="notes" placeholder="Session notes"></div>
          </form>
          <div id="am_time_entries"></div>
        </div>

        <div class="tab-pane fade" id="am_tab_comments">
          <div id="am_comments" class="mb-3"></div>
          <form id="commentForm" class="d-flex gap-2">
            <input class="form-control" name="body" placeholder="Add a comment…" required>
            <button class="btn btn-outline-primary">Post</button>
          </form>
        </div>

        <div class="tab-pane fade" id="am_tab_history">
          <div id="am_history" class="small"></div>
        </div>
      </div>
      <div class="modal-footer">
        <div class="me-auto d-flex gap-2">
          <button type="button" class="btn btn-outline-danger d-none" id="am_delete_btn" onclick="afActivities.deleteActivity()"><i class="bi bi-trash3"></i> Delete task</button>
          <button type="button" class="btn btn-outline-secondary d-none" id="am_clone_btn" onclick="afActivities.openMoveOrClone('clone')"><i class="bi bi-files"></i> Clone…</button>
          <button type="button" class="btn btn-outline-secondary d-none" id="am_move_btn" onclick="afActivities.openMoveOrClone('move')"><i class="bi bi-arrow-left-right"></i> Move…</button>
        </div>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="activitySaveBtn" onclick="afActivities.save()">Save activity</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="taskMoveCloneModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="taskMoveCloneTitle">Move task</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p id="taskMoveCloneSummary" class="text-muted small"></p>
        <label class="form-label">Destination project</label>
        <select class="form-select" id="taskMoveCloneProject" <?= !$amTargetProjects ? 'disabled' : '' ?>>
          <?php if (!$amTargetProjects): ?>
            <option value="">No projects available</option>
          <?php else: ?>
            <?php foreach ($amTargetProjects as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['code']) ?>)</option><?php endforeach; ?>
          <?php endif; ?>
        </select>
        <?php if (!$amTargetProjects): ?>
          <p class="text-danger small mt-2 mb-0">You're not a member of any project you could move or clone a task into.</p>
        <?php endif; ?>
        <p class="text-muted small mt-2 mb-0" id="taskMoveCloneNote"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="taskMoveCloneConfirmBtn" onclick="afActivities.confirmMoveOrClone()">Confirm</button>
      </div>
    </div>
  </div>
</div>
