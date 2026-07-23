# Manual test checklist

Mapped to the acceptance criteria. Run through this after installation
(`docs/INSTALL.md`) using the seeded demo accounts.

## Authentication & accounts

- [ ] Register a new account with full name, email, password, secret
      question/answer — no email verification step, can log in immediately.
- [ ] Log in with a valid demo account (e.g. `carla.diaz@activityflow.test` /
      `Password123!`).
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

## Projects & collaboration

- [ ] Create a project as a Project Manager, add two members with different
      project roles.
- [ ] Add both planned and unplanned tasks to the project; open Project
      Details and confirm progress, unplanned effort, and "requesters
      generating work for this project" all update.
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

## Authorization boundaries

- [ ] As an Employee, confirm Admin pages (`admin/*.php`, `audit_log.php`)
      return an access-denied page.
- [ ] As an Employee not on a given project, confirm that project's detail
      page is denied and its tasks are hidden from Team Activities.
- [ ] Directly call an `api/*.php` write endpoint without a valid CSRF token
      (e.g. via browser dev tools) and confirm it's rejected.

## General

- [ ] Resize the browser to a mobile width and confirm the sidebar collapses
      into the offcanvas menu and pages remain usable.
- [ ] Confirm no page displays a raw SQL error, PHP stack trace, or password
      hash under normal use.
