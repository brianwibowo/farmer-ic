# Petugas task list contract

Status: **Task data generation is implemented; the `/petugas/tasks` page still uses typed fixture data.** Real rows now exist in `planting_cycle_tasks` after a subblok is generated from its farm's default SOP. Replace `PetugasTaskController::fixtureTasks()` with a scoped query when the task list backend is connected.

## Routes

```text
GET /petugas/tasks
```

Query parameter:

```text
status = all | overdue | today | upcoming | completed
```

Default: `all`.

The page remains inside the existing `petugas.access` middleware. The real
implementation must also require the Petugas user to have an assigned farm and
must only return tasks for that farm.

## Inertia props

```ts
type PetugasTaskPageProps = {
    tasks: PetugasTask[];
    filters: { status: 'all' | 'overdue' | 'today' | 'upcoming' | 'completed' };
    isFixture: boolean; // remove when the real query is connected
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
    activity_category: 'planting' | 'maintenance' | 'harvest';
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

The backend should derive `urgency`, not store it:

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

- fixture warning banner
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

- task filters UI
- task detail page
- “Mulai” action
- linked activity list
- “Ajukan selesai” action
- approval/rejection feedback
- pagination

Do not add working action buttons until the backend endpoints exist. Disabled
buttons are fine only if they have an explanatory label.

## Backend replacement plan

1. ~~Add stage alignment and `planting_cycle_tasks`.~~ Done.
2. ~~Generate tasks for a subblok from the farm default SOP.~~ Done.
3. Replace `fixtureTasks()` with a scoped `PlantingCycleTask` query.
4. Add `PetugasTaskController@show`.
5. Add start/submit endpoints and policies.
6. Add linked activities and submission props.
7. Remove the fixture banner and `isFixture` prop.

## Generated task fields

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
