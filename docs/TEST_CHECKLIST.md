# Manual test checklist

Mapped to the acceptance criteria. Run through this after installation
(`docs/INSTALL.md`) using the seeded demo accounts.

## Authentication & accounts

- [ ] Visit login.php while logged out — confirm the full marketing landing
      page renders: nav (Features/How it works/Reviews/Log in/Get started),
      hero with mascot, stats strip, About, Benefits, Features grid,
      "How it works" steps with mascot, mid-page CTA banner, Reviews with
      star ratings and mascot, and finally the preserved login card.
      Confirm the nav links and every "Get started"/"Log in" CTA scroll
      down to the login card (id="auth") rather than navigating away.
- [ ] On the landing page, confirm the login form, CAPTCHA question, error
      handling, "Forgot password?" and "Create an account" links all work
      exactly as before (this is the same form/logic, just restyled).
- [ ] Switch language via the landing page's nav language dropdown —
      confirm all landing page copy (hero, features, reviews, etc.) switches
      to Spanish, and the choice persists into the login card and beyond.
- [ ] Switch the color theme (from within the app, then log out) and revisit
      the landing page — confirm the hero, cards, mascot color, and stats
      band all re-theme correctly, including in the dark "blue" scheme.
- [ ] Register a new account with full name, email, password, secret
      question/answer — no email verification step, can log in immediately.
- [ ] Log in with a valid demo account (e.g. `carla.diaz@activityflow.test` /
      `Password123!`).
