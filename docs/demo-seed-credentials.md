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
