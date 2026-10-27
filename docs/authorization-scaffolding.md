# Native model authorization

Laravel's existing session guard and Fortify login/password flow are unchanged.
Authorization uses automatically discovered model policies, native Gates, and
`config/access.php`. There is no permission package, stand-in subject, assignment
adapter, or manual policy registration. Superadmin is handled by the explicit
`Gate::before` bypass requested for the developer role; the matrix remains the
source of truth for every other role.

## Providers and grant source

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

Admin/petugas may leave non-personal teams that they do not own. Owner views
notifications, while admin and superadmin have notification CRUD grants. Reference
record writes are application-wide because those tables have no team boundary;
they still require a grant and team membership. Abilities without grants deny,
including `configure` and `forceDelete`.

## Model coverage

There are now 36 concrete policy classes for the 36 concrete Eloquent models.
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
| `Notification` | `notifications` | Persisted recipient's team membership; `own` additionally requires the actor to be the recipient |
| `OperationalTarget` | `operational_targets` | farm -> team |
| `PesticideDetail` | `pesticide_details` | activity.farm -> team |
| `PlantingCycle` | `planting_cycles` | farm -> team |
| `PlantingDetail` | `planting_details` | activity.farm -> team |
| `PlantObservation` | `plant_observations` | plantingCycle.farm -> team |
| `Role` | `roles` | Application-wide reference/system record |
| `TeamInvitation` | `team_invitations` | team |
| `Team` | `teams` | Team itself |
| `User` (native actor/target) | `users` | Shared native team membership |
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

Every concrete model exposes the native `visibleTo(User, Team)` Eloquent scope
through an inherited model concern. It reuses its actual model policy's query
constraints, so predicates are shared with record checks. Missing/invalid
grants yield empty results. Listing admission alone never authorizes all rows.
Never pass browser-supplied role, team, user, or farm IDs as proof of authority.
Resolve the model/parent server-side before authorizing it.

`User::assignedFarm()` is a direct `belongsTo` relation through
`users.assigned_farm_id`. The nullable foreign key keeps the scaffolding explicit:
one petugas can have one assigned Farm. Owner/admin visibility does not use this
foreign key; it comes from the Farm's `team_id`. No assignment management endpoint
has been added.

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
home/demo, farm/activity/finance/report pages, team index/store/edit/switch,
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
`visibleTo` scopes before returning records. Other application controllers and
services remain outside this scaffolding rollout.

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