- [ ] On the Log in page, submit the form with a wrong answer to the "what
      is N + N?" security check — confirm you're bounced back with an
      "Incorrect answer" error and your login attempt was *not* consumed
      (i.e. it doesn't count toward the lockout below). Confirm the
      security question changes to a new one after any failed submission
      (correct or incorrect credentials), so the same answer can't be
      replayed. Confirm a correct answer plus correct credentials logs you
      in normally.
- [ ] On the Create account page, submit the form with a wrong answer to
      the security check — confirm the account is not created and the
      form is redisplayed with a fresh question. Confirm a correct answer
      lets registration proceed as before.
- [ ] Log in with a wrong password 6 times in under 15 minutes — confirm the
      6th attempt is rejected with a generic "too many attempts" message.
- [ ] Forgot password: enter a real email, answer the shown question
      correctly, set a new password, log in with it.
- [ ] Forgot password with an email that doesn't exist — confirm a question
      is still shown (not an "account not found" message) and the answer
      step fails generically.
- [ ] Change password and recovery question from Profile & Settings.
- [ ] As an administrator, add a person to the People Directory with no
      system account (e.g. email `newhire@activityflow.test`), assign them as
      a requester/assignee on a task, then register a new account using that
      same email — confirm the flash message says the existing directory
      entry was linked, and check the People Directory afterward to confirm
      there is still only **one** person row (not a duplicate) and the task
      it was assigned to still shows the same person.
- [ ] Log out, confirm the session is cleared and protected pages redirect to
      login.

## People & roles

- [ ] As `alicia.moreno@activityflow.test` (administrator), open Admin →
      Users & Roles, change a user's roles and account status.
- [ ] On Users & Roles, confirm the "Impersonate" button does NOT appear on
      your own row, on any other administrator's row, or on an
      inactive/locked account's row — only active PM/Employee/Viewer
      accounts show it.
- [ ] Click "Impersonate" on an active Employee/PM/Viewer account and
      confirm the dialog — confirm you land on the dashboard logged in as
      that user (their name in the topbar, their theme/locale, their nav
      items — no Admin section), and an orange banner at the top of every
      page reads "You are impersonating {name}." with a "Stop impersonating"
      button.
- [ ] While impersonating, try to reach an admin-only page directly (e.g.
      `admin/users.php`) — confirm it's denied, exactly as it would be for
      that user normally.
- [ ] Click "Stop impersonating" — confirm you're returned to your own
      admin account (dashboard, your own name/theme, Admin nav visible
      again), and the banner is gone.
- [ ] Attempt to call `api/impersonate.php` with `action=start` and your own
      user id, or the id of another administrator, or the id of an
      inactive/locked account — confirm each is rejected with a clear error
      and no session change occurs.
- [ ] Check Audit Log after an impersonate/stop cycle — confirm both
      `impersonation_started` and `impersonation_ended` entries are recorded
      against the impersonated user, each attributing the action to the
      admin (not the impersonated user) as the actor.
- [ ] Add a new person from the People Directory; add one with a name/email
      similar to an existing person and confirm the duplicate warning
      appears.
- [ ] Add a new requester inline from the quick-add task form without
      leaving the page.

## Planned & unplanned work

- [ ] From My Day, create a planned activity with a scheduled time; confirm
      it appears in the Planned column and on the Calendar.
- [ ] In the New/Edit Activity dialog, use the Planned start field's calendar
      picker to choose a day — confirm the time defaults to 9:00 AM. Do the
      same for Target completion — confirm it defaults to 5:00 PM. Then
      manually change just the hour on either field and confirm it's kept
      (not reset) until you pick a different day again.
- [ ] Use the floating quick-add button to log an unplanned task in under
      10 seconds; confirm it's tagged "Unplanned" everywhere it appears.
- [ ] Quick-add a task that interrupts an in-progress task; confirm the
      interruption is recorded (visible on the Timeline detail panel).
- [ ] Re-open that same unplanned task later from My Tasks/Team
      Activities/Task Board — confirm an "Interrupted task:" line appears
      near the Classification badge showing the title of the task it
      interrupted. Quick-add a second unplanned task WITHOUT picking an
      interrupted task and confirm that one shows no such line. Confirm a
      normal planned task, and the planned task that WAS interrupted (not
      the interrupter), also show no such line.
- [ ] On that same unplanned task, click the task name in the "Interrupted
      task:" line — confirm the dialog swaps in place to the interrupted
      planned task (title, project, etc. all update to the other task)
      rather than opening a second dialog or navigating away.
- [ ] On My Tasks, confirm the planned task you interrupted above shows a
      small orange lightning-bolt icon next to its title (hover to see the
      tooltip). Confirm tasks that were never interrupted show no icon.
- [ ] Open that interrupted planned task and go to its new "Interruptions"
      tab — confirm it lists the unplanned task(s) that interrupted it
      (title, who logged it, when, any notes). Click one of the listed
      interruptions and confirm it opens that unplanned task in the same
      dialog. Confirm a task that was never interrupted shows "No
      interruptions recorded." on this tab instead.
- [ ] Quick-add a third unplanned task that interrupts the SAME planned task
      again — confirm the Interruptions tab now lists both interrupting
      tasks, most recent included, and the My Tasks icon/tooltip still
      shows correctly (doesn't duplicate or break with more than one).
- [ ] Try to reclassify a task's planned/unplanned status as a Project
      Manager or Administrator — confirm a reason is required and the
      original classification remains visible in its audit history.
- [ ] Confirm an Employee account cannot reclassify a task (button/permission
      absent, and a direct API call is rejected server-side).
- [ ] Open a task you're assigned to (or created) as an Employee from My
      Tasks — confirm a "Delete task" button appears in the Edit Activity
      dialog, a confirmation warning appears on click, and deleting removes
      the task, its comments, and its time entries. Confirm the same Employee
      does NOT see the Delete button on a task assigned to someone else, and
      that a direct API call to delete it is rejected server-side.
- [ ] As an Administrator or the owning Project Manager, confirm the Delete
      button appears and works on any task in the project regardless of who
      it's assigned to, opened from Team Activities, My Day, Calendar, and
      the Project Board (all routes into the same Edit Activity dialog).
      Confirm a subtask of a deleted task is not itself deleted (just
      detached from the removed parent).
- [ ] Open a task you can edit and click "Clone…" — choose a destination
      project (same or different) and confirm a new task appears titled
      "<original title> (Copy)" with the same assignee/requester/estimate/
      priority/tags, fresh status/0% progress, and no comments or time
      entries carried over. Click "Move…" on a task instead and confirm its
      project changes but its comments/time entries/history stay attached
      (same task, not a copy).
- [ ] On a project's Task Board, use the team-member filter dropdown to
      select one or more members — confirm the board only shows tasks
      assigned to those people, the button label updates ("All team
      members" / "1 member selected" / "N members selected"), and the
      selection persists after a drag-and-drop status change (which
      reloads the page). Re-check "All team members" and confirm it
      clears the individual selections and shows every task again.
- [ ] On the same Task Board, use the status filter dropdown to select one
      or more statuses (e.g. just "In progress" and "Blocked") — confirm
      only those columns appear, the counts still match, and the button
      label updates. Confirm the member and status filters combine
      correctly (e.g. one member + two statuses shows only that member's
      tasks in those two columns) and both survive a drag-and-drop reload.
      Re-check "All statuses" and confirm every column reappears.
- [ ] On My Tasks and Team Activities, select several tasks via the row
      checkboxes (and the header "select all"), then use "Clone selected" /
      "Move selected" from the bulk bar — confirm all selected tasks are
      cloned/moved together. Confirm the destination dropdown only lists
      projects you're a member of (unless you're an admin or PM, who see
      all), and that a direct API call naming a project you don't belong to
      is rejected server-side.

## Projects & collaboration

- [ ] Create a project as a Project Manager, add two members with different
      project roles.
- [ ] Add both planned and unplanned tasks to the project; open Project
      Details and confirm progress, unplanned effort, and "requesters
      generating work for this project" all update.
- [ ] On that same project, confirm the "Tasks by status" doughnut chart and
      "Tasks by assignee" bar chart both render with the correct counts.
      Open a brand-new project with no tasks yet and confirm both cards show
      a plain "No tasks yet." message instead of a blank chart area.
- [ ] Switch the progress method (duration-weighted vs. simple count) and
      confirm the percentage and label change accordingly.
- [ ] Mark a task cancelled and confirm it drops out of the progress
      denominator.
- [ ] As an administrator (or the owning Project Manager), open an existing
      project's detail page and click "Edit project" — confirm every field
      (name, code, description, owner, department, dates, priority, status,
      planned hours, color, archived flag, notes) loads with its current
      value and saves correctly. Confirm the button is not shown, and a
      direct API call is rejected, for an Employee or a Project Manager who
      doesn't own the project.
- [ ] Edit a project's code to one already used by another project — confirm
      it's rejected instead of failing with a database error.
- [ ] Edit a project to change its owner — confirm the new owner appears in
      the Members list as project manager.
- [ ] As an administrator (or the owning Project Manager), click "Delete
      project" on a project with several tasks (some with comments and time
      entries) and members — confirm the warning modal shows the correct
      task/member counts, the Delete button stays disabled until you type the
      exact project name, and after confirming, the project, its tasks, their
      comments/time entries, and its members are all gone, and you're
      redirected to the Projects list. Confirm an Employee or non-owning PM
      does not see the Delete button and a direct API call is rejected.
      Confirm an unrelated task from a *different* project is unaffected.
- [ ] In both "New project" and "Edit project", use the Description field's
      rich text toolbar (headings, bold/italic/underline/strike, lists,
      blockquote, link) and save — confirm the formatting appears correctly
      on the project detail page. Try pasting a link with a `javascript:`
      URL and confirm it's stripped down to plain text rather than becoming
      a clickable link.
- [ ] With browser dev tools, block the Quill CDN request (or simulate it
      failing) and confirm the Description field still shows as a plain,
      editable textarea instead of breaking the form.

- [ ] In the Edit Activity dialog's Comments tab, add a comment, then edit it —
      confirm the body updates, an "(edited ...)" timestamp appears next to the
      original posted time, and the Edit button only appears on your own
      comments (not on comments posted by other users). Confirm a direct API
      call to edit someone else's comment is rejected server-side, and that
      saving an empty comment is blocked.
- [ ] On the Task Board, open an existing task and click through to the
      "Time & Progress" (or Comments/History) tab, then close the dialog
      without saving. Click "Add task" to open a fresh New Task dialog —
      confirm it shows the full Details tab (title, project, assignee,
      dates, priority, tags, notes, etc.), not just the few fields from
      whichever tab was left open on the previous task.

- [ ] On the Task Board, confirm each task card shows a thin progress bar
      and a "X%" label reflecting its Completion % value.

- [ ] On the Task Board, open the status filter dropdown, check one or two
      statuses, and click Apply — confirm the board filters as expected.
      Reload the page (or navigate away and back to this project's board)
      without any filter query in the URL — confirm the same statuses are
      still checked and the board is still filtered the same way.
      Re-open the dropdown, check "All statuses", and Apply — confirm that
      after this, reloading the board plainly shows all statuses again
      (the "All" preference sticks too, it doesn't fall back to the last
      non-empty selection). Confirm this preference is local to this
      browser/PC (per project) and doesn't affect other projects' boards.

- [ ] Throttle the network (browser dev tools "Slow 3G") and trigger a
      server action anywhere in the app (save a task, save a project, delete
      something, run a report, load the calendar, apply a board filter) —
      confirm a full-screen loading overlay with a spinner appears and
      blocks clicks/typing elsewhere until the response comes back, then
      disappears. Confirm a near-instant action (e.g. toggling a quick
      filter) does NOT show a visible flash of the overlay.
- [ ] While the overlay is showing for one action, confirm a second
      concurrent action (e.g. the notification bell polling in the
      background) doesn't cause the overlay to disappear early — it should
      only hide once every in-flight request has finished.
- [ ] Open an existing task, go to the "Time & Progress" tab and log a
      manual time entry, then go to the Comments tab and post a comment —
      confirm the loading overlay appears briefly for each and then
      disappears on its own (does NOT stay stuck spinning afterward, and
      you can still click/type elsewhere once it clears).
- [ ] On Login, Register, and Forgot/Reset Password (plain full-page-submit
      forms), confirm the overlay appears immediately on clicking
      Submit/Login/Register. On a dialog that saves via AJAX (e.g. Edit
      Project), confirm the overlay still appears/disappears correctly and
      doesn't get stuck open.

- [ ] In "New project", check a few people in the "Assign people to this
      project" list and create the project — confirm the Members table on
      the resulting project detail page shows the owner as Project Manager
      plus everyone you checked as Contributor.
- [ ] In "Edit project" on an existing project, confirm the member checkbox
      list is pre-checked for every current member. Uncheck one existing
      member and check one new person, then save — confirm the unchecked
      person is removed from Members and the newly-checked person appears as
      Contributor, and that no one else's existing role (e.g. a Reviewer
      added via the separate "Add Member" button) was changed. Confirm the
      project owner can never be removed this way even if you uncheck them.
- [ ] As a person assigned to at least one project (not necessarily its
      owner), log in and open "My Projects" from the sidebar — confirm only
      projects you're a member of appear, each showing your role on that
      project. Confirm a person with no project memberships sees an empty
      state, and a logged-in user whose account isn't linked to a People
      Directory entry sees the "not linked" message instead of an error.
- [ ] In the New/Edit Activity dialog, select a Project — confirm the
      Assignee dropdown narrows to just that project's members (with a small
      hint text explaining the list is filtered) and, if the previously
      selected assignee isn't one of them, the field jumps to a valid member
      automatically. Switch the Project field back to "No project" and
      confirm the full people list returns. Open an existing task whose
      assignee somehow isn't a current member of its project (e.g. they were
      later removed) — confirm that assignee still shows up (not silently
      dropped) when the dialog opens, so editing the task doesn't look like
      data went missing.

