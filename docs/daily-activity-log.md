# Daily activity log: block mapping

This first backend slice stores a complete daily report with one or more block
mapping activities in PostgreSQL. It exposes create, list, and detail endpoints.
There are no UI, Policy, role-matrix, draft/approval, edit, delete, or attachment
changes. The other four agreed form types are not accepted yet. AI/RAG does not
consume these new reports yet; indexing and authoritative AI retrieval need a
separate integration step.

## Apply the feature

From the project directory, use the private local profile:

```sh
php artisan --env=local migrate --database=pgsql
php artisan --env=local db:seed --class=BlockMappingActivityTypeSeeder
```

Only pending migrations are applied; do not reset the database. The additive
migration adds `daily_activity_logs` and `block_mapping_details`, links existing
`farm_activities` to their daily parent, and extends the existing PostgreSQL
type/detail guards. Older activities keep a null daily parent and an explicit
`not_recorded` obstacle state. A missing/mismatched detail is still rejected at
transaction completion. Rolling the feature back refuses when daily/mapping
records exist so history is not silently discarded.

The type seeder is repeatable and only inserts the `block_mapping` catalog
record if absent. It does not create logbook or user records.

## Local request contract

These JSON endpoints are covered by the existing `EnsureLocalFarmApi`
middleware. They return 404 outside `local`/`testing`, and 503 when the default
connection is not PostgreSQL. They deliberately follow the current local farm
API's guest-accessible pattern. They are not an operational petugas API.

| Method | URL | Result |
| --- | --- | --- |
| POST | `/api/kebun/{farm}/daily-logs` | 201 and the persisted report |
| GET | `/api/kebun/{farm}/daily-logs` | 200 and paginated reports for that farm |
| GET | `/api/kebun/{farm}/daily-logs/{dailyLog}` | 200, or 404 if not in that farm |

Replace `{farm}` with an existing farm UUID and `recorded_by` with an existing
active user UUID. Send `Accept: application/json` and `Content-Type:
application/json`.

```json
{
  "work_date": "2026-10-27",
  "start_time": "08:00",
  "end_time": "16:00",
  "worker_count": 6,
  "recorded_by": "<existing-active-user-uuid>",
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
- IDs, farm association, timestamps, order, and form version are server-owned.
  Nested keys are allowlisted. `farm_id` comes from the resolved route parent.
  One transaction saves the header and every activity/detail, or none of them.
- Decimal values are returned as fixed-scale strings to preserve precision.
  The response contains `data.id`, recorder name, activities, and form version.
  It does not serialize user secrets.
- List filters: `from_date`, `to_date` (inclusive, YYYY-MM-DD), `recorded_by`,
  `page`, `per_page` (1–100). Results sort by work date then creation time,
  newest first. Counts and pagination are scoped to the route's farm.
- Validation failures return 422. Unknown farm/report UUIDs and cross-farm
  report URLs return 404. Active-recorder validation is a data consistency
  check, not proof that the caller is that user.

## Policy and authentication discussion (not implemented)

`recorded_by` currently comes from a selected active user reference. It is not
authenticated identity. Any caller with access to this local API can select a
different active user and read/create records under any known farm URL. Farm
scoping prevents accidental cross-parent reads; it does not implement RBAC or
assignment checks. Do not enable these endpoints in a shared/production
environment without addressing the following:

1. Adapt the existing authenticated `User` model/provider to the UUID and
   `password_hash` schema. Then derive the recorder from the authenticated
   account and stop accepting it from request input. Do not use `FarmUser`
   (a plain Eloquent model) as a fake authentication guard.
2. Decide the persisted farm-to-team relationship and add real user-to-farm
   assignments. The current schema has team membership but neither a farm team
   foreign key nor `kebun_user`. `created_by`, `owner_id`, and browser IDs are
   not substitutes for assignment. `own` must deny until these relationships
   exist; petugas must have no more than one assigned farm.
3. Agree how the daily parent and individual `FarmActivity` map to the
   `activities` permission resource. Use per-model policies and a shared
   resource-policy base; do not invent a catch-all subject. Put explicit grants
   in `config/access.php`, with one `RolePermissions` reader. The fixed matrix
   includes `superadmin`, while the current DB role check still permits only
   owner/admin/petugas; that mismatch needs a deliberate follow-up migration.
4. Add backend authorization before service execution and authorized query
   scopes before counts/pagination. Owner stays read-only, only owner gets AI
   access, and no role inherits permissions or bypasses the matrix.
5. Add actual role/assignment and cross-team HTTP tests that assert 403, trusted
   recorder identity, and correctly scoped totals. Replace the temporary local
   gate with normal authenticated application routes only after those checks
   are present. Never rely on UI visibility for access control.

No existing farm or AI route has been secured by this work. The new daily-log
routes remain guest-accessible only in local/testing; existing authorization
gaps elsewhere remain unchanged, as requested.

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
