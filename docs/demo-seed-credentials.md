# Seed data for stakeholder presentations

For a fresh local installation, run the complete seed after migrations:

```sh
php artisan --env=local db:seed --database=pgsql
```

The complete seeder creates or updates the four role accounts below, including
their passwords and current tenant. It is not the command for renaming existing
presentation data. It also creates one non-personal team named from `config('app.name')` (default `Bumi Hijauan`), one
assigned farm, crop master data, an active
planting cycle, activity and daily-log records, monitoring data, finance data,
operational targets, and a notification. It then adds the five Owner portfolio
farms and their operational details, and refreshes recognized legacy presentation
names. All seeders run through this command, including dependent seeders called
by their parents. Existing records outside the named demo records are left
untouched. The complete seed may run in any environment, including production,
as long as it targets the intended demo database.

Fresh seeds create only three commodities: `Jagung`, `Rumput`, and `Sorgum`,
with the presentation varieties `Bisi 18`, `Pakchong`, and `Bioguma 1`.
Existing portfolio records are preserved on reruns; changing these fixtures does
not convert or delete commodities, varieties or farm history already in a database.

| Role / display name | Email | Password |
| --- | --- | --- |
| SuperAdmin | `superadmin@farmer-ic.test` | `SuperAdmin123!` |
| Owner | `owner@farmer-ic.test` | `Owner123!` |
| Admin | `admin@farmer-ic.test` | `Admin123!` |
| Petugas | `petugas@farmer-ic.test` | `Petugas123!` |

The Petugas account is assigned to `Kebun Cileuksa`. Use that account to
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

The optional, separately guarded Cariu Baru logbook import and its dedicated
Petugas account are documented in [cariu-baru-demo.md](cariu-baru-demo.md).
It includes explicitly simulated future harvests and is not part of the full seed.

## Refresh existing presentation names safely

For an existing local installation, use only the additive name refresh:

```sh
php artisan --env=local db:seed --database=pgsql --class=PresentationNamesSeeder
```

It requires the existing active seeded Superadmin and its persisted membership
in the non-personal tenant with legacy technical slug `farm-ic-demo`. It runs
inside a transaction with the tenant locked and native policy authorization.
Recognized legacy tenant names (`Farm IC Demo`, `Hijauan`, and `Bumi Hijauan`)
become the configured `APP_NAME` (default `Bumi Hijauan`); custom tenant names
and the technical slug stay unchanged.
The four seeded account display names become `SuperAdmin`, `Owner`, `Admin`,
and `Petugas`. The six recognized farm names lose their `Demo` suffix and
recognized child descriptions, batch labels, fertilizer/pesticide names,
weather source labels, and illustration captions are updated in place.
Batch labels become `A`, `H`, and `P`. Illustrations remain explicitly labelled
as illustrations rather than actual field photos.

This command neither recreates fixture records nor resets accounts. IDs,
relationships, credentials, memberships, current teams, Petugas assignments,
profile fields other than the requested display names, dates, land areas, and
financial amounts remain intact. Other tenants and unrecognized custom content
are left untouched. Repeating the command makes no further changes. It may run
in any environment, including production. Do not run the complete `DatabaseSeeder`
or `DemoIdentitySeeder` merely to refresh names, because those seeders update
account credentials and assignments.

Names of existing seeder classes, test fixtures, internal UUID namespaces,
account email addresses, and the legacy tenant slug remain technical identifiers
for compatibility; they are not visible product labels.

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
- `PresentationNamesSeeder`: additive display-copy refresh for existing fixture
  records without resetting identities or operational data; runs last.
- `DemoOwnerPortfolioSeeder`: five additional Owner farms, blocks, batches and
  SOPs; automatically calls `DemoOwnerPortfolioActivitySeeder` for their details.
- `DemoFarmBlockSeeder` and `BlockMappingActivityTypeSeeder` run automatically
  through `DemoFarmSeeder` and `DemoActivitySeeder`, respectively.