## Time tracking

- [ ] Start a timer on a task, then try to start a second timer on another
      task for the same user — confirm it's blocked.
- [ ] Pause/stop the timer and confirm a time entry with a duration is
      recorded.
- [ ] Add a manual time entry; try a negative duration and confirm it's
      rejected.

## Calendar & timeline

- [ ] On the Calendar page, drag a task to a new day/time and confirm it
      saves (reload the page to verify persistence).
- [ ] On the Calendar page, use the employee filter dropdown to check two or
      more people — confirm the calendar shows both people's tasks together,
      the button label updates ("All employees" / "1 employee selected" /
      "N employees selected"), and re-checking "All employees" clears the
      selection and shows everyone's tasks again. Confirm the calendar loads
      by default showing just your own tasks (if your account is linked to
      a person), matching the old single-select default.
- [ ] Do the same for the project filter dropdown — select multiple
      projects and confirm the calendar shows tasks from all of them at
      once, with "All projects" behaving the same way. Confirm the employee
      and project filters combine correctly (e.g. two employees + one
      project shows only those employees' tasks within that project).
- [ ] On the Timeline page, select a date with both planned and unplanned
      activity, press Play, and watch unplanned insertions appear at their
      actual requested time relative to the plan.
- [ ] Click a timeline block and confirm the detail panel shows requester,
      timing, classification, and interruption impact where applicable.

## Dashboards & reports

- [ ] Personal dashboard shows today's planned/unplanned split, overdue
      tasks, and top requesters.
- [ ] Manager dashboard (PM/Admin only) shows workload by employee and
      unplanned work by requester/department, and respects the filters.
- [ ] Reports Center: run "Unplanned tasks by requester" and "Overdue tasks",
      export one as CSV, and use Print/PDF.
- [ ] Requester Analytics page shows the date range and sample size, and the
      ranking panels populate.

## Task status management (admin)

- [ ] As an Administrator, open Administration → Task Statuses — confirm all
      8 default statuses appear with their current text, internal key, and
      a live count of tasks currently using each.
- [ ] Rename a status's text (e.g. "Blocked" → "On Hold") and save — confirm
      it now shows with the new text everywhere it appears (status filter
      dropdowns, task board columns, task badges on My Tasks/Team
      Activities/My Day, the Edit Activity dialog's Status field) without
      any existing task losing its status or history.
- [ ] Add a new custom status (e.g. "Needs Review") — confirm it appears in
      all the same places, can be assigned to a task via the Edit Activity
      dialog's Status field, and appears as its own column on the Task
      Board.
- [ ] Delete a custom/default non-system status that has zero tasks on it —
      confirm a simple confirmation is enough and it disappears from every
      list immediately.
- [ ] Assign a handful of tasks to a status, then delete that status as an
      admin — confirm the delete dialog shows the exact number of tasks
      using it and requires you to pick a replacement status before
      proceeding. Confirm that after confirming, every one of those tasks
      now shows the replacement status (check My Tasks and the Task Board),
      and the deleted status no longer appears anywhere.
- [ ] Confirm the four required statuses (Planned, In Progress, Completed,
      Cancelled) have no Delete button and that a direct API call to delete
      one is rejected server-side, while their text can still be renamed
      normally like any other status.
- [ ] As a non-Administrator (Project Manager, Employee, Viewer), confirm
      Administration → Task Statuses is not reachable (redirects/denies)
      and a direct API call to save or delete a status is rejected.
- [ ] Confirm a task's completion percentage, "completed" counts on
      dashboards/reports, and project progress calculations still work
      correctly after renaming "Completed"'s text — these depend on the
      status's internal key, not its display text, so renaming should have
      no effect on them.

## Request channel management (admin)

- [ ] As an Administrator, open Administration → Request Channels — confirm
      all 10 default channels (Manager Request, Coworker Request, Customer
      Request, Meeting, Chat, Phone, Walk-up, System Incident,
      Self-initiated, Other) appear with their current text, internal key,
      and a live count of tasks currently using each.
- [ ] Rename a channel's text (e.g. "Phone" → "Phone Call") and save —
      confirm it now shows with the new text everywhere it appears (Edit
      Activity dialog's Request Channel field, Quick-add's Request Channel
      field, the Reports request-channel filter dropdown, and the dashboard
      "by source" chart) without any existing task losing its channel.
- [ ] Add a new custom channel (e.g. "Slack DM") — confirm it appears in all
      the same places and can be assigned to a task via the Edit Activity
      dialog or Quick-add.
- [ ] Delete a channel that has zero tasks on it — confirm a simple
      confirmation is enough and it disappears from every list immediately.
- [ ] Assign a handful of tasks to a channel, then delete that channel as an
      admin — confirm the delete dialog shows the exact number of tasks
      using it and requires you to pick a replacement channel before
      proceeding. Confirm that after confirming, every one of those tasks
      now shows the replacement channel, and the deleted channel no longer
      appears anywhere.
- [ ] As a non-Administrator (Project Manager, Employee, Viewer), confirm
      Administration → Request Channels is not reachable (redirects/denies)
      and a direct API call to save or delete a channel is rejected.
- [ ] Confirm a task's Request Channel can still be left blank ("—") on both
      Quick-add and the Edit Activity dialog, and that this displays
      correctly wherever the channel is shown.

## Release management (admin)

- [ ] As an Administrator, open Administration → Releases and create a new
      release with a name, description, start date, and launch (end) date
      spanning at least a few weeks — confirm it's rejected if the launch
      date is before the start date, or if the span is under 8 days (each
      of the 8 default phases needs at least one day).
- [ ] After creating a release, open its Manage page — confirm 8 phases
      (Grooming and BRD, FDS and TDS, Scope Commit, Build, SIT, UAT and
      L&P, Code Freeze, MTP, in that order — or whatever is currently
      configured in Administration → Release Phase Templates) were created
      automatically, their dates are contiguous with no gaps or overlaps,
      the first phase starts on the release's start date, and the last phase ends on the
      release's launch date.
- [ ] Edit a phase's dates to a new range still inside the release's
      start/launch window and not overlapping any other phase — confirm it
      saves. Then try dates that exceed the release's launch date, and
      separately dates that overlap an adjacent phase — confirm both are
      rejected with a clear error and nothing is saved.
- [ ] Add a new custom phase (e.g. "Hypercare") with its own date range —
      confirm it appears in the phase list. Edit a phase's name. Delete a
      phase — confirm it disappears and the others are unaffected.
- [ ] Edit the release's own name/description/dates — confirm it saves
      without needing to touch its phases.
- [ ] From the release's Manage page, associate an existing project that
      isn't yet part of any release — confirm it appears in the release's
      Associated Projects list and disappears from the "associate" picker.
- [ ] Confirm a project that already belongs to a release does NOT appear
      in another release's "associate" picker (only unassigned projects
      are offered there).
- [ ] Use "Move to..." on a project already in Release A to move it to
      Release B — confirm it now shows under Release B and no longer under
      Release A, and that this is the only way to reassign an
      already-associated project (there's no way to "associate" it directly
      into a second release).
- [ ] Disassociate a project from a release — confirm the project itself
      still exists and is fully intact (check its task board, members,
      etc.), and it now reappears in every release's "associate" picker.
- [ ] Delete a release that has associated projects — confirm the
      confirmation dialog says the projects will be disassociated, not
      deleted, and after deleting, verify those projects still exist and
      simply show no release. Confirm the release's phases are gone too.
- [ ] As a non-Administrator (Project Manager, Employee, Viewer), confirm
      Administration → Releases and its Manage page are not reachable
      (redirect/deny), and a direct API call to any release_* admin action
      is rejected.
- [ ] As any role that can view a project belonging to a release, confirm
      the project's card (Projects page) and detail page both show a
      read-only "Release: <name>" badge — for non-admins this badge should
      not be a clickable link (since the admin Releases pages are
      Administrator-only).

## Release phase templates (admin)

- [ ] As an Administrator, open Administration → Release Phase Templates —
      confirm the 8 defaults (Grooming and BRD, FDS and TDS, Scope Commit,
      Build, SIT, UAT and L&P, Code Freeze, MTP) appear in that order.
- [ ] Rename a default phase (e.g. "SIT" → "System Integration Testing")
      and save — confirm the new name appears in the list, but any
      already-created release's existing phases (from before the rename)
      keep their original names unchanged.
- [ ] Use the up/down arrows to reorder a phase — confirm the list re-sorts
      and the position numbers update; confirm the arrows are disabled (or
      no-op) at the very top and bottom of the list.
- [ ] Add a new default phase (e.g. "Hypercare") — confirm it's appended to
      the end of the list and that creating a new release afterward
      includes it as one of the auto-generated phases, in its position in
      the list.
- [ ] Delete a default phase — confirm it disappears from the list and
      that creating a new release afterward no longer includes it, while
      any release created *before* the deletion is completely unaffected.
- [ ] Try adding two default phases with the same name (case-insensitive) —
      confirm it's rejected with a clear error.
- [ ] Open Administration → Releases' "Add release" dialog — confirm the
      hint text under the date fields lists the exact current default
      phase names in order, and includes a link to manage them.
- [ ] Delete every default phase, then create a new release — confirm it's
      accepted (minimum span drops to 1 day) and the release ends up with
      zero phases; confirm the "Add release" dialog's hint reflects that no
      defaults are configured and phases must be added manually to the
      release afterward. Restore the defaults afterward for the rest of
      testing.
- [ ] As a non-Administrator (Project Manager, Employee, Viewer), confirm
      Administration → Release Phase Templates is not reachable
      (redirect/deny), and a direct API call to any
      release_phase_template_* admin action is rejected.

## Vacations

- [ ] As any logged-in user, open the "Vacations" nav item — confirm it's
      reachable by every role (Administrator, Project Manager, Employee,
      Viewer), unlike the Administration-only sections.
- [ ] Click "Add vacation" and submit a consecutive date range for yourself
      — confirm it appears on the calendar in the current month, colored
      consistently with your entry in the top people filter's swatches.
- [ ] Try submitting a vacation whose dates overlap one you already have —
      confirm it's rejected with a clear error; submitting an adjacent,
      non-overlapping range (e.g. starting the day after an existing entry
      ends) should succeed. Confirm two separate, non-consecutive blocks of
      days off require two separate "Add vacation" entries — there's no way
      to submit a single entry with a gap in it.
- [ ] As a non-Administrator, confirm the "Add vacation" dialog only lets
      you submit time off for yourself (no person picker) — and that a
      direct API call to create a vacation for someone else is rejected. As
      an Administrator, confirm you can pick any person when creating a new
      entry.
- [ ] Click an existing vacation event on the calendar you own (or, as an
      Administrator, one that belongs to someone else) — confirm the dialog
      opens editable with a Delete button; click one that belongs to someone
      else while NOT an Administrator — confirm the dialog opens read-only
      (no Save/Delete, fields disabled) and its Notes field is blank even if
      the owner set one (notes are private to the owner and admins).
- [ ] Use the calendar's view switcher to go from Month to Year view and
      back, and use prev/next to move to a different month — confirm
      vacations render correctly in both views and the "Today" button
      returns to the current month.
- [ ] Use the top people multi-select filter to narrow the calendar (and
      the list below it) to one or two specific people — confirm both the
      calendar and the conflicts list update to match, and reverting to "All
      people" restores everyone.
- [ ] As an Administrator or Project Manager, create/edit a task so its
      assignee's planned dates fall within an existing vacation for that
      assignee — confirm the Edit Activity dialog shows a warning banner
      naming the person and the vacation's dates as soon as you pick the
      conflicting assignee/dates (before saving), and confirm the same
      banner appears automatically when reopening the saved task afterward.
- [ ] Confirm a task with a vacation conflict shows a warning icon next to
      its title on My Tasks, Team Activities, and the Task Board, and that
      resolving the conflict (rescheduling the task or removing/moving the
      vacation) makes the icon disappear on next reload.
- [ ] On the Vacations page, confirm the "Vacation & Task Conflicts" list
      below the calendar shows every currently-conflicting task (not just
      ones on the current calendar month), with the person, vacation dates,
      task title, task dates, and project — and that clicking "Open" on a
      row opens that task in the same Edit Activity dialog used everywhere
      else, letting you reschedule or reassign it directly from this list.
- [ ] Delete a vacation entry — confirm it disappears from the calendar and
      any task that no longer conflicts with anything loses its warning
      badge on next reload, and drops off the conflicts list immediately.
- [ ] Delete the person record (or deactivate) tied to a vacation — this is
      a low-priority edge case, but confirm nothing on the Vacations page
      errors out if a vacation's person is no longer active.

## Task description rich text

- [ ] Open the New Task dialog from My Tasks, Team Activities, the Task
      Board, Calendar, My Day, or Vacations' conflicts list — confirm the
      Description field renders as a Quill WYSIWYG toolbar/editor (bold,
      italic, underline, strike, headers, ordered/bullet lists, blockquote,
      link) rather than a plain textarea, on every one of those pages.
- [ ] Format some description text (e.g. a bold word, a bullet list, a
      link), save the task, and reopen it — confirm the formatting is
      preserved exactly as entered.
- [ ] Open task A (with a rich-text description), close the dialog without
      saving, then open a *different* task B — confirm task B's editor
      shows B's own description, not a leftover copy of A's (this is the
      main risk with reusing one modal/editor instance across many tasks).
- [ ] Open the New Task dialog after having just viewed an existing task —
      confirm the description editor starts empty, not carrying over the
      previous task's content.
- [ ] Attempt to submit a description containing a `<script>` tag or an
      `onerror=` attribute (e.g. by pasting raw HTML) — confirm it's
      stripped down to the safe allow-list of tags on save (same
      sanitize_html() allow-list already used for project descriptions) and
      never executes when the task is reopened.
- [ ] Clone or move a task that has a rich-text description — confirm the
      formatting carries over intact to the new/destination task.
- [ ] With the Quill CDN blocked (e.g. via browser dev tools' network
      blocking), confirm the Description field falls back to a plain,
      fully-functional textarea instead of breaking the dialog.

## Task comment rich text

- [ ] Open any task's Comments tab — confirm the "add a comment" box renders
      as a Quill WYSIWYG toolbar/editor (bold, italic, underline, strike,
      headers, ordered/bullet lists, blockquote, link) rather than a plain
      single-line input.
- [ ] Format a new comment (e.g. a bold word, a bullet list, a link) and
      post it — confirm it appears in the comment list with the formatting
      preserved, not as literal HTML text.
- [ ] Post a comment, then reopen the same task later (or reload the page) —
      confirm the formatting is still there.
- [ ] Click "Edit" on one of your own comments — confirm the inline editor
      also becomes a Quill toolbar/editor pre-filled with the comment's
      existing formatting, edit it, and save — confirm the updated
      formatting is preserved and the "(edited ...)" note appears.
- [ ] Click "Edit" on a comment, then "Cancel" — confirm the comment reverts
      to its original read-only display with no changes and no leftover
      editor controls.
- [ ] Post a comment on task A, close the dialog, then open a *different*
      task B — confirm task B's "add a comment" box starts empty rather than
      carrying over what you typed for task A (the shared modal/editor
      instance is reused across every task opened in a page session).
- [ ] Try to post a comment that's empty or contains only formatting with no
      actual text (e.g. just pressing Enter, or bolding nothing) — confirm
      it's rejected with a "Comment cannot be empty" message rather than
      being saved as blank.
- [ ] Attempt to submit a comment containing a `<script>` tag or an
      `onerror=` attribute (e.g. by pasting raw HTML) — confirm it's
      stripped down to the safe allow-list of tags on save (same
      sanitize_html() allow-list already used for descriptions) and never
      executes when the task is reopened.
- [ ] With the Quill CDN blocked (e.g. via browser dev tools' network
      blocking), confirm both the new-comment box and the inline comment
      editor fall back to plain, fully-functional textareas instead of
      breaking the dialog.

## Releases (open viewing, admin-only editing)

- [ ] As an Employee or Viewer, confirm "Releases" appears in the main
      sidebar nav (not under "Administration"), and clicking it loads
      `releases.php` showing the full list of releases with their start/
      launch dates and phase/project counts.
- [ ] As an Employee or Viewer on the releases list, confirm there is no
      "Add release" button, and no Edit/Delete buttons on any row — only
      "Manage" (view) is available.
- [ ] As an Employee or Viewer, click "Manage" on a release to open
      `release_detail.php` — confirm the phases table and associated
      projects table are visible, but there is no "Add phase" button, no
      Edit/Delete on phases, no "Move"/"Disassociate" on projects, and no
      "associate a project" control at the bottom.
- [ ] Still as a non-admin, confirm editing/deleting a release from the top
      of the detail page is not available (no Edit/Delete buttons next to
      the release name).
- [ ] As an Administrator, confirm all of the above controls (add/edit/
      delete release, add/edit/delete phase, associate/move/disassociate
      project) are visible and working exactly as before, on both
      `releases.php` and `release_detail.php`.
- [ ] As an Administrator, confirm the "Manage default phases" link inside
      the New/Edit Release modal still goes to
      `admin/release_phase_templates.php` (that page remains under
      Administration — only release viewing/management moved).
- [ ] Attempt a release write action directly against the API as a
      non-admin (e.g. POST `action=release_save` to `api/admin.php` via
      browser dev tools) — confirm it's rejected with a 403, independent of
      whatever the UI shows (the UI hiding buttons is a convenience, not the
      actual security boundary).
- [ ] From a project's detail page, confirm the release badge still links
      to the release's detail page and works identically for both admins
      and non-admins (read-only either way for the badge itself).
- [ ] Confirm no links anywhere in the app (nav, project detail, release
      list) still point at the old `admin/releases.php` or
      `admin/release_detail.php` paths.

## Workload

- [ ] As an Employee or Viewer, confirm the "Workload" nav link is not shown,
      and navigating to `workload.php` directly is denied.
- [ ] As an Administrator or Project Manager, confirm the "Workload" nav
      link appears and the page loads with every active person shown by
      default (People filter = "All team members").
- [ ] Use the People multi-select to pick two or three specific people —
      confirm only those people's cards appear in the results, in the same
      "All / individual picks" checkbox behavior used on the Task Board and
      Vacations pages (picking anyone clears "All"; clearing everyone
      reverts to "All").
- [ ] Use the Task Statuses multi-select to pick one or more statuses (e.g.
      "In Progress" and "Blocked") — confirm each person's task count and
      task list only reflect tasks in those statuses, across *all* of their
      projects, not just one.
- [ ] Set a start and end date and re-run — confirm only tasks whose planned
      window overlaps that date range are counted (a task starting before
      the range but still open during it, and a task starting inside the
      range, should both count; a task entirely outside the range should
      not).
- [ ] Confirm the result for each person shows their name, a task count
      badge, and the list of matching task titles with each task's project
      name (or "No project" if unassigned to one).
- [ ] Switch "Sort by" between "Least busy first" and "Most busy first" —
      confirm the person cards reorder by task count accordingly (ties
      broken alphabetically).
- [ ] Confirm a person with zero matching tasks still appears in the results
      (not hidden) with a "No matching tasks in this range" note — this is
      the point of the feature, spotting who has free capacity.
- [ ] Click "Open" on a task in the workload results — confirm it opens the
      same shared Edit Task dialog used everywhere else, and that
      reassigning or rescheduling the task there and saving refreshes the
      workload results.
- [ ] Confirm switching the app language changes all Workload page labels
      (filters, sort options, empty states) to Spanish.

## Task "Is this an Issue?" flag

- [ ] Open the New Task dialog from any page — confirm the "Is this an
      Issue?" checkbox is present and unchecked by default.
- [ ] Check "Is this an Issue?", save the task, and reopen it — confirm the
      checkbox is still checked.
- [ ] Edit an existing task that is NOT tagged as an issue, check the box,
      and save — confirm it's now tagged; uncheck and save again — confirm
      it's no longer tagged (the flag isn't sticky/one-way).
