# Native model authorization

Laravel's existing session guard and Fortify login/password flow are unchanged.
Authorization uses automatically discovered model policies, native Gates, and
`config/access.php`. There is no permission package, stand-in subject, assignment
adapter, or manual policy registration. The explicitly approved developer
exception is a `Gate::before` bypass for `superadmin`.

## Providers and grant source

- `AppServiceProvider`: framework defaults only.
- `AiServiceProvider`: existing AI/RAG container bindings, preserved as before.
- `AuthorizationServiceProvider`: defines `ai.access` and the explicitly approved
  developer bypass. `superadmin` returns `true` from `Gate::before`, so it bypasses
  team membership, scopes, and undefined abilities. Other roles use the explicit
  matrix and `ai.access` still requires membership in the supplied team.
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
| superadmin | Developer-level unrestricted access through `Gate::before`, including all model actions, AI, cross-team records, and undefined abilities. |
| admin | CRUD for all non-AI modeled resources within teams using `any`; no AI access. |
| petugas | View farms and CRUD farm-operation records within assigned farms using `own`; no team, finance, catalog, or AI grants. |

Admin/petugas may update their own notifications and leave non-personal teams
that they do not own. These are explicit matrix grants. The `Role` model is
covered for read access; it is not a dynamic permission editor. Known native
abilities without a grant deny by default, including configure and operational
deletes not expressly listed in the matrix.

## Model coverage

There are now 35 concrete policy classes for 35 concrete Eloquent model classes.
`App\Models\Farmer\User` is a distinct domain target, never an actor. It uses
`#[UsePolicy(App\Policies\Farmer\UserPolicy::class)]`; native `App\Models\User`
uses its own convention-discovered policy. No persisted domain-to-native identity
link exists yet: domain-user reads, listings, creates, and mutations deny, and
its `visibleTo` query is empty. Neither equal IDs nor equal emails prove identity.
Notifications also deny because their persisted recipient is that unlinked
domain user. Native-user targets still resolve through actual native team
memberships. Shared policy signatures distinguish actor and target with fully
qualified native/domain user types. Pint preserves these explicit type hints.

Merged flat model files were aligned to their `App\Models` namespace so
Composer and Laravel can actually discover them. Domain-user relationships
still point to the original domain projection.

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
| `Commodity` | `commodities` | Application-wide reference/system record |
| `CropVariety` | `crop_varieties` | Application-wide reference/system record |
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
| `Notification` | `notifications` | Domain recipient needs an explicit native identity link; currently denies |
| `OperationalTarget` | `operational_targets` | farm -> team |
| `PesticideDetail` | `pesticide_details` | activity.farm -> team |
| `PlantingCycle` | `planting_cycles` | farm -> team |
| `PlantingDetail` | `planting_details` | activity.farm -> team |
| `PlantObservation` | `plant_observations` | plantingCycle.farm -> team |
| `Role` | `roles` | Application-wide reference/system record |
| `TeamInvitation` | `team_invitations` | team |
| `Team` | `teams` | Team itself |
| `User` (native actor/target) | `users` | Shared native team membership |
| `Farmer\User` (domain target) | `users` | Requires an explicit native identity link; currently denies |
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
records resolve and check their own persisted team. Notification recipient
ownership remains the intended personal scope after identity integration;
today the absence of that link denies instead of joining IDs across models.
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

`User::farms()` is a native many-to-many relationship through `farm_user`.
Assigned IDs are loaded once on that User instance and reused across policies
and query scopes, across all teams. Petugas access denies when more than one
distinct farm is assigned; admin can have several. Assignment workflows must
validate the configured limit and clear/reload the `farms` relation after a
change. No assignment management endpoint has been added.

## Database prerequisite

A read-only check of the configured local `farmer_ic_dev` PostgreSQL database
confirmed UUID `users.id` and `farms.id`, with no teams or native memberships.
The integer user/team design is also present in the read-only verified legacy
SQLite database, as well as the starter migrations and SQLite policy fixtures; it is not integrated into that live database. Normalization
(3NF) is independent of this identity/key mismatch.

The chosen direction for a **later** integration is to preserve UUID domain
users and add an explicit persisted link to native authentication users. No
link table, schema conversion, identity backfill, connection split, or migration
was implemented/applied in this review. Duplicate users/AI table names and
native password/role attributes still need a reviewed integration design.
See [the full review](authorization-review.md) for every user FK and trade-offs.

The deferred migration at
`database/migrations/authorization/2026_10_26_000001_add_farm_authorization_relationships.php`
now reads the physical `id` column types for users/farms/teams **before** any
DDL, then defines matching referencing columns and constraints. UUID, bigint,
integer, and smallint keys are supported; unsupported types fail before DDL.
Absent users, farms, or teams also fail before DDL. Matching physical FK types
does not create or validate the missing native/domain identity link.

Do not run this migration against the current local baseline. No runnable
integration is claimed. Its tests capture Blueprints and compile PostgreSQL
SQL without PDO or SQL execution. Policy tests build independent SQLite
fixtures instead of invoking this deferred migration.

No migration or backfill has been run against a working database here. Existing
farms must deliberately be assigned a team before access is authorized. A record
with no provable team boundary denies even for `any`. Until the assignment table
exists, `own` access denies; `any` still works for team-linked records. Nullable
farm records, such as an unallocated financial transaction, need an authoritative
team/parent before they can be authorized; a caller cannot supply a scope to
bypass that requirement.

`users.role` stays nullable and guarded. In a trusted backend assignment process,
validate the proposed string using `RolePermissions::isKnownRole()` before
explicitly assigning and saving it. Do not copy role input during registration
or profile updates. Existing and newly created accounts receive no automatic
application privileges. Owning a starter team does not grant application owner access. A trusted developer
account with `role = superadmin` is the explicit global authorization exception.

## Enforcement coverage and remaining integration

Existing team update/delete/member/invitation/leave checks now use the static
matrix through native policies. Their personal-team and team-owner lifecycle
constraints remain intact. Existing team capability props delegate to the same
Gates, replacing the old independent team-role grant list.

AI knowledge index/create/update/publish/archive/delete now call their specific
native policy ability. The index uses `visibleTo`, and config revocations are
honored independently for reads, creates, updates, and deletes.

This remains authorization scaffolding for the rest of the app. Existing
home/demo/bypass, farm/activity/finance/report pages, team index/store/edit/switch,
settings/profile flows, other AI conversation/message/voice routes, background
jobs/commands, and AI/RAG retrieval queries have not been migrated to these
application-role checks/scopes. They retain their existing authentication,
validation, membership, and business checks, where present. In particular,
installing `ai.access` does not make every existing AI route owner-only yet.
Future controllers and non-HTTP entry points must invoke Gate before acting;
queries must use `visibleTo` before returning data. No claim is made that every
route or query now enforces application RBAC.

After changing the file grants, rebuild config cache when used and restart
long-running workers. New ownership semantics or abilities still require code
and corresponding tests.

## Verification

The authorization tests use real persisted SQLite records, native Gate/policy
discovery, and independent relational fixtures. The deferred migration is
validated through SQL compilation only. Fixture tests exercise native policies
and assert denial of unlinked domain targets; they do not pretend to execute or validate
the separate PostgreSQL schema. Coverage includes every role/model action,
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
`assignedFarmIds`, and `farm_assignment_limits`; only real Indonesian fixture
names/titles retain the domain word. Fixture-based tests establish policy logic,
not compatibility with the separately migrated PostgreSQL domain schema.
