# Petugas task list contract

Status: **Database-backed tasks, task-linked reports, and initial planting realization are implemented.** Tasks come from `planting_cycle_tasks`, scoped to the authenticated user's assigned farm. Category, subcategory, farm and subblok are derived from the persisted task, never selected again or accepted as browser authority.

## Shared daily reports

`daily_activity_logs` is a shared header for **one farm + work date**, not one
header per activity or officer. The database enforces
`daily_logs_farm_date_unique (farm_id, work_date)`. `recorded_by` is the first
creator; `farm_activities.performed_by` remains the actual reporting officer.
Daily hours, total workers and general notes belong only to the header. Workers
are not summed from activity reports. Activity `notes` and `daily_notes` are
separate inputs. Existing headers are never overwritten by append requests.

The Petugas Aktivitas page is now a date-selectable daily report with an empty
state, daily summary, all visible activities, each officer, details and private
photos. Tambah aktivitas leads to SOP tasks or initial planting, retaining the
selected date. Recording forms check the chosen day's header: reuse its summary
when present, or request daily information for the first activity. Date lookup
has loading/error/retry states and cancels stale requests.

New endpoints (authenticated and policy-authorized):

```text
GET   /petugas/aktivitas?work_date=YYYY-MM-DD
GET   /kebun/{farm}/daily-logs/context?work_date=YYYY-MM-DD
PATCH /kebun/{farm}/daily-logs/{dailyLog}
GET   /kebun/{farm}/daily-logs/activities/{activity}/photos/{photo}
```

Assigned Petugas and authorized tenant Admin can edit the shared header from a
separate Ubah action. Owners remain read-only. Farm, date, original creator and
activities cannot be reassigned through header edits. Task, planting and legacy
block-mapping writes all acquire the same exclusive persisted farm lock before
resolving a header and allocating `max(position) + 1`. A farm/date unique index
and existing header/position unique constraint also guard the database itself.
Concurrent first writes keep the first successful creator's values; the second
appends without overwriting them. Report/header creation, details and photos
remain in the same transaction. Missing required information for a new header
returns validation errors without partially saving the report.

Activity timestamps inherit the daily header's start on their work date; these
are not separately measured per-activity working hours. Initial planting stage
timestamps likewise use the daily hours available when planting was recorded.
Later header edits do not rewrite activity/stage timestamps or change dates.

Demo activity seeding attaches its sample reports to shared headers too. The
additive uniqueness migration refuses duplicate historical farm/date headers
instead of silently merging or deleting them. The user separately authorized
clearing old local demo reports before this implementation; the migration does
not repeat that cleanup and does not reset any database.

SOP scheduling-date restrictions, task completion/review, accumulated harvested
area enforcement and closing cycles are unchanged and not part of this change.

## Initial planting and task generation

### PHP upload configuration

Both planting and SOP-task reports accept up to five photos of 10 MB each.
The serving PHP process must therefore allow `upload_max_filesize = 10M`
and `post_max_size = 64M` (including multipart overhead). Set these in the
serving PHP's `php.ini`, not Laravel `.env`, and restart the web server.
Use `php --ini` / `php -i` to inspect CLI configuration when using
`php artisan serve`; other web SAPIs may load a different configuration.
PHP's default 2 MB/file and 8 MB/request limits cannot support this form.
Changing these limits with `ini_set()` inside a controller is too late:
PHP processes uploads before Laravel handles the request. A PHP upload
failure leaves the planting report unsaved; choose the photo again after
correcting the limit. Oversized total requests may be rejected before
Laravel's per-photo validation runs.

```text
GET  /petugas/penanaman
GET  /petugas/subbloks/{cycle}/penanaman
POST /petugas/subbloks/{cycle}/penanaman
POST /petugas/subbloks/{cycle}/tasks/sync
```

Open Aktivitas → Penanaman aktual → select a subblok. A single initial report
records the actual execution date, variety, area, seeds, worker/time information,
and private photos. It reuses or creates the shared daily log, then creates a `planting` farm activity, its
`planting_details` and photos, and updates the cycle's `planted_at`, `variety_id`,
`planted_area_ha` and `status = active` in one transaction. The planting stage
is completed using the shared daily hours on the reported date; other preparation stages are not
fabricated or closed automatically. Duplicate submissions cannot overwrite an
existing actual date or area. Partial/staggered planting and correction flows
are not implemented.