- [ ] Clone a task tagged as an issue — confirm the new copy is also tagged
      as an issue. Clone a non-issue task — confirm the copy is not tagged.
- [ ] On My Tasks, Team Activities, and the Task Board, confirm a task
      tagged as an issue shows a red exclamation-octagon icon next to its
      title (alongside the existing milestone/interrupted/vacation-conflict
      icons where applicable), with a tooltip identifying it as an issue.
- [ ] On the Workload report, confirm a task tagged as an issue shows a red
      "Issue" badge next to its title.
- [ ] On My Tasks, use the new Issue filter (All / Issues only / Non-issues
      only) — confirm selecting "Issues only" shows only tagged tasks and
      "Non-issues only" excludes them, combined correctly with the other
      filters (status, type, priority, project, search).
- [ ] Repeat the Issue filter check on Team Activities.
- [ ] On the Task Board, toggle the "Issues only" switch — confirm the
      board narrows to just issue-tagged tasks across all status columns,
      and toggling it off restores the full board. Confirm it works
      together with the existing member/status filters.
- [ ] On the Workload report, set the Issue filter to "Issues only" —
      confirm each person's task list and count reflect only their
      issue-tagged tasks; "Non-issues only" excludes them.
- [ ] Confirm switching the app language changes the checkbox label, filter
      option text, and tooltip to Spanish.

