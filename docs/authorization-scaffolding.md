# Native model authorization

Laravel's existing session guard and Fortify login/password flow are unchanged.
Authorization uses automatically discovered model policies, native Gates, and
`config/access.php`. There is no permission package, stand-in subject, assignment
adapter, or manual policy registration. Superadmin is handled by the explicit
`Gate::before` bypass requested for the developer role; the matrix remains the
source of truth for every other role.

## Providers and grant source

The desktop admin logbook uses the existing DailyActivityLog, Farm, FarmActivity,
User, ActivityType and BlockMappingDetail policies. Its three GET routes under
`/admin/logbook` require authenticated/verified admin admission plus explicit
controller resource authorization. `AdminLogbookService` scopes directory rows,
report totals, activity counts, recorder options and detail relationships with
`visibleTo` before serialization. No new model prefixes or wildcard grants are
introduced. The Admin logbook UI remains read-only and DailyActivityLog
create/delete are denied. The 4 November shared-header decision restores
`daily_activity_logs.update = any` for a separate authorized header edit;
it does not grant activity creation or deletion through the logbook.
Petugas retains own-farm recording access; the developer Superadmin bypass stays.
See `docs/daily-activity-log.md` for the route map; all new routes and queries are
protected. Mobile recording still uses its existing controller and service.

Petugas to-dos, report history/detail and voluntary forms are guarded by their
resource policies and assigned-farm boundary. `FarmActivityPolicy::recordVoluntary`
requires a persisted selectable active category, farm create permission, and
view/create grants for the shared header, private photos and applicable typed
detail. `visibleTo` is applied before report pagination/totals and serialization.
The staged-planting command authorizes `recordPlanting` and `synchronize` as each
persisted assigned actor before scoped cycle reconciliation. See the current
route/storage/verification checkpoint in `petugas-activity-handoff.md`, section 8.

- `AppServiceProvider`: framework defaults only.
- `AiServiceProvider`: existing AI/RAG container bindings, preserved as before.
- `AuthorizationServiceProvider`: defines `ai.access` and the superadmin
  `Gate::before` bypass. Other roles remain subject to the matrix and persisted
  team boundary.
- `RolePermissions`: the single reader of access configuration, including grant
  scopes, recognized role validation, team membership, and assignment limits.
- `ResourcePolicy`: an abstract parent sharing policy predicates and query
  constraints. Concrete `{Model}Policy` classes declare their model prefix and
  persisted relationship paths. Laravel discovers them without `Gate::policy()`.

The merged model names/tables now determine the matrix. The temporary plot,
SOP, form, sales/purchases, and progress aliases have been replaced by
actual model prefixes. Income and expense are both `financial_transactions`;
observations are `plant_observations`, and harvests are `harvest_details`.
No invented models or permissions for nonexistent models remain.

The four application roles retain their intended responsibilities:

| Role | Grants in the revised matrix |
| --- | --- |
| owner | View every modeled resource; full interactive AI access for conversations, messages, attachments, and knowledge documents. |
| superadmin | Gate::before bypass for every policy, team boundary, assignment, and ability. |
| admin | CRUD for all non-AI modeled resources within teams using `any`; no AI access. |
| petugas | View farms and CRUD farm-operation records within assigned farms using `own`; may leave eligible teams; no finance, catalog, or AI grants. |

The current matrix denies leave without an explicit teams.leave grant.
Owner, Admin and Petugas read their own notifications and may update their own
read state. Notification inbox queries always filter the recipient, including
for Superadmin. No HTTP endpoint accepts arbitrary notification content. Reference
record writes are application-wide because those tables have no team boundary;
they still require a grant and team membership. Abilities without grants deny,
including `configure` and `forceDelete`.

Block preparation grants `farm_blocks.view = own` to assigned Petugas, and
`create/update/delete = own` only when `User::isPic()` confirms PIC status on
their assigned farm. `FarmBlockPolicy` opts into the shared
`ResourcePolicy::$ownWritesRequirePic` constraint. Each write check reloads
`assignedFarm` so cached PIC status cannot retain revoked access. The normal
persisted parent/record farm, membership, assignment and view checks still apply;
PIC status never bypasses a missing grant or changes `own` for other models.
Admin retains `any` CRUD; Owner retains read-only access and Superadmin its
existing bypass. `visibleTo` still includes assigned-farm blocks for non-PIC
Petugas, since reading does not require PIC. `planting_rounds` may be created as part of authorized batch creation,
but has no direct update/delete grants. `planting_cycle_stages` is read-only for
ordinary roles and initialized internally by the batch service. See
[farm-block-backend.md](farm-block-backend.md) for the complete route and scope map.