## Finance dummy data

`DemoFinanceSeeder` now provides 10 shared financial categories, 24 transactions
across August–October 2026, 6 category budgets for October–December, and 3
projections. Transactions cover seed, fertilizer, labor, pest control, irrigation,
transport, equipment rental, maintenance, harvest sales, and seedling sales.
All operational records belong to `Kebun Cileuksa` in `farm-ic-demo`.
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

The finance seeder resolves the persisted demo admin and its team/farm, and
authorizes the financial model policies before
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

## Owner presentation portfolio

The complete `db:seed` command includes this scenario automatically after the
base identity, Cileuksa, SOP, monitoring, finance and operations seeders. No
additional seed command is required. Apply pending additive migrations first:

```sh
php artisan --env=local migrate --database=pgsql
php artisan --env=local db:seed --database=pgsql
```

For an existing demo database that only needs the portfolio, the targeted command
remains available without rerunning identity or finance fixtures:

```sh
php artisan --env=local db:seed --database=pgsql --class=DemoOwnerPortfolioSeeder
```

Run it only against the intended demo database; the seeder requires the
persisted `farm-ic-demo` tenant and its demo
identities. It does not call `DemoIdentitySeeder`, change passwords/profiles,
switch active tenants, reassign Petugas, truncate tables, or reset demo data.
It uses the existing persisted demo Superadmin with native policy checks for
fixture writes; Owner remains read-only. All new records belong to that demo
tenant. The complete portfolio is inserted in one transaction.

| New site | Land (ha) | Land tenure | Commodity/variety | Batch snapshot |
| --- | ---: | --- | --- | --- |
| Kebun Sukamaju | 8 | Owned | Rumput / Pakchong | 3.25 ha active; 1.75 ha harvested |
| Kebun Cibogo | 6.5 | Partnership | Sorgum / Bioguma 1 | 3 ha active |
| Kebun Mekarsari | 4 | Rented | Jagung / Bisi 18 | 1.5 ha active |
| Kebun Cikeas | 10 | Partnership | Jagung / Bisi 18 | 4 ha active; 2 ha planned |
| Kebun Cisarua | 3.5 | Owned | Rumput / Pakchong | 1.25 ha harvested; 0.75 ha planned |

The fixed demonstration snapshot is **5 November 2026**. It includes coordinates,
two permanent blocks per new site, persisted block batches, all six
batch stages, published commodity SOPs and scheduled task snapshots. Matching
activity detail records, growth observations, selected risks, local placeholder
documentation and demo weather fill the Owner farm/block/activity detail pages.
The added scenario contains 10 blocks, 8 batches, 48 stage rows, 5 published
SOPs, 48 batch task snapshots, 25 activities with matching detail records,
6 observations, 6 labeled local illustrations, 5 weather records and 2 risks.
The sample coordinates, weather and observations are fictional presentation
fixtures rather than cadastral measurements or live monitoring.

On the unchanged Cileuksa baseline, Owner shows **6 sites / 44.5 ha**. The pie
contains three commodities totaling **19 ha** planted across active/harvested
cycles: Jagung 9.75, Rumput 6.25 and Sorgum 3 ha.
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

## Application display name

Set the application name through `APP_NAME`. Laravel defaults to `Bumi Hijauan`
when this variable is absent. Shared Vue logos, accessible labels, login/footer,
report text, startup screen, email header, and browser titles use the same
`config('app.name')` value; `VITE_APP_NAME` is not a separate naming source.
Seeded tenant names and presentation addresses also use this configuration.
Changing configuration does not rename existing database records automatically;
use the guarded presentation-name refresh only for recognized fixture names.
If configuration is cached, rebuild it after changing `APP_NAME`. No frontend
rebuild is needed solely for a name change. Logo asset paths and historical
reference images keep their technical names; historical AI evaluation fixtures
are not runtime branding configuration.