## Mind Map

- [ ] As every role (Administrator, Project Manager, Employee, Viewer),
      confirm the "Mind Map" nav link is visible and the page loads without
      a permission error — this view is open to everyone.
- [ ] With no filters applied, confirm the map shows releases as purple
      boxes at the top, projects as boxes in each project's own assigned
      color below them, tasks as ellipses in that same project's color below
      their project, and people as orange dots with a light label pill below
      the tasks assigned to them — with connecting arrows following that
      hierarchy.
- [ ] Confirm projects/releases with no currently-visible tasks are hidden
      from the default (no-filter) view, so the map isn't cluttered with
      long-dormant projects.
- [ ] Confirm a person assigned to multiple tasks appears as a single node
      with multiple incoming arrows, not duplicated once per task.
- [ ] Confirm each project node shows its overall completion percentage
      (a second line under the project name) and that hovering it shows a
      "Completion: X%" tooltip — this should reflect the project's real,
      duration-weighted progress across all of its tasks, not just the
      tasks currently visible under an active filter.
- [ ] Confirm each task node shows its own completion percentage under its
      title, and that hovering it shows a tooltip with the completion
      percentage (and, for issue-tagged tasks, the Issue label as well).
- [ ] Change a task's completion percentage from its Edit Task dialog
      (opened via double-click) and confirm the map refreshes to reflect
      the new percentage on both the task node and its parent project node.