SOP templates are tenant-owned and variety-specific. Admin has CRUD; Owner
reads templates, versions, and tasks; Petugas has no catalog grant and reads
applied instructions through batch tasks later. Versions and tasks resolve their
tenant through `sopTemplate.team` and `version.sopTemplate.team`. Publishing uses
the version `update` ability because only draft versions are mutable; published
versions are frozen in the service.

Notification status/date filters run on the personal inbox query before totals
and pagination. Only Owners can select an associated non-personal tenant; a
tenant filter from another role or for an unrelated tenant returns 403.
`NotificationPolicy::showsTenantContext` is a presentation helper that keeps
tenant copy/filter controls Owner-only even for Superadmin. It adds no grant or
bypass. Tenant metadata is saved from the authorized notification snapshot;
request input never sets a notification's tenant or recipient.

Notification reads can reuse persisted membership data inside
`RolePermissions::withMembershipSnapshot()` for one bounded callback. The snapshot
is removed in `finally`; it never grants a new permission. HTTP inbox rendering
keeps per-record Gate checks. Queue recipient validation starts with fresh user,
role and membership reads on each call, including after provider validation.
Only a freshly eager-loaded dispatcher batch uses `reload: false`. Existing
membership helpers keep their database behavior outside this callback. See
[notification query performance](notification-query-performance.md) for the
measured scope and authorization regression coverage.

## Model coverage

Every concrete Eloquent model has its own convention-discovered policy.
There is one authentication and authorization actor: `App\Models\User`. The merged
backend no longer exposes a second `Farmer\User` projection. User targets use the
convention-discovered `UserPolicy`, and notifications use the persisted native
recipient as their personal scope. Native-user targets resolve through actual team
memberships.

Merged flat model files use the `App\Models` namespace so Composer and Laravel
discover the persisted domain relationships directly.

| Model | Permission prefix | Trusted boundary |
| --- | --- | --- |
| `ActivityPhoto` | `activity_photos` | activity.farm -> team |
| `ActivityType` | `activity_types` | Application-wide reference/system record |
| `AdministrativeRegion` | `administrative_regions` | Application-wide read-only region reference; membership required |
| `AiAttachment` | `ai_attachments` | conversation.team |
| `AiConversation` | `ai_conversations` | team |
| `AiKnowledgeChunk` | `ai_knowledge_chunks` | document.team |
| `AiKnowledgeDocument` | `ai_knowledge_documents` | team |
| `AiMessage` | `ai_messages` | conversation.team |
| `AiUsageEvent` | `ai_usage_events` | team |
| `AuditLog` | `audit_logs` | Application-wide reference/system record |
| `BlockMappingDetail` | `block_mapping_details` | activity.farm -> team |
| `Commodity` | `commodities` | Application-wide reference/system record |
| `CropVariety` | `crop_varieties` | Application-wide reference/system record |
| `DailyActivityLog` | `daily_activity_logs` | farm -> team |
| `FarmActivity` | `farm_activities` | farm -> team |
| `Farm` | `farms` | Farm itself -> team |
| `FarmBlock` | `farm_blocks` | farm -> team; create parent Farm |
| `PlantingRound` | `planting_rounds` | block.farm -> team; create parent FarmBlock; system-managed round grouping |
| `PlantingCycleStage` | `planting_cycle_stages` | plantingCycle.farm -> team; read-only timeline derived on batch creation |
| `PlantingCycleTask` | `planting_cycle_tasks` | plantingCycle.farm -> team; generated snapshot of SOP tasks for one subblok |
| `FarmRisk` | `farm_risks` | plantingCycle.farm -> team |
| `FertilizationDetail` | `fertilization_details` | activity.farm -> team |
| `FinancialBudget` | `financial_budgets` | farm -> team |
| `FinancialCategory` | `financial_categories` | Application-wide reference/system record |
| `FinancialProjection` | `financial_projections` | farm -> team |
| `FinancialTransaction` | `financial_transactions` | farm -> team |
| `HarvestDetail` | `harvest_details` | activity.farm -> team |
| `IncidentDetail` | `incident_details` | activity.farm -> team |
| `IrrigationDetail` | `irrigation_details` | activity.farm -> team |
| `Membership` | `memberships` | team |
| `Notification` | `notifications` | Personal inbox: persisted recipient only, active account and valid membership; listing without a Team explicitly spans the actor's memberships, not other recipients |
| `OperationalTarget` | `operational_targets` | farm -> team |
| `PesticideDetail` | `pesticide_details` | activity.farm -> team |
| `PlantingCycle` | `planting_cycles` | farm -> team |
| `PlantingDetail` | `planting_details` | activity.farm -> team |
| `SopTemplate` | `sop_templates` | team; create parent Team |
| `SopTemplateVersion` | `sop_template_versions` | sopTemplate.team -> team; create parent SopTemplate |
| `SopTemplateTask` | `sop_template_tasks` | version.sopTemplate.team -> team; create parent SopTemplateVersion |
| `PlantObservation` | `plant_observations` | plantingCycle.farm -> team |
| `Role` | `roles` | Application-wide reference/system record |
| `TeamInvitation` | `team_invitations` | team |
| `Team` | `teams` | Team itself |
| `User` (native actor/target) | `users` | Shared native team membership; Admin targets are Admin/Petugas with exactly one matching non-personal tenant |
| `WeatherRecord` | `weather_records` | farm -> team |