The service then synchronizes tasks in that same transaction. Actual planting
is retained if SOP readiness is missing; feedback explains why tasks are deferred.
A technical failure rolls back both database changes and new files. A variety
cannot contradict an already-bound SOP commodity. With no snapshot, a default
SOP commodity mismatch defers generation until its configuration is corrected.

Task generation now runs:
- when a batch is created through the authenticated block action (`prepareBatch`);
- when initial planting is saved;
- when Siapkan tugas is requested for an existing assigned subblok;
- from the existing demo seeder with explicit non-HTTP authorization.

Both dates remain nullable. No tasks or version binding are created without
either `planted_at` or `planned_planting_at`. For new snapshots the default SOP
must be active, in the same tenant, and have a published version. Existing
snapshots retain their bound published/retired version even if the default
changes. Only pending tasks are rescheduled; work already started/submitted or
closed is left untouched. A missing stage is detected before any task is inserted.

`PlantingCycleTaskPolicy::synchronize` delegates to the cycle update and task
listing permissions; it does not grant arbitrary task CRUD. The initial batch
creation action derives tasks under its existing trusted-parent creation check.
`FarmActivityPolicy::recordPlanting` composes the cycle-update and report-creation
grants. Petugas gets read-only SOP template/version/step grants within its tenant
for scoped generation; the admin SOP portal and mutations stay forbidden.

## Recording activity from a task

```text
GET  /petugas/tasks/{task}
GET  /petugas/tasks/{task}/aktivitas/buat
POST /petugas/tasks/{task}/aktivitas
GET  /petugas/aktivitas/{activity}/foto/{photo}
```

The legacy `/petugas/aktivitas/buat` redirects to the task list. It no longer
offers a free category selector or SOP selector. Initial planting uses the
subblok-based workflow above. Preparation work and incidental activities outside
a generated task are not submitted through this task-report form.

Recording is available only for pending/in-progress tasks with an actual
planting date. Multiple reports per task are allowed. Saving does not start,
submit, complete or approve the task. Task submission/review is still pending.

Each report reuses or creates its shared daily log and persists one farm activity, typed details where
available, validated supplementary `task_details`, and 1–5 private photos.
The task link is protected by a composite FK to the same planting cycle.
Weeding uses its own reference type and JSON detail, without pretending to be
another operation. Existing typed-detail integrity checks remain enabled.
Photo reads authorize both activity and photo; failed saves remove new files.

`FarmActivityPolicy::recordTask` is a composite policy ability using existing
view/create grants for the activity, daily log, photo and applicable detail.
No new wildcard or task-update grant is introduced. Petugas gains read-only
`activity_types.view` and `crop_varieties.view` for reference metadata.

Apply the additive `2026_11_02_000011_link_task_activity_reports` migration.
It refuses rollback while task reports exist. Farm force-delete handles the
new link and deletes activities before tasks; private file cleanup remains
outside force-delete, consistent with its existing storage boundary.

## Routes

```text
GET /petugas/tasks
```

Query parameter:

```text
status = all | overdue | today | upcoming | completed
```

The controller validates the value and passes it back as `filters.status`. The
backend applies the urgency filter while summary counts remain scoped to the
full assigned-farm task set.

Default: `all`.

The page remains inside the existing `petugas.access` middleware. The
implementation only returns tasks for the assigned farm. A user without an
assignment receives an empty page with no operational data or actionable links.

## Inertia props

```ts
type PetugasTaskPageProps = {
    tasks: PetugasTask[];
    filters: { status: 'all' | 'overdue' | 'today' | 'upcoming' | 'completed' };
    summary: Record<'overdue' | 'today' | 'upcoming' | 'completed', number>;
    farm: { id: string; name: string } | null;
    defaultSopMissing: boolean;
};
```

Type source:

```text
resources/js/types/petugas-task.ts
```

## Default SOP for a farm

The Petugas task list and planting form must not expose an SOP selector. The
backend resolves the SOP from `farms.default_sop_template_id`. SOP templates are
tenant-owned and tied to a commodity, not a variety. If the farm has no default
SOP, show a clear “SOP default belum diatur” state instead of letting Petugas
choose one.

## Task shape

