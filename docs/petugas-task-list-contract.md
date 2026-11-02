# Petugas task list contract

Status: **UI contract stub**. The batch execution layer does not exist yet, so the
current `/petugas/tasks` page uses typed fixture data. Replace
`PetugasTaskController::fixtureTasks()` with real `planting_cycle_tasks` queries
when task generation is implemented.

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
    stage_code: string;
    activity_type: { id: string; code: string; name: string };
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
    batch: {
        id: string;
        batch_number: number;
        subblock_label: string | null;
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
- task name, HST, stage, activity type
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

1. Add stage alignment and `planting_cycle_tasks`.
2. Generate tasks when a SOP is applied to a batch.
3. Replace `fixtureTasks()` with a scoped query.
4. Add `PetugasTaskController@show`.
5. Add start/submit endpoints and policies.
6. Add linked activities and submission props.
7. Remove the fixture banner and `isFixture` prop.

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