Reference/system single-record `view` checks require an explicit `any` view
grant and membership in at least one real, non-deleted team. They do not depend
on `current_team_id`; a stale active-team pointer never proves membership.
The exact model rules are:

| Model | Required grant | Additional rule |
| --- | --- | --- |
| Commodity | commodities.view = any | At least one persisted team membership |
| CropVariety | crop_varieties.view = any | At least one persisted team membership |
| FinancialCategory | financial_categories.view = any | At least one persisted team membership |
| ActivityType | activity_types.view = any | At least one persisted team membership |
| AdministrativeRegion | administrative_regions.view = any | Owner/Admin/Petugas: persisted membership; native Policy and visibleTo; application mutations default deny |
| Role | roles.view = any | At least one persisted team membership |
| AuditLog | audit_logs.view = any | At least one persisted team membership |

Their listings still use `viewAny` and `visibleTo` with an authorized team.
These shared-record grants never authorize farm/team-owned records. Those
records resolve and check their own persisted team. Notification creation receives
a persisted `User` recipient who shares a team with the actor. Notification
listings use the supplied team and the same recipient relationship.
AI messages/attachments resolve the conversation's team; knowledge chunks
resolve their document's team.

## Record and query usage

```php
Gate::authorize('viewAny', [PlantingCycle::class, $team]);
Gate::authorize('create', [PlantingCycle::class, $persistedFarm]);
Gate::authorize('update', $persistedPlantingCycle);
Gate::authorize('ai.access', $team);

// Apply before pagination, counts, exports, totals, and serialization.
$rows = PlantingCycle::visibleTo($user, $team)->paginate(20);
```

Create receives a persisted parent of the correct model type: a Team for a
Farm, a Farm for a PlantingCycle, a PlantingCycle for a PlantObservation, or a
FarmActivity for a HarvestDetail. A missing parent or an unrelated model denies.
An `own` creation must resolve to an assigned farm. Update/delete/configure
require visibility in addition to their specific mutation grant.

Every concrete model exposes the native `visibleTo(User, ?Team = null)` Eloquent scope
through an inherited model concern. It reuses its actual model policy's query
constraints, so predicates are shared with record checks. Missing/invalid
grants yield empty results. Listing admission alone never authorizes all rows.
Never pass browser-supplied role, team, user, or farm IDs as proof of authority.
Resolve the model/parent server-side before authorizing it.

Global Superadmin User Management may omit the Team argument. Query bypass is
resolved by the `access.global` Gate, whose ordinary definition denies; the
existing superadmin `Gate::before` is the sole grant. All other actors without
a Team receive an empty query. See [User Management](user-management.md).

Tenant Admin User Management resolves its Team from the actor's sole persisted
non-personal membership, not `current_team_id` or request input. `UserPolicy`
applies the same Admin/Petugas restriction to record checks and `visibleTo`
queries. Admin may create either role, but may not change an existing role or
tenant association. `users.configure` permits Petugas farm assignment changes
within that tenant; it does not permit role changes. Only Superadmin may change
existing roles or move accounts between tenants. The `users.manage` Gate admits
tenant Admin and read-only Owner to the page; `users.manageAll` remains
Superadmin-only. Owner User Management combines policy-scoped queries across
the Owner's persisted non-personal teams, including for filters and serialized
relationships. It does not grant global query access or reveal foreign teams.

