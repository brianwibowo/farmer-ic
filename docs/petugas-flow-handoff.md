# Petugas flow handoff

> **Historical handoff:** Activity creation, planting realization, shared daily
> reports, and direct task completion have since been implemented. For current
> status and remaining work, read `docs/petugas-activity-handoff.md` and
> `docs/petugas-task-list-contract.md`. Task approval/review is no longer in
> scope. Admin skip/reschedule remains available; cancel was merged into skip.

Status: **Ownership split confirmed.** The Petugas flow — activity creation,
planting logging, and task completion — is owned by the friend. The Admin
task-management and Petugas task-list backend are implemented on
`feat/admin-sop`.

## Current backend state

### Routes already available

```text
GET  /petugas/tasks
GET  /petugas/tasks/{task}
POST /petugas/tasks/{task}/selesai
GET  /admin/tasks
GET  /admin/tasks/{task}
POST /admin/tasks/{task}/skip
POST /admin/tasks/{task}/reschedule
```

Task creation remains SOP-generated. There is no `start`, `submit`, `approve`,
or `reject` endpoint. Completion is a direct Petugas action after at least one
linked report exists.

### Activity taxonomy

`activity_types` is now self-referencing (`parent_id`) with `detail_key`,
`is_selectable`, `sort_order`, and `is_active`. `farm_activities.activity_type_id`
points to the most specific selectable row. Every selectable category now has
typed detail storage; the public activity API still creates `block_mapping`
only, while task and planting flows write their own typed details.

### Task data

`planting_cycle_tasks` is the generated follow-up task snapshot:

```text
pending -> in_progress -> completed
                    \-> skipped
```

`skipped` is the single Admin-only “not done” terminal state with a required
free-text reason. There is no approval/review state.

Relevant existing services:

```text
app/Services/PlantingCycleTaskService.php
  generateForSubblok(PlantingCycle $subblok, User $actor)

app/Services/PlantingCycleTaskManagementService.php
  skip / complete / reschedule
```

`generateForSubblok()` is idempotent. It skips planting and creates only
`maintenance` / `harvest` tasks. The HST anchor is:

```text
planted_at
```

Call it after the real planting save has set `planted_at` on the subblok.

### Petugas task response

`PetugasTaskReadService` returns the UI shape defined in
`docs/petugas-task-list-contract.md`:

```text
linked_activities_count = actual linked reports
completed_at / completed_by / completion_note
can.record   = open task with actual planting date
can.complete = open task, planted, and at least one linked report
```

## Remaining work owned by the friend

1. **Activity creation / planting logging** — implemented.
2. **Task-to-activity linkage** — implemented through
   `farm_activities.planting_cycle_task_id` and `task_details`.
3. **Petugas task completion** — implemented as a direct action. The first
   report moves `pending` to `in_progress`; completion requires at least one
   linked report.
4. **Admin task management** — skip and reschedule remain. Cancel is merged into
   skip. There is no approve/reject workflow.

## Files owned by the friend

```text
resources/js/pages/petugas/Tasks.vue
resources/js/pages/petugas/ActivityCreate.vue
resources/js/components/petugas/*
resources/js/types/petugas-task.ts
```

Backend files now also part of the Petugas flow:

```text
app/Http/Controllers/Petugas/PetugasTaskController.php
app/Http/Controllers/Petugas/PetugasActivityController.php
app/Services/PetugasTaskService.php
```

Shared files to coordinate before editing:

```text
routes/web.php
resources/js/layouts/PetugasLayout.vue
resources/js/components/farmer/FarmerBottomNav.vue
```

## Implemented slice

1. Activity/task linkage migration is in place.
2. Planting save calls `generateForSubblok()`.
3. Direct completion endpoint updates the task after a linked report exists.
4. Approval/rejection endpoints and Admin review UI are not in scope.
5. `linked_activities_count` and completion fields are real.
6. `isFixture` has been removed.

## Branch

All work is on `feat/admin-sop`. The branch includes the Admin task-management
slice, the real read-only Petugas task list, block/subblok filtering, and the
planting-category cleanup.

Before starting, branch from `origin/feat/admin-sop` after this handoff is
pushed. Do not branch from `main`; the tenant-owned SOP and task work is on
`feat/admin-sop`.