```ts
type PetugasTask = {
    id: string;
    name: string;
    instructions: string | null;
    status:
        | 'pending'
        | 'in_progress'
        | 'submitted'
        | 'completed'
        | 'skipped'
        | 'cancelled';
    urgency: 'overdue' | 'today' | 'upcoming' | 'completed';
    activity_category: 'maintenance' | 'harvest';
    activity_subcategory:
        | 'irrigation'
        | 'weeding'
        | 'follow_up_fertilization'
        | 'growth_check'
        | 'pest_control'
        | null;
    offset_days: number;
    scheduled_date: string | null;
    due_date: string | null;
    is_required: boolean;
    linked_activities_count: number;
    latest_submission: {
        status: 'submitted' | 'approved' | 'rejected';
        submitted_at: string | null;
        review_note: string | null;
    } | null;
    can: {
        start: boolean;
        submit: boolean;
        record: boolean;
    };
    subblok: {
        id: string;
        number: number;
        label: string | null;
        planned_planting_at: string | null;
        planted_at: string | null;
        hst: number | null;
        farm: { id: string; name: string };
        block: { id: string; name: string };
        variety: { id: string; name: string };
    };
};
```

## Urgency rules

The backend derives `urgency`; it is not stored. The effective date is
`rescheduled_date ?? scheduled_date`, so an Admin reschedule is visible to
Petugas without changing the original SOP date:

```text
status completed / skipped / cancelled → completed
no scheduled_date → upcoming
due_date < today → overdue
scheduled_date <= today <= due_date → today
otherwise → upcoming
```

Tasks should be ordered by:

```text
urgency group
then scheduled_date
then offset_days
then name
```

## UI scope

The page currently supports:

- database-backed rows and scoped summary counts (not narrowed by status filter)
- counts for overdue / today / upcoming
- grouped task cards
- task name, HST, category, subcategory
- farm, block, subblock, variety
- scheduled date
- linked activity count
- required indicator
- submission status
- empty state

Not implemented yet:

- “Mulai” action
- “Ajukan selesai” action
- approval/rejection feedback
- pagination

Do not add working action buttons until the backend endpoints exist. Disabled
buttons are fine only if they have an explanatory label.

## Backend replacement plan

1. ~~Add stage alignment and `planting_cycle_tasks`.~~ Done.
2. ~~Generate tasks for a subblok from the farm default SOP.~~ Done.
3. ~~Replace `fixtureTasks()` with a scoped `PlantingCycleTask` query.~~ Done.
4. Add `PetugasTaskController@show`.
5. Add start/submit endpoints and policies.
6. Add linked activities and submission props.
7. Remove the fixture banner and `isFixture` prop from the friend-owned UI.
8. Add pagination once the task volume requires it.

Steps 3, 4, 6 (linked reports only), and 7 are now complete. Start/submit
endpoints, submission/review records and pagination remain unimplemented.

## Verification

```text
php artisan test tests/Feature/Activity/PetugasTaskActivityTest.php tests/Feature/Petugas tests/Feature/Authorization
npm run types:check
npm run build
```

## Generated task fields

SOP authoring only includes follow-up **Perawatan** and **Panen** steps. Planting is the HST anchor and is recorded through the planting activity, not as an SOP step or a generated task. The generated task list starts at maintenance work.

The real query should map `planting_cycle_tasks`:

```text
id
name
instructions
status
activity_category
activity_subcategory
offset_days
scheduled_date
due_date
is_required
completed_at
planting_cycle_id  → subblok.id
```

Join the subblok, block, farm, and variety for the card metadata. `hst` and
urgency should be derived, not stored.

## File ownership

Friend working on the task list UI:

```text
resources/js/pages/petugas/Tasks.vue
resources/js/components/petugas/*
resources/js/types/petugas-task.ts
```

Backend / execution layer:

```text
database/migrations/*
app/Models/PlantingCycleTask.php
app/Services/*
app/Http/Controllers/Petugas/PetugasTaskController.php
app/Policies/*
config/access.php
```

Shared files needing coordination:

```text
routes/web.php
resources/js/layouts/PetugasLayout.vue
resources/js/components/farmer/FarmerBottomNav.vue
```

## Branch workflow

```bash
git fetch origin
git switch -c feat/petugas-task-list origin/feat/admin-sop
```

Do not branch from `main`; the tenant-owned SOP changes are on `feat/admin-sop`.
