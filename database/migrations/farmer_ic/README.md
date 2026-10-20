# Farmer IC: PostgreSQL schema baseline

This is the migration-only implementation of the supplied `farmer_ic_revised.dbml`.
The unchanged source is included as `schema.dbml` for reproducible schema tests.

The selected migration strategy is **a new, empty PostgreSQL database**. The
existing SQLite database, data, and historical migrations remain unchanged.
These migrations are intentionally isolated in this directory, because the
historical migrations create integer identifiers, team tables, and incompatible
AI tables. Laravel does not recursively discover this directory from the default
migration path.

## Apply the baseline

Configure the existing `pgsql` connection for a **separate, empty** database.
For example, the following PowerShell environment variables apply only to that
terminal session; they do not edit `.env`:

```powershell
$env:DB_CONNECTION = 'pgsql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '5432'
$env:DB_DATABASE = 'farmer_ic_new'
$env:DB_USERNAME = 'your_postgresql_user'
$env:DB_PASSWORD = 'your_postgresql_password'
$env:DB_URL = ''

php artisan migrate:status --database=pgsql --path=database/migrations/farmer_ic
php artisan migrate --database=pgsql --path=database/migrations/farmer_ic
```

The connection name is `pgsql`, not `postgres` or `postgresql`. A valid default
connection is also needed while Laravel boots its service providers, before the
Artisan command applies `--database`. Set `DB_CONNECTION=pgsql` for this terminal
session as shown above. PostgreSQL's configured port and credentials must be
used; the legacy MySQL port `3306` and its credentials are not interchangeable.
If configuration was cached, run `php artisan config:clear` after setting these
environment variables and before running the migration.

Do not run the default `php artisan migrate` against this database: it would run
the legacy migrations, including the team schema. Do not use `migrate:fresh` on
an existing database. The migrator also creates its normal `migrations` tracking
table; that is infrastructure, separate from the 31 ERD tables.

Rollback, only on the intended development database:

```powershell
php artisan migrate:rollback --database=pgsql --path=database/migrations/farmer_ic
```

Each migration runs inside PostgreSQL's DDL transaction support. A failed
migration is rolled back; rerunning `migrate` applies only pending migrations.
Rollback runs the nine migrations in reverse dependency order.

## Migration inventory

| Sequence | Tables / responsibility |
| --- | --- |
| 000001 | `roles`, `users` |
| 000002 | `farms`, `commodities`, `crop_varieties`, `planting_cycles` |
| 000003 | `activity_types`, `farm_activities`, `activity_photos`, and six activity detail tables |
| 000004 | `plant_observations`, `weather_records`, `farm_risks`, `ai_insights` |
| 000005 | `financial_categories`, `financial_transactions`, `financial_projections`, `financial_budgets` |
| 000006 | `operational_targets` |
| 000007 | `ai_conversations`, `ai_messages`, `ai_attachments`, `ai_knowledge_documents`, `ai_usage_logs` |
| 000008 | `notifications`, `audit_logs` |
| 000009 | Composite foreign keys and deferred cross-table validation |

All 31 tables have the exact DBML column names, types, precision, nullability,
and declared defaults. Identifiers are UUIDs; each activity detail uses its
`activity_id` as both primary and foreign key. Timestamps use timezone-aware
PostgreSQL types, and audit before/after values use JSONB. No dashboard, report,
team, plot, or growth-check detail tables are added.

## Constraints and indexes

- The 45 DBML foreign keys are implemented directly. Historical references use
  `RESTRICT`, dependent activity photos/details and message attachments use
  `CASCADE`, and optional references that may be detached use `SET NULL`.
- Eight additional composite FKs validate farm/cycle consistency,
  observation/activity/cycle consistency, risk/insight observation consistency,
  and financial category/transaction-type consistency. Four candidate unique
  keys are necessary for PostgreSQL to reference these pairs; they do not add
  attributes or replace the DBML's primary keys.
- A nullable composite association uses PostgreSQL `MATCH SIMPLE`. In particular,
  the DBML still permits a transaction without `farm_id` or `planting_cycle_id`.
- Positive farm/planted areas and actual transaction amounts, nonnegative
  quantities/forecasts/budgets/token usage/costs, percentage ranges, coordinate
  ranges, ordered periods, and estimated harvest dates are checked in the DB.