- [ ] Confirm each task node is colored the same as its own project (not by
      status), so tasks belonging to the same project are visually grouped
      and easy to tell apart from tasks in other projects. Tasks with no
      project show the same neutral gray as the "No project" bucket.
- [ ] Pick a project with a light/pale color (e.g. white or pale yellow) —
      confirm its project box and its tasks' text renders in dark text for
      readability. Pick a project with a dark/saturated color — confirm its
      text renders in white. Either way the label must stay easily readable.
- [ ] Switch to dark mode — confirm person node names stay clearly readable
      (light pill behind the name) even against the dark page background.
- [ ] Drag a node — confirm it can be moved both horizontally AND
      vertically, and stays exactly where dropped (it should not snap back
      to its original row/level).
- [ ] Confirm a task tagged as an Issue shows a warning icon in its label
      and an "Issue" tooltip on hover.
- [ ] Use the Releases filter (multi-select) to pick one release — confirm
      the map narrows to that release's projects/tasks/people, and that the
      release and its projects still show even if a project currently has
      zero matching tasks (so you can confirm the filter, not clutter,
      explains an empty branch).
- [ ] Use the Projects filter (multi-select) to pick one or more projects —
      confirm the map narrows accordingly and combines correctly with an
      active Releases filter.
- [ ] Use the Task Owners filter (multi-select) to pick one or more people —
      confirm only tasks assigned to those people (and their
      releases/projects) remain.
