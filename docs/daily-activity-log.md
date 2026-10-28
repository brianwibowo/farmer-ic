# Daily activity log: block mapping

This slice stores a complete daily report with one or more block mapping
activities in PostgreSQL. The primary user flow is a server-backed Inertia page:
the controller renders the page and redirects form submissions while the service
owns persistence and transactions. A JSON compatibility route remains for the
existing backend tests and integrations, but the Vue page does not call it. The
other four agreed form types are not accepted yet. AI/RAG does not consume these
new reports yet; indexing and authoritative AI retrieval need a separate step.

## Apply the feature

From the project directory, use the private local profile:

```sh
php artisan --env=local migrate --database=pgsql
php artisan --env=local db:seed --class=BlockMappingActivityTypeSeeder
```

For a complete local dataset covering identity, farms, activities, monitoring,
finance, operations, and daily-log examples, use the full demo seeder and see
[demo-seed-credentials.md](demo-seed-credentials.md).

Only pending migrations are applied; do not reset the database. The additive
migration adds `daily_activity_logs` and `block_mapping_details`, links existing
`farm_activities` to their daily parent, and extends the existing PostgreSQL
type/detail guards. Older activities keep a null daily parent and an explicit
`not_recorded` obstacle state. A missing/mismatched detail is still rejected at
transaction completion. Rolling the feature back refuses when daily/mapping
records exist so history is not silently discarded.

The type seeder is repeatable and only inserts the `block_mapping` catalog
record if absent. It does not create logbook or user records.

## Inertia request flow

The page requires the normal authenticated and verified web session. The
controller authorizes the farm, passes the paginated logbook as Inertia props,
and derives `recorded_by` from the authenticated user before calling the service.
The Vue form submits with Inertia and receives validation errors or a flash
success message through the normal web response.

| Method | URL | Result |
| --- | --- | --- |
| GET | `/kebun/{farm}/daily-logs` | Inertia page with form, filters, and paginated reports |
| POST | `/kebun/{farm}/daily-logs` | Redirect back with a flash message |
| GET | `/kebun/{farm}/daily-logs/{dailyLog}` | Inertia detail page, or 404 if not in that farm |

The existing JSON compatibility routes remain under `/api/kebun/...` for the
current backend test harness. They require authentication and are not used by
the Inertia page.

For the compatibility request, replace `{farm}` with an existing farm UUID and
send `Accept: application/json` and `Content-Type: application/json`.

```json
{
  "work_date": "2026-10-27",
  "start_time": "08:00",
  "end_time": "16:00",
  "worker_count": 6,
  "notes": "Pemetaan awal untuk persiapan bedengan.",
  "activities": [
    {
      "type": "block_mapping",
      "block_mapping": {
        "block_name": "Blok 1",
        "length_m": 35,
        "width_m": 50,
        "row_spacing_cm": 40,
        "plant_spacing_cm": 20,
        "rows_per_bed": 2
      },
      "obstacle_status": "reported",
      "obstacles": "Batas blok mengikuti lereng lahan.",
      "proposed_solution": "Sesuaikan panjang bedeng dengan batas lahan.",
      "action_taken": "Batas blok ditandai di lapangan."
    }
  ]
}
```

- `work_date` is the actual work date, not submission time. Times are WIB
  (`Asia/Jakarta`), HH:mm, within the same day; end must follow start. Overnight
  shifts are not part of this first contract. Activity timestamps preserve the
  timezone offset in PostgreSQL.
- A report contains 1–50 activities. Worker count is once per report, 1–32767.
  More than one report per farm/day is allowed; no single-report-per-day rule
  has been assumed.
- Dimensions are positive meters, at most 3 decimal places. Spacing is positive
  centimeters, at most 2 decimal places; row and plant spacing must both be
  provided or both absent/null. Rows per bed is optional, a positive integer.
  A block name is a label for this measurement, not a newly created master
  block record. Dimensions never overwrite the farm's registered area.
- Choose `obstacle_status: "none"` with obstacles omitted/null, or `"reported"`
  with a nonempty explanation. Solutions, actions, and activity notes are
  optional. No numeric zero is invented for missing optional measurements.
- The authenticated user is the recorder. IDs, farm association, timestamps,
  order, and form version are server-owned.
  Nested keys are allowlisted. `farm_id` comes from the resolved route parent.
  One transaction saves the header and every activity/detail, or none of them.
- Decimal values are returned as fixed-scale strings to preserve precision.
  The response contains `data.id`, recorder name, activities, and form version.
  It does not serialize user secrets.
- List filters: `from_date`, `to_date` (inclusive, YYYY-MM-DD), `recorded_by`,
  `page`, `per_page` (1–100). Results sort by work date then creation time,
  newest first. Counts and pagination are scoped to the route's farm.
- Validation failures return 422. Unknown farm/report UUIDs return 404; a farm
  outside the caller's team or assignment returns 403. Active-recorder
  validation is a data consistency check, not proof that the caller is that user.

## Policy and authentication boundary

The authenticated caller is authorized against the route farm through the native
Gate/policy before the service runs. Farm/team scope and the petugas assignment
are resolved from persisted relationships; a caller cannot gain access by changing
the farm URL or by supplying a different browser ID. Any optional `recorded_by`
input is validated for compatibility and then overwritten with the authenticated
user's ID by the controller.

1. The current schema uses `farms.team_id` for tenant ownership and
   `users.assigned_farm_id` for the one-farm petugas assignment. `created_by`,
   `owner_id`, and browser IDs are not substitutes for either boundary.
2. The daily parent and individual `FarmActivity` map to the
   `activities` permission resource. Use per-model policies and a shared
   resource-policy base; do not invent a catch-all subject. Put explicit grants
   in `config/access.php`, with one `RolePermissions` reader. Superadmin is
   reserved for the provider's `Gate::before` bypass.
3. Add the remaining cross-team HTTP tests that assert 403, trusted recorder
   identity, and correctly scoped totals. Never rely on UI visibility for access
   control.

The Farm CRUD and daily-log routes now require authentication and invoke their
model policies. Farm listing and daily-log pagination both apply the model
visibility scope before returning records. Other application routes still require
a separate authorization rollout.

## Verification

`tests/Feature/Activity/DailyActivityLogTest.php` follows the repository's
PostgreSQL test harness. Set `FARMER_CRUD_TEST_DATABASE` to a separately migrated
database whose name starts `farmer_ic_crud_test_`, configure its connection, then
run the file with `php artisan test`. Tests roll back their own fixtures.
Never use `farmer_ic_dev` as that test database. The migration contract test
also verifies the updated DBML and a full migrate/rollback/remigrate cycle in
its separately guarded schema-test database.

Verified locally on 27 October 2026: 28 tests passed with 2,203 assertions,
including farm/model regressions and the full migration round trip. PHP type
analysis passed for the feature files. A labeled demo report was also created
through Laravel's HTTP kernel in `farmer_ic_dev` (POST 201), then retrieved and
listed (GET 200). Its IDs and endpoint are in the private local file
`storage/app/private/daily-log-smoke.json`; this file contains no passwords.