`User::assignedFarm()` is a direct `belongsTo` relation through
`users.assigned_farm_id`. The nullable foreign key keeps the scaffolding explicit:
one petugas can have one assigned Farm. Owner/admin visibility does not use this
foreign key; it comes from the Farm's `team_id`. User Management's protected
create/update actions persist these assignments.

## Database prerequisite

The merged backend uses one UUID `users` table for domain records and native
authentication, with integer team IDs. The earlier split-user review is historical.

The authorization migration at
`database/migrations/2026_10_26_000003_add_farm_authorization_relationships.php`
now reads the physical `id` column types for users/farms/teams **before** any
DDL, then defines matching referencing columns and constraints. UUID, bigint,
integer, and smallint keys are supported; unsupported types fail before DDL.
Absent users, farms, or teams also fail before DDL. It runs after team support
has been restored. The superadmin role constraint is extended in a separate
authorization migration, preserving the team's identity baseline.

The local PostgreSQL schema has been verified with `php artisan migrate:fresh`.
Farms must deliberately be assigned a team before access is authorized. A record
with no provable team boundary denies even for `any`. Until the assignment table
exists, `own` access denies; `any` still works for team-linked records. Nullable
farm records, such as an unallocated financial transaction, need an authoritative
team/parent before they can be authorized; a caller cannot supply a scope to
bypass that requirement.

Application roles resolve through `User::role()` and `roles.name`, cached on the
actor instance. `RolePermissions` is the only matrix reader. The old authorization
migration skips `users.role` when `role_id` exists, so it cannot shadow the Eloquent
relationship. `role_id` remains guarded; trusted assignment must validate the role
name with `RolePermissions::isKnownRole()` before associating the persisted role.
The test factory accepts the existing `['role' => 'admin']` fixture shorthand and
converts it into a role association. Team ownership alone grants no application role.

## Enforcement coverage and remaining integration

Existing team update/delete/member/invitation/leave checks now use the static
matrix through native policies. Their personal-team and team-owner lifecycle
constraints remain intact. Existing team capability props delegate to the same
Gates, replacing the old independent team-role grant list.

AI knowledge index/create/update/publish/archive/delete now call their specific
native policy ability. The index uses `visibleTo`, and config revocations are
honored independently for reads, creates, updates, and deletes.

This remains authorization scaffolding for the rest of the app. Existing
home/demo, farm/activity/report demo pages, team index/store/edit/switch,
settings/profile flows, other AI conversation/message/voice routes, background
jobs/commands, and AI/RAG retrieval queries have not been migrated to these
application-role checks/scopes. They retain their existing authentication,
validation, membership, and business checks, where present. In particular,
installing `ai.access` does not make every existing AI route owner-only yet.
Future controllers and non-HTTP entry points must invoke Gate before acting;
queries must use `visibleTo` before returning data. No claim is made that every
route or query now enforces application RBAC. `/bypass` requires authentication and
authorizes `ai.access` before redirecting. The local-only `/api/kebun` Farm CRUD
and `/api/kebun/{farm}/daily-logs` endpoints now require authentication and invoke
their native policies; Farm listing and daily-log pagination use their model
`visibleTo` scopes before returning records.

The authenticated Inertia pages and web form routes under `/finance` now authorize
all category, transaction, budget, and projection CRUD actions. Record reads,
list pagination, transaction summaries, and CSV exports apply `visibleTo` before
serialization or aggregation. Team context comes from the authenticated user's
persisted current team. Creation derives its farm from the persisted route
parent and recorder metadata from the authenticated user. Categories retain their
explicit application-wide reference scope. See [finance-backend.md](finance-backend.md)
for routes, form payloads, and PostgreSQL integration coverage. Finance pages use
database-backed Inertia props; AI finance snapshots still use labeled demo sources. Other application
controllers and services remain outside this scaffolding rollout.

After changing the file grants, rebuild config cache when used and restart
long-running workers. New ownership semantics or abilities still require code
and corresponding tests.

## Handoff checkpoint

See [`rbac-authentication-guide.md`](rbac-authentication-guide.md) for the
backend/frontend usage guide and [`rbac-scaffolding-checkpoint.md`](rbac-scaffolding-checkpoint.md)
for the current implementation boundary and validation snapshot.

## Verification