- [ ] Use the Task Statuses filter (multi-select) to pick one or more
      statuses — confirm only matching tasks remain, combined correctly with
      the other three filters.
- [ ] Confirm all four filter dropdowns follow the standard multi-select
      behavior: checking "All" clears individual picks, checking any
      individual pick clears "All", and unchecking the last individual pick
      reverts back to "All" (never an ambiguous nothing-selected state).
- [ ] Drag a node to a new position — confirm it stays where dropped (no
      physics snapping it back).
- [ ] Click "Reset Layout" after dragging nodes — confirm the map rebuilds
      in its clean hierarchical arrangement.
- [ ] Double-click a task node — confirm the Edit Task dialog opens for that
      task, and that saving a change (e.g. reassigning it) refreshes the map
      with the update reflected.
- [ ] Click "Download PNG" — confirm a PNG image of the current map
      (including any manual node rearrangement) downloads.
- [ ] Click "Download JPG" — confirm a JPG image downloads with a solid
      white background (not black/transparent).
- [ ] As a non-admin, non-viewer role, confirm the map only shows
      projects/tasks you're already allowed to see elsewhere (project owner
      or member) — it should not leak tasks from projects you have no access
      to, even though the Mind Map page itself has no role gate.
- [ ] Confirm switching the app language changes all Mind Map labels,
      filters, legend, and buttons to Spanish.

