# Petugas flow handoff

Status: **Ownership split confirmed.** The Petugas flow — activity creation,
planting logging, task start/submit, and Petugas task actions — is owned by the
friend. The Admin task-management and read-only Petugas task-list backend are
already implemented on `feat/admin-sop`.

## Current backend state

### Routes already available

```text
GET  /petugas/tasks
GET  /petugas/aktivitas/buat
GET  /admin/tasks
GET  /admin/tasks/{task}
POST /admin/tasks/{task}/skip
POST /admin/tasks/{task}/cancel
POST /admin/tasks/{task}/reschedule
```

`GET /petugas/tasks` is read-only. There is no `store`, `start`, `submit`,
`approve`, or `reject` endpoint yet.

### Activity taxonomy

`activity_types` is now self-referencing (`parent_id`) with `detail_key`,
`is_selectable`, `sort_order`, and `is_active`. `farm_activities.activity_type_id`
points to the most specific selectable row. The current activity API still only
accepts `block_mapping`; detail tables and request validation for the other
categories remain part of the Petugas flow.

### Task data

`planting_cycle_tasks` is the generated follow-up task snapshot:

```text
pending -> in_progress -> submitted -> completed
                         \-> in_progress (rejected)
skipped / cancelled are Admin-only terminal states
```

Relevant existing services:

```text
app/Services/PlantingCycleTaskService.php
  generateForSubblok(PlantingCycle $subblok)

app/Services/PlantingCycleTaskManagementService.php
  skip / cancel / reschedule
```

`generateForSubblok()` is idempotent. It skips planting and creates only
`maintenance` / `harvest` tasks. The HST anchor is:

```text
planted_at ?? planned_planting_at
```

Call it after the real planting save has set `planted_at` on the subblok.

### Petugas task response

`PetugasTaskService` returns the UI shape defined in
`docs/petugas-task-list-contract.md`. Some fields are intentionally
placeholders until the Petugas submission layer exists:

```text
linked_activities_count = 0
latest_submission = null
can.start  = status === 'pending'
can.submit = status === 'in_progress'
```

`isFixture` is still present and always `false` for compatibility with the
existing UI. It can be removed when the UI no longer reads it.

## Remaining work owned by the friend

1. **Activity creation / planting logging**
   - Create the activity and detail rows.
   - Set the subblok’s `planted_at`, variety, planted area, and status.
   - Call `PlantingCycleTaskService::generateForSubblok()` after the anchor is set.

2. **Task-to-activity linkage**
   - `farm_activities` currently has no `planting_cycle_task_id`.
   - Choose either a nullable task FK on `farm_activities` or a dedicated
     submission table for attempts, evidence, and review notes.
   - Replace the placeholder `linked_activities_count` and `latest_submission`.

3. **Petugas task actions**
   - Start: `pending -> in_progress`.
   - Submit: `in_progress -> submitted`, with linked activity/evidence.
   - Show rejection feedback and allow resubmission.

4. **Admin review**
   - Approve: `submitted -> completed`.
   - Reject: `submitted -> in_progress` with a review note.
   - Keep Admin skip/cancel/reschedule behavior unchanged.

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

## Suggested implementation slice

1. Add the activity/task linkage migration.
2. Implement planting save and call `generateForSubblok()`.
3. Add start/submit endpoints and update the task status.
4. Add approval/rejection endpoints and Admin review UI.
5. Fill the real `linked_activities_count` and `latest_submission` values.
6. Remove `isFixture` from the UI and controller once the UI drops it.

## Branch

All work is on `feat/admin-sop`. The branch includes the Admin task-management
slice, the real read-only Petugas task list, block/subblok filtering, and the
planting-category cleanup.

Before starting, branch from `origin/feat/admin-sop` after this handoff is
pushed. Do not branch from `main`; the tenant-owned SOP and task work is on
`feat/admin-sop`.
