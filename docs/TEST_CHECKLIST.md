# Manual test checklist

Mapped to the acceptance criteria. Run through this after installation
(`docs/INSTALL.md`) using the seeded demo accounts.

## Authentication & accounts

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