The authorization tests use real persisted PostgreSQL records and native Gate/policy
discovery. Migration tests additionally capture Blueprints and compile SQL for key
type combinations. Coverage includes every role/model action,
query/record agreement, team and assignment boundaries, counts/paginator totals/
export selections, guarded roles, invalid grants, assignment caching and unique
constraints, configuration validation, real HTTP 403s, and forged input.

```powershell
php artisan test tests/Feature/Authorization
```

## Review record and grant comparison

[authorization-review.md](authorization-review.md) records the findings before
code changes, every grant (139 rows), the complete prior model-file inventory,
the chosen future integration direction, skip reasons/resolutions, and files
changed during the seven-item review. This review did not add or change route
or controller enforcement. Authorization names now use `farms`,
`assignedFarmId`, and `farm_assignment_limits`; only real Indonesian fixture
names/titles retain the domain word. The review describes the older schema split;
the current PostgreSQL migration and model tests now validate the unified schema.

Latest validation: `php artisan migrate:fresh --force` passed on the local demo
database. `php artisan test --compact` runs against dedicated PostgreSQL test
databases; Fortify's disabled two-factor challenge test is explicitly skipped.

## Farm force deletion

`FarmPolicy::forceDelete(User, Farm)` is a Farm-specific ability reserved for
the existing developer bypass. Its normal implementation returns false for
every actor; `Gate::before` is the only SuperAdmin grant. Do not add a
`farms.forceDelete` matrix grant, a global wildcard, or a separate role check in
the controller/service. Normal `delete` remains guarded by history/assignment
integrity rules, including for SuperAdmin.

The GET preview and DELETE confirmation at
`/admin/farm-management/{farm}/force-delete` are protected by auth/verified,
portal admission, and the Farm ability. Preview queries use `visibleTo` before
collecting IDs/counts; raw integrity checks inspect outside references only to
block the operation. No new unprotected route is introduced. Existing public
demo routes and the previously unaudited AI conversation/message/voice query
paths keep their existing checks; this task does not claim to secure them.
# Task-linked activity recording

Shared daily headers use the existing DailyActivityLogPolicy and static grants:
one persisted farm/date header, shared between authorized officers in that farm.
`own` remains farm-assignment scope, not `recorded_by`. Header update is a
separately authorized action; activity appends cannot overwrite header values.
Context lookup authorizes farm view and daily-log viewAny before a scoped query.
Petugas daily reports scope both the parent log and all activity/detail/photo
queries. The general report-photo route authorizes farm, activity and photo,
checks persisted parent links, then uses a scoped file lookup. Only the known
shared seed placeholder or private task-activity files are served. Header creator
and activity performer names are minimal projections through already-authorized
reports, not a user-directory grant; no account details or user-management access
are exposed. Owner remains read-only; other-farm/tenant officers cannot read or
edit shared headers. No grants or role definitions were added.

All new daily-report, context, update and photo routes are protected. Existing
daily-log child-list reads now also apply visibleTo. Unrelated routes and AI/RAG
retrieval have not been re-audited by this change.

Initial planting uses `FarmActivityPolicy::recordPlanting` against a persisted
cycle and composes cycle update, activity create, daily-log/photo/planting-detail
view/create grants. Petugas reads only its assigned farm's subblok and reports.
The controller authorizes before the transactional service; FormRequest delegates
the same ability early so validation cannot expose foreign-farm context.
`PlantingCycleTaskPolicy::synchronize` composes cycle update and task-list admission
for derived scheduling, without granting arbitrary task CRUD. Job/command/seed
callers must explicitly authorize it before invoking `generateForSubblok` with
an actor. Petugas now has tenant-scoped `any` read grants for SOP templates,
versions and tasks, allowing scoped generation. SOP mutations and admin portal
admission remain denied. All new planting/synchronization/photo routes are guarded;
this work does not audit unrelated existing routes.

`FarmActivityPolicy::recordTask` authorizes the composite report creation use
case against a persisted `PlantingCycleTask`. It requires task visibility and
the existing activity/daily-log/photo/applicable typed-detail view/create
grants within the same farm boundary. It is not a new matrix grant and does
not authorize updating or completing a task. HTTP controllers check it before
calling the report service. The Petugas portal additionally requires that
the task's farm equals the persisted assignment, even for developer portal use.

Petugas can read activity types and crop varieties as shared reference records
(`any`, with valid team membership), but cannot mutate them. Task listing,
summary counts, linked reports and photo serialization use `visibleTo`.