## Project/task visibility restrictions (Employees vs. Admin/PM/Viewer)

Only the plain Employee role is restricted to projects/tasks it's assigned
to or a member of. Administrators, Project Managers, and Viewers all keep
full, org-wide visibility (PMs need it for management tools like Workload
and cross-project Reports; Viewers exist specifically to see everything
read-only) — see `has_broad_project_visibility()` in `includes/permissions.php`.

- [ ] As an Employee who is a member of Project A but not Project B, confirm
      Project B never appears: in the Projects directory, in the Project
      field dropdown of the New/Edit Task modal (My Tasks, Team Activities,
      Task Board, Calendar, Mind Map, Quick Add), in Team Activities' project
      filter, in Calendar's/Timeline's project filter, in the Reports
      Center's project filter, and in a release's "Associated Projects" list.
- [ ] As that same Employee, confirm Project B's tasks never appear in Team
      Activities, the Calendar (list or drag-to-reschedule), Mind Map, or any
      Reports Center report/CSV export — even when no project filter is
      applied and even when searching/paging through "all" results.
- [ ] Confirm the Employee CAN still see and act on Project A's tasks
      everywhere above, including tasks in Project A that are assigned to a
      teammate (not just their own tasks) — membership grants visibility
      into the whole project, not just self-assigned tasks within it.
- [ ] As an Employee, create an ad-hoc/unplanned task with no project and
      assign it to a colleague. Confirm the Employee no longer sees that
      task anywhere (My Tasks doesn't show it since it isn't theirs; Team
      Activities, Calendar, and Mind Map's "No project" bucket must also
      hide it) — project-less tasks are only visible to their own
      assignee/requester for a restricted Employee, not to everyone.
- [ ] As an Employee, open Timeline and select a colleague who doesn't share
      any project with them — confirm that colleague's planned/actual/
      unplanned tracks come back empty (or only show items where the
      Employee is themselves the requester), rather than exposing the
      colleague's Project B tasks.
- [ ] As an Employee, run each report in the Reports Center and confirm
      results only include Project A (and project-less tasks where they're
      the assignee/requester) — try "Tasks by project", "Overdue tasks",
      "Estimated vs. actual", and "Requester-employee matrix" specifically,
      since these previously could have shown org-wide data.
- [ ] As an Employee, confirm the "Project progress" report only lists
      Project A, and Requester Analytics' figures only reflect Project A's
      (and their own) activity.
- [ ] As a Project Manager, confirm all of the above show the FULL org-wide
      dataset — Projects directory, Team Activities, Calendar, Timeline
      (any employee), Mind Map, every Reports Center report, and Requester
      Analytics — matching Admin's visibility (this is a widening from
      before: PMs used to be restricted like Employees in Team Activities,
      Project Detail, and Mind Map).
- [ ] As a Viewer, confirm the same full org-wide visibility as Admin/PM
      (unchanged from before).
- [ ] Directly call `api/activities.php?action=list` and
      `api/projects.php?action=list` as a logged-in Employee (e.g. via
      browser dev tools) and confirm the results are scoped the same way as
      the UI — these endpoints are reachable by URL even though no current
      page calls them directly for a full unfiltered list.

## Authorization boundaries

- [ ] As an Employee, confirm Admin pages (`admin/*.php`, `audit_log.php`)
      return an access-denied page.
- [ ] As an Employee not on a given project, confirm that project's detail
      page is denied and its tasks are hidden from Team Activities.
- [ ] Directly call an `api/*.php` write endpoint without a valid CSRF token
      (e.g. via browser dev tools) and confirm it's rejected.

## Color schemes & language

- [ ] From the topbar swatch dropdown, switch between Golden & White, Light
      Green, and Dark Blue — confirm the change applies instantly (no page
      reload) across sidebar, topbar, buttons, links, and form-check states.
- [ ] Reload the page and log out/in again — confirm the chosen color scheme
      is remembered (stored on your profile).
- [ ] As a guest (logged out), change the color scheme on the Log in page —
      confirm it applies to the login/register/forgot-password pages, and
      confirm it carries over once you register or log in (session-based
      until an account exists, then saved to the profile).
- [ ] From the topbar language dropdown, switch to Español — confirm the
      page reloads and text throughout the app (nav, buttons, forms, table
      headers) is translated. Switch back to English and confirm it reverts.
- [ ] Reload and log out/in again — confirm the chosen language persists.
- [ ] From Profile & Settings, confirm the "Appearance & language" section
      shows the currently active theme/language as selected and that
      choosing a different option there also applies and saves correctly
      (same mechanism as the topbar controls).
- [ ] Spot-check a few pages in Spanish (Dashboard, Task Board, My Tasks,
      Project Detail, a Reports Center filter form) and confirm labels read
      naturally; note that report table columns (built by `reports.js`) and
      the admin Users table (built by `admin_users.js`) remain in English —
      this is a known, disclosed gap, not a bug.
- [ ] Confirm times still show AM/PM in English regardless of language
      selected (native PHP date formatting is not locale-aware) — also a
      known, disclosed gap.

## General

- [ ] Resize the browser to a mobile width and confirm the sidebar collapses
      into the offcanvas menu and pages remain usable.
- [ ] Confirm no page displays a raw SQL error, PHP stack trace, or password
      hash under normal use.