- Enum checks use only sets explicitly defined in the DBML: roles, activity
  type codes, planting-cycle status, income/expense, and AI message sender.
  Open-ended or unspecified values (for example `target_unit` and risk status)
  are not restricted using guessed enums.
- Foreign keys have indexes, reusing primary/unique/composite prefixes where
  possible. Date-based composite indexes support farm activity, observations,
  weather, finance, targets, conversations, usage logs, and notification queries.
- Deferred constraint triggers enforce exactly one matching detail per activity.
  `growth_check` uses `plant_observations`, whose `activity_id` remains optional
  and unique. Standalone observations are supported. Linked actual harvest
  activities cannot precede their cycle's planting date, including after edits.
- Deferred area checks lock the farm row and validate that the sum of `active`
  planting-cycle areas does not exceed farm capacity, including after changes
  to the farm's area. Planned/cancelled/harvested cycles are not counted.

Create an activity and its corresponding detail **in the same database
transaction**. The check runs at transaction completion. Deleting a required
detail without replacing it or deleting its activity is rejected. PostgreSQL
`SET CONSTRAINTS ALL IMMEDIATE` can force deferred checks inside a transaction.

## Compatibility boundaries

This baseline does not move existing records, assign roles to existing accounts,
insert reference/seed data, modify models/controllers/API/frontend, or switch
the application's configured database. UUIDs and required timestamps have no
extra database defaults beyond the DBML and must be supplied by the application.

Before switching the running application to the new database, a separate task
must adapt UUID model keys, `password_hash` authentication, required `role_id`,
the team-dependent routes/services, and the changed AI column names. Existing
models currently expect `password`, integer IDs, and team associations.
The existing SQLite-backed runtime therefore remains the compatibility path.

Role names are validated in the database; the `owner` read-only, `admin` full,
and `petugas` operational permissions still require application authorization.
They are not implemented by a role-name CHECK constraint.

Laravel's legacy session, password-reset, cache, and job tables are not included
in this 31-table business schema. Runtime infrastructure must be configured or
migrated separately when the application is moved to PostgreSQL.

## Verification

PHP lint and Pint can run without a database:

```powershell
Get-ChildItem database/migrations/farmer_ic -Filter '*.php' | ForEach-Object { php -l $_.FullName }
php vendor/bin/pint --test database/migrations/farmer_ic tests/Feature/Database/FarmerIcMigrationsTest.php
```

The integration test requires a dedicated local PostgreSQL database owned by
`farmer_ic_schema_test`, with a database name starting `farmer_ic_schema_test_`.
Use an isolated test server/account; the test performs rollback and removes
these 31 tables. It deliberately refuses other database names or users and is
skipped unless explicitly enabled:

For a separate local test cluster, PostgreSQL's tools can provision it without
using the normal PostgreSQL server or the application's database:

```powershell
initdb -D storage/app/farmer-ic-pg-schema-test -U farmer_ic_schema_test --auth=trust --encoding=UTF8 --locale=C
pg_ctl -D storage/app/farmer-ic-pg-schema-test -l storage/app/farmer-ic-pg-schema-test/server.log -o "-h 127.0.0.1 -p 55439 -c timezone=UTC" -w start
createdb -h 127.0.0.1 -p 55439 -U farmer_ic_schema_test farmer_ic_schema_test_local
```

Then enable the test using the database name chosen above:

```powershell
$env:FARMER_IC_TEST_DATABASE = 'farmer_ic_schema_test_local'
$env:FARMER_IC_TEST_PORT = '55439'
php artisan test tests/Feature/Database/FarmerIcMigrationsTest.php
pg_ctl -D storage/app/farmer-ic-pg-schema-test -m fast -w stop
```

The test compares all tables/columns/PKs/types/nullability/declared defaults and
foreign-key targets with `schema.dbml`; it exercises positive and negative
inputs, matching activity details, compound relations, cascades, restrictions,
SET NULL, and a migrate/rollback/remigrate/rollback round trip. The active
application database is not selected by this test.

Verified during implementation on PostgreSQL 18.1: nine migrations applied,
all schema/behavior tests passed (one integration test, 1,516 assertions), and
rollback/remigrate/rollback left only the empty migration tracking table.
PHP lint and Pint passed. The original SQLite file's SHA-256 hash and migration
status were unchanged. PostgreSQL test data remains in ignored `storage/app/`;
the temporary test server is stopped after verification.
