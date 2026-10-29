# Demo seed data

Run the complete local demo seed after migrations:

```sh
php artisan --env=local db:seed
```

The seeder is repeatable. It creates or updates the four role accounts below,
one non-personal team, one assigned demo farm, crop master data, an active
planting cycle, activity and daily-log records, monitoring data, finance data,
operational targets, and a notification. Existing records outside the named
demo records are left untouched.

| Role | Email | Password |
| --- | --- | --- |
| SuperAdmin | `superadmin@farmer-ic.test` | `SuperAdmin123!` |
| Owner | `owner@farmer-ic.test` | `Owner123!` |
| Admin | `admin@farmer-ic.test` | `Admin123!` |
| Petugas | `petugas@farmer-ic.test` | `Petugas123!` |

The Petugas account is assigned to `Kebun Cileuksa Demo`. Use that account to
open the daily-log Inertia page:

```text
/kebun/{farm_uuid}/daily-logs
```

The farm UUID can be read from the authenticated farm listing or PostgreSQL.
The seed creates the `block_mapping` activity type before creating the sample
daily log.

These credentials are development fixtures only. Do not use them in a shared,
staging, or production environment, and do not copy their passwords into real
accounts.

## Seeder sections

- `DemoIdentitySeeder`: roles, users, team membership, and current team.
- `DemoFarmSeeder`: team-bound farm, owner/creator, petugas assignment,
  commodity, variety, and active planting cycle.
- `DemoActivitySeeder`: activity types, block-mapping daily log, planting,
  fertilization, irrigation, pesticide, incident, growth observation, harvest,
  and activity-photo records with their matching detail records.
- `DemoMonitoringSeeder`: weather, farm risk, and AI insight summary.
- `DemoFinanceSeeder`: financial categories, transactions, projection, and
  budget.
- `DemoOperationsSeeder`: operational targets, a petugas notification, and an
  audit-log entry.

## Finance dummy data

`DemoFinanceSeeder` now provides 10 shared financial categories, 24 transactions
across August–October 2026, 6 category budgets for October–December, and 3
projections. Transactions cover seed, fertilizer, labor, pest control, irrigation,
transport, equipment rental, maintenance, harvest sales, and seedling sales.
All operational records belong to `Kebun Cileuksa Demo` in `farm-ic-demo`.
October expenses link to its October planting cycle; historical transactions
and harvest sales remain farm-level records rather than claiming that cycle's
future harvest. Projection periods are August, September, and October–January.

On a fresh dataset, totals for the 24 transactions are:

| Item | Amount |
| --- | ---: |
| Income | Rp51.425.000,00 |
| Expense | Rp29.075.000,00 |
| Net | Rp22.350.000,00 |

To add only finance fixtures after the demo identity and farm are available:

```powershell
php artisan --env=local db:seed --database=pgsql --class=DemoFinanceSeeder
```

For a fresh installation, apply migrations and seed the complete demo in order:

```powershell
php artisan --env=local migrate --database=pgsql
php artisan --env=local db:seed --database=pgsql
```

The finance seeder requires `local` or `testing`, resolves the persisted demo
admin and its team/farm, and authorizes the financial model policies before
writing. Missing prerequisites produce a message naming the required seeder;
finance-only seeding does not reset demo passwords or create a new farm.
The complete finance batch is transactional. It does not truncate tables or
overwrite existing matching rows. Reruns reuse transaction keys (farm, category,
date, description), budget keys (farm, category, period), and projection keys
(farm, cycle, period), preserving edits to amounts and unrelated transactions.
Changing one of those identifying fields can cause a new fixture to be inserted.

Data can be checked through the authenticated `/finance` and
`/finance/transactions` Inertia pages using the active team. These pages read
database records directly, including the seeded categories, budgets, and
projections. The AI finance snapshot retains its separately labeled knowledge
demo data and is not connected to these database transactions.
