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

## Optional Owner presentation portfolio

After the normal demo identity and Cileuksa prerequisites exist, add the varied
Owner scenario after applying any pending additive migrations:

```sh
php artisan migrate
php artisan db:seed --class=DemoOwnerPortfolioSeeder
```

This is a separate optional scenario, not part of `DatabaseSeeder`. Run it only
against the intended local demo database; the seeder itself accepts only
`local`/`testing` and requires the persisted `farm-ic-demo` tenant and its demo
identities. It does not call `DemoIdentitySeeder`, change passwords/profiles,
switch active tenants, reassign Petugas, truncate tables, or reset demo data.
It uses the existing persisted demo Superadmin with native policy checks for
fixture writes; Owner remains read-only. All new records belong to that demo
tenant. The complete portfolio is inserted in one transaction.

| New site | Land (ha) | Land tenure | Commodity/variety | Batch snapshot |
| --- | ---: | --- | --- | --- |
| Kebun Sukamaju Demo | 8 | Owned | Padi / Ciherang | 3.25 ha active; 1.75 ha harvested |
| Kebun Cibogo Demo | 6.5 | Partnership | Kedelai / Anjasmoro | 3 ha active |
| Kebun Mekarsari Demo | 4 | Rented | Cabai keriting / Kencana | 1.5 ha active |
| Kebun Cikeas Demo | 10 | Partnership | Jagung / Bisi 18 | 4 ha active; 2 ha planned |
| Kebun Cisarua Demo | 3.5 | Owned | Tomat / Servo | 1.25 ha harvested; 0.75 ha planned |

The fixed demonstration snapshot is **5 November 2026**. It includes coordinates,
two permanent blocks per new site, persisted planting rounds/batches, all six
batch stages, published commodity SOPs and scheduled task snapshots. Matching
activity detail records, growth observations, selected risks, local placeholder
documentation and demo weather fill the Owner farm/block/activity detail pages.
The added scenario contains 10 blocks, 8 batches, 48 stage rows, 5 published
SOPs, 48 batch task snapshots, 25 activities with matching detail records,
6 observations, 6 labeled local illustrations, 5 weather records and 2 risks.
The sample coordinates, weather and observations are fictional presentation
fixtures rather than cadastral measurements or live monitoring.

On the unchanged Cileuksa baseline, Owner shows **6 sites / 44.5 ha**. The pie
contains five commodities totaling **19 ha** planted across active/harvested
cycles: Jagung 8.25, Padi 5, Kedelai 3, Cabai keriting 1.5 and Tomat 1.25 ha.
Planned allocations do not enter planted-area percentages. Table totals and
pie values still come from the normal scoped database services and farm filter.
Site names open Owner farm detail, blocks open their batch stages/SOP tasks,
and Resume opens the actual latest activity. Proyeksi remains empty.

Stable tenant-bound fixture IDs and existing-record checks make reruns
non-duplicating and preserve later edits. Existing farms (including Cileuksa)
and unrelated tenant data are retained. To restore edited demo records, use an
explicitly reviewed demo-data reset workflow rather than rerunning this seeder.

Verification on 5 November 2026: 99 integrated PostgreSQL tests / 2,447
assertions passed across Owner, dashboard, blocks and SOP task generation.
The six focused portfolio tests cover populated/detail HTTP pages, scope,
idempotency, preserved existing users/farm edits, guards and rollback after
a late detail-write failure. Pint, targeted PHPStan and frontend build passed.
The local demo database was brought up to date with the four existing additive
Farm migrations before the scenario was seeded. Browser QA confirmed six rows,
five pie sectors and six map markers on 320px/390px phones, 768px tablet,
844px landscape and 1440px desktop. Horizontal/vertical table freeze, farm and
batch navigation, expanded SOP tasks and harvest detail worked. Both themes
were checked without console warnings/errors; no UI/profile/AI files changed.
