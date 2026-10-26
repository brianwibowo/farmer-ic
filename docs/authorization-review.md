# Native authorization review - 26 October 2026

Initial inspection was reported before code edits. Live PostgreSQL metadata was
read inside a READ ONLY transaction, rolled back afterward. No row data or
credentials were read. No live migration or schema integration is authorized here.

## 1. Schema split: verified facts and selected future direction

The live `farmer_ic_dev` database has `users.id uuid` and `farms.id uuid`.
There are no `teams`, `team_members`, or `farm_user` tables there. Integer
users/teams are the native starter migration design, the read-only verified legacy database/database.sqlite tables, and the SQLite test fixture;
they are not the current PostgreSQL schema. This finding is independent of 3NF.

Every direct user/team reference in the isolated domain baseline follows.
All listed tables also have UUID primary keys; no domain table references teams.

| Domain table | Foreign-key column(s) | Referenced key | Column type |
| --- | --- | --- | --- |
| `farms` | `owner_id`, `created_by` | `users.id` | UUID |
| `farm_activities` | `performed_by` | `users.id` | UUID |
| `plant_observations` | `observed_by` | `users.id` | UUID |
| `financial_transactions` | `recorded_by` | `users.id` | UUID |
| `financial_budgets` | `created_by` | `users.id` | UUID |
| `operational_targets` | `created_by` | `users.id` | UUID |
| `ai_conversations` | `user_id` | `users.id` | UUID |
| `ai_knowledge_documents` | `uploaded_by` | `users.id` | UUID |
| `ai_usage_logs` | `user_id` | `users.id` | UUID |
| `notifications` | `user_id` | `users.id` | UUID |
| `audit_logs` | `user_id` | `users.id` | UUID |

The native starter schema has bigint `users.id` and `teams.id` (SQLite stores
these as integer). Its direct references are:

| Native table | User references | Team references | Type |
| --- | --- | --- | --- |
| users | none | current_team_id | bigint |
| team_members | user_id | team_id | bigint |
| team_invitations | invited_by | team_id | bigint |
| ai_conversations | user_id | team_id | bigint |
| ai_messages | user_id | none | bigint |
| ai_attachments | user_id | none | bigint |
| ai_usage_events | user_id | team_id | bigint |
| ai_knowledge_documents | created_by, reviewed_by | team_id | bigint |
| ai_knowledge_chunks | none | team_id | bigint |

Other integration differences also need an explicit decision: domain users use
`role_id` and `password_hash`; the native actor expects a string `role`,
`password`, Fortify fields, and team memberships. The domain `roles.name` CHECK
currently admits owner/admin/petugas, while the static matrix also has
superadmin. AI tables share names across designs but have incompatible keys and
columns. Do not run default starter migrations over the migrated UUID baseline.

| Option | Benefits | Costs / risks |
| --- | --- | --- |
| A. Native integer identity | One actor identity; simplest native Fortify/team relations. Domain record IDs can stay UUID. | Remap all 12 UUID user FK columns across 11 tables, preserve identity/audit mapping, and reconcile users/AI column collisions. Requires a reviewed data migration. |
| B. Keep UUID domain identity and add explicit native links (recommended) | Preserves the migrated domain schema/data and current native authentication; makes the actor/target distinction explicit. | Separate PostgreSQL schemas or connections must avoid duplicate table names. A persisted one-to-one native bigint user to domain UUID user link, farm/team links, connection boundaries, and lifecycle synchronization are required. Cross-database FK constraints are unavailable if separate databases are chosen. |
| C. One UUID identity for native authentication and domain | Avoids dual user identities and UUID-to-integer user mapping long-term. | Adapt native users, teams/membership FKs, Fortify attributes, existing AI tables, and data. Largest authentication migration and regression scope. |

Recommendation: B minimizes disruption to your already migrated, normalized
schema and keeps App\Models\User as the only actor. This is a proposal, not an
implemented link. The user selected option B for a later integration; no schema changes or identity link were made in this review.

## 2-7. Inspection findings

2. The authorization migration hardcodes bigint user/team keys and UUID farm
   keys. The farm key matches the baseline; the user key does not match the
   current PostgreSQL users. Teams are absent. Never apply it as-is to that DB.
3. UserPolicy currently shares the target ID with native team membership lookup.
   Farmer\User::teams() incorrectly reuses native `team_members.user_id` with no
   persisted identity link. NotificationPolicy also reaches that domain user.
   The ID-coincidence path must be removed and domain targets must fail closed.
4. Commodity, CropVariety, FinancialCategory, ActivityType, Role, and AuditLog
   inherit global=true. Today their view uses explicit prefix.view plus
   membership in currentTeam. It wrongly depends on current_team_id when another
   real membership could satisfy the required application-wide rule.
5. Rename the farm relationship, cached relation name, ID helper, and assignment
   limit config key. Existing Indonesian place names and document titles may
   retain the real domain word; they are not authorization identifiers.
6. The complete prior model diff is listed below. Namespace/import corrections
   are required to load the merged classes at their existing paths. No changes
   to casts, fillable/hidden fields, key generation, timestamps, or existing
   business relationships are required by this review.
7. The initial full suite passed 165 tests with 2,422 assertions and 4 skips.
   Exact skip names/reasons and resolution are recorded after verification below.

## Complete grants before review fixes

Every row is one actual grant from config/access.php. No grant changes are
planned in this review; only the assignment-limit identifier is renamed.
This is the model-driven matrix, not a claim of equivalence to the original
11-prefix scaffold. Added reference/system/detail prefixes, finance budgets and
projections, notifications, and team lifecycle grants should be reviewed by the
product owner. Missing entries deny. `viewAny` consumes the corresponding view.

| Role | Prefix | Ability | Scope |
| --- | --- | --- | --- |
| owner | `activity_photos` | view | any |
| owner | `activity_types` | view | any |
| owner | `ai_attachments` | view | any |
| owner | `ai_conversations` | view | any |
| owner | `ai_knowledge_chunks` | view | any |
| owner | `ai_knowledge_documents` | view | any |
| owner | `ai_messages` | view | any |
| owner | `ai_usage_events` | view | any |
| owner | `audit_logs` | view | any |
| owner | `commodities` | view | any |
| owner | `crop_varieties` | view | any |
| owner | `farms` | view | any |
| owner | `farm_activities` | view | any |
| owner | `farm_risks` | view | any |
| owner | `fertilization_details` | view | any |
| owner | `financial_budgets` | view | any |
| owner | `financial_categories` | view | any |
| owner | `financial_projections` | view | any |
| owner | `financial_transactions` | view | any |
| owner | `harvest_details` | view | any |
| owner | `incident_details` | view | any |
| owner | `irrigation_details` | view | any |
| owner | `memberships` | view | any |
| owner | `notifications` | view | any |
| owner | `operational_targets` | view | any |
| owner | `pesticide_details` | view | any |
| owner | `planting_cycles` | view | any |
| owner | `planting_details` | view | any |
| owner | `plant_observations` | view | any |
| owner | `roles` | view | any |
| owner | `teams` | view | any |
| owner | `team_invitations` | view | any |
| owner | `users` | view | any |
| owner | `weather_records` | view | any |
| owner | `ai_conversations` | create | any |
| owner | `ai_conversations` | update | any |
| owner | `ai_conversations` | delete | any |
| owner | `ai_messages` | create | any |
| owner | `ai_messages` | update | any |
| owner | `ai_messages` | delete | any |
| owner | `ai_attachments` | create | any |
| owner | `ai_attachments` | update | any |
| owner | `ai_attachments` | delete | any |
| owner | `ai_knowledge_documents` | create | any |
| owner | `ai_knowledge_documents` | update | any |
| owner | `ai_knowledge_documents` | delete | any |
| owner | `ai` | access | any |
| superadmin | `farms` | view | any |
| superadmin | `farms` | create | any |
| superadmin | `farms` | update | any |
| superadmin | `farms` | delete | any |
| superadmin | `activity_types` | view | any |
| superadmin | `activity_types` | create | any |
| superadmin | `activity_types` | update | any |
| superadmin | `activity_types` | delete | any |
| superadmin | `commodities` | view | any |
| superadmin | `commodities` | create | any |
| superadmin | `commodities` | update | any |
| superadmin | `commodities` | delete | any |
| superadmin | `crop_varieties` | view | any |
| superadmin | `crop_varieties` | create | any |
| superadmin | `crop_varieties` | update | any |
| superadmin | `crop_varieties` | delete | any |
| superadmin | `financial_categories` | view | any |
| superadmin | `financial_categories` | create | any |
| superadmin | `financial_categories` | update | any |
| superadmin | `financial_categories` | delete | any |
| superadmin | `users` | view | any |
| superadmin | `users` | create | any |
| superadmin | `users` | update | any |
| superadmin | `users` | delete | any |
| superadmin | `teams` | view | any |
| superadmin | `teams` | create | any |
| superadmin | `teams` | update | any |
| superadmin | `teams` | delete | any |
| superadmin | `memberships` | view | any |
| superadmin | `memberships` | create | any |
| superadmin | `memberships` | update | any |
| superadmin | `memberships` | delete | any |
| superadmin | `team_invitations` | view | any |
| superadmin | `team_invitations` | create | any |
| superadmin | `team_invitations` | update | any |
| superadmin | `team_invitations` | delete | any |
| superadmin | `roles` | view | any |
| superadmin | `audit_logs` | view | any |
| superadmin | `notifications` | view | any |
| admin | `farms` | view | own |
| admin | `activity_types` | view | any |
| admin | `commodities` | view | any |
| admin | `crop_varieties` | view | any |
| admin | `financial_categories` | view | any |
| admin | `teams` | view | any |
| admin | `notifications` | view | own |
| admin | `notifications` | update | own |
| admin | `teams` | leave | any |
| admin | `financial_transactions` | view | own |
| admin | `financial_transactions` | create | own |
| admin | `financial_projections` | view | own |
| admin | `financial_projections` | create | own |
| admin | `financial_budgets` | view | own |
| admin | `financial_budgets` | create | own |
| petugas | `farms` | view | own |
| petugas | `activity_types` | view | any |
| petugas | `commodities` | view | any |
| petugas | `crop_varieties` | view | any |
| petugas | `financial_categories` | view | any |
| petugas | `teams` | view | any |
| petugas | `notifications` | view | own |
| petugas | `notifications` | update | own |
| petugas | `teams` | leave | any |
| petugas | `farm_activities` | view | own |
| petugas | `farm_activities` | create | own |
| petugas | `activity_photos` | view | own |
| petugas | `activity_photos` | create | own |
| petugas | `planting_cycles` | view | own |
| petugas | `planting_cycles` | create | own |
| petugas | `planting_details` | view | own |
| petugas | `planting_details` | create | own |
| petugas | `fertilization_details` | view | own |
| petugas | `fertilization_details` | create | own |
| petugas | `irrigation_details` | view | own |
| petugas | `irrigation_details` | create | own |
| petugas | `pesticide_details` | view | own |
| petugas | `pesticide_details` | create | own |
| petugas | `incident_details` | view | own |
| petugas | `incident_details` | create | own |
| petugas | `harvest_details` | view | own |
| petugas | `harvest_details` | create | own |
| petugas | `plant_observations` | view | own |
| petugas | `plant_observations` | create | own |
| petugas | `weather_records` | view | own |
| petugas | `weather_records` | create | own |
| petugas | `farm_risks` | view | own |
| petugas | `farm_risks` | create | own |
| petugas | `operational_targets` | view | own |
| petugas | `operational_targets` | create | own |
| petugas | `planting_cycles` | update | own |
| petugas | `plant_observations` | update | own |
| petugas | `planting_details` | update | own |

Unchanged grant totals: owner 47, superadmin 39, admin 15, petugas 38 (139 total).

## Model file inventory against the pre-refactor merge

The comparison base is HEAD `6c7b618` (the model merge), not the staged index.
At inspection, authorization work from the preceding task was already staged; this review does
not stage, unstage, or commit it. This list describes that work as well as the
focused corrections in this review.

| File | Nature of prior changes | Disposition / reason |
| --- | --- | --- |
| `app/Models/ActivityDetail.php` | namespace and binding-preserving imports; visibility trait | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/ActivityPhoto.php` | namespace and binding-preserving imports | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/ActivityType.php` | namespace and binding-preserving imports | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/AiAttachment.php` | visibility trait | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/AiConversation.php` | visibility trait | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/AiKnowledgeChunk.php` | visibility trait | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/AiKnowledgeDocument.php` | visibility trait | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/AiMessage.php` | visibility trait | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/AiUsageEvent.php` | visibility trait | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/AuditLog.php` | namespace and binding-preserving imports | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/Commodity.php` | namespace and binding-preserving imports | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/CropVariety.php` | namespace and binding-preserving imports | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/Farm.php` | team relationship / type documentation; namespace-dependent imports | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/FarmActivity.php` | namespace and binding-preserving imports | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/FarmRisk.php` | namespace and binding-preserving imports | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/Farmer/User.php` | UsePolicy attribute; unsafe native teams relationship; namespace-dependent imports | Remove unsupported teams relationship; retain policy attribute and necessary imports. |
| `app/Models/FarmerModel.php` | namespace and binding-preserving imports; visibility trait | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/FertilizationDetail.php` | namespace and binding-preserving imports | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/FinancialBudget.php` | namespace and binding-preserving imports | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/FinancialCategory.php` | namespace and binding-preserving imports | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/FinancialProjection.php` | namespace and binding-preserving imports | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/FinancialTransaction.php` | namespace and binding-preserving imports | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/HarvestDetail.php` | namespace and binding-preserving imports | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/IncidentDetail.php` | namespace and binding-preserving imports | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/IrrigationDetail.php` | namespace and binding-preserving imports | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/Membership.php` | visibility trait | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/Notification.php` | namespace and binding-preserving imports | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/OperationalTarget.php` | namespace and binding-preserving imports | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/PesticideDetail.php` | namespace and binding-preserving imports | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/PlantObservation.php` | namespace and binding-preserving imports | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/PlantingCycle.php` | namespace and binding-preserving imports | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/PlantingDetail.php` | namespace and binding-preserving imports | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/Role.php` | namespace and binding-preserving imports | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/Team.php` | visibility trait | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/TeamInvitation.php` | visibility trait | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/User.php` | visibility trait; farm assignment relationship / cached IDs | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |
| `app/Models/WeatherRecord.php` | namespace and binding-preserving imports | Retain: native authorization addition or necessary PSR-4 autoload repair; preserve model behavior. |

No file moves are needed. Reverting the 25 corrected flat-file namespaces would
make their App\Models classes unavailable to Composer/Laravel policy discovery;
retaining those repairs is necessary. Their original relationship bindings to
App\Models\Farmer\User were preserved explicitly. No unrelated business
behavior changes are retained; this review only changes User assignment naming
and the domain projection's unsupported teams relationship/policy attribute. Authorization source and tests remain separate
from real PostgreSQL integration, whose selected explicit-link strategy still awaits a separately reviewed implementation.

## Resolutions by review item

1. **Report only.** The UUID baseline and native integer schema were compared;
   local PG metadata was verified read-only. Option B is selected for a later
   design/implementation; there is no new link, schema conversion, or backfill.
2. **Key types fixed in the deferred authorization migration only.** Referencing
   column types come from real referenced-key metadata, with all inputs checked
   before DDL. PostgreSQL SQL compilation tests cover bigint/UUID users, UUID
   farms, bigint/integer teams, FK targets, and unique assignment constraints.
   No migration SQL is executed. The real local DB has no teams, so the migration
   remains inapplicable there until integration is designed and implemented.
3. **Identity coincidence removed.** The native actor is always
   App\Models\User. Farmer\User has no proven native link, no native teams()
   relationship, and a separate real model policy that denies listing/creation.
   Shared record/query predicates also deny domain-user and notification targets;
   no matching numeric ID or email can grant access. Native users still use
   persisted native team membership. Tests include coincident target IDs.
4. **Shared-record view corrected.** Commodity, CropVariety, FinancialCategory,
   ActivityType, Role, and AuditLog require prefix.view = any and at least one
   real, non-deleted team membership. currentTeam is not authority and is not
   required. Each model is tested with no grant, with a grant but no membership,
   and with grant + membership; farm authorization is checked independently.
5. **Names aligned.** User::farms(), cached `farms`, assignedFarmIds(), and
   farm_assignment_limits replace the old authorization names. User-owned
   AGENTS.md already uses farms and was not edited by this task. Kept only
   `Kebun Cianjur` (realistic Indonesian farm fixture name) and `Pedoman kebun`
   (Indonesian knowledge-document title, including its expected assertion).
   Historical review text/older demo and AI documents may describe the domain
   term; no active authorization identifier retains it.
6. **Model ownership respected.** This review edits only app/Models/User.php
   (assignment names/cache, doc type) and app/Models/Farmer/User.php (remove the
   unsafe authorization teams relationship; use the dedicated domain policy).
   The prior inventory above lists every model changed by the earlier refactor.
   Necessary namespace/autoload repairs are retained and justified above. No
   casts, fillable fields, hidden fields, timestamps, key generation, business
   relationships, or model file locations were changed in this review.
7. **Skipped tests audited.** See the exact test table below. Two obsolete 2FA
   feature guards on password-settings tests were removed, with assertions aligned
   to the existing password-only controller response. Production code is unchanged.
   Two legitimately unavailable scenarios remain skipped with explicit reasons.
   This repository uses PHPUnit: markTestSkipped('reason') is the native
   equivalent of Pest's ->skip('reason').

Pint's fully_qualified_strict_types rewrite was disabled in pint.json to preserve
requested explicit actor/domain type hints; the Laravel preset and other checks
remain enabled. No unrelated source files were restyled.

## Original four skips: exact tests, files, reasons, disposition

| Test | File | Original reason | Disposition |
| --- | --- | --- | --- |
| AuthenticationTest::test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge | tests/Feature/Auth/AuthenticationTest.php | Two-factor authentication is not enabled. | Retain skip with explicit explanation: config/fortify.php disables 2FA and therefore its challenge routes are not registered. Requires a dedicated enabled-feature test configuration. |
| FarmerIcMigrationsTest::test_postgresql_dbml_contract_constraints_and_migration_round_trip | tests/Feature/Database/FarmerIcMigrationsTest.php | Set FARMER_IC_TEST_DATABASE to a dedicated local PostgreSQL test database. | Retain skip with explicit explanation: migration/rollback test needs an empty farmer_ic_schema_test_* database; never use farmer_ic_dev. No such test database was supplied or created here. |
| SecurityTest::test_security_page_is_displayed | tests/Feature/Settings/SecurityTest.php | Fortify feature [two-factor-authentication] is not enabled. | Fixed: password settings are implemented independently of 2FA; test now asserts passwordRules and the actual response without obsolete 2FA props. |
| SecurityTest::test_security_page_renders_without_two_factor_when_feature_is_disabled | tests/Feature/Settings/SecurityTest.php | Fortify feature [two-factor-authentication] is not enabled. | Fixed: the disabled-feature branch must run when the feature is disabled; its obsolete props were replaced with the current password-settings contract. |

## Still open

- Implement the selected explicit-identity-link integration in a separately
  reviewed change, including physical schemas/connections, one-to-one identity
  link constraints, team/farm mapping, and UUID/native model column conflicts.
- Add integration tests against that completed schema. The current tests prove
  policy logic with SQLite fixtures and migration SQL shape through compilation.
- Existing unprotected routes/queries are unchanged from the scaffold document:
  operational/demo pages, team list/create/edit/switch, remaining AI endpoints,
  background jobs/commands, and AI/RAG retrieval still need separate enforcement
  integration. No route or controller enforcement was changed in this review.

## Files changed during this review

| File | Change |
| --- | --- |
| app/Policies/ResourcePolicy.php | Explicit actor/target types, reject unlinked projections in records/queries, membership-based shared-record view, farm naming. |
| app/Policies/Farmer/UserPolicy.php | Dedicated native policy for the real domain model; listing/creation deny until a link exists. |
| app/Policies/NotificationPolicy.php | Deny unlinked domain-recipient listing/creation; shared predicates deny records/queries. |
| app/Models/User.php | Rename assignment relationship/cache/helper to farms/assignedFarmIds, preserve persistence behavior. |
| app/Models/Farmer/User.php | Remove unsupported teams join and point UsePolicy at the dedicated domain policy. |
| app/Support/RolePermissions.php | Read the renamed farm_assignment_limits key. |
| config/access.php | Assignment-limit identifier rename only; all 139 grants unchanged. |
| database/migrations/authorization/2026_10_26_000001_add_farm_authorization_relationships.php | Derive FK types from referenced keys and reject unsupported/missing keys before DDL; not applied. |
| pint.json | Keep explicit fully qualified identity types without formatter rewriting. |
| tests/Feature/Authorization/AuthorizationMigrationTypesTest.php | Offline Blueprint/PG SQL column-type, FK, uniqueness, and pre-DDL failure tests. |
| tests/Feature/Authorization/ModelAuthorizationTest.php | Identity-link denial, shared-record matrix, farm names, independent fixtures without migration execution. |
| tests/Feature/Settings/SecurityTest.php | Run two previously skipped password-settings tests against the actual UI response. |
| tests/Feature/Auth/AuthenticationTest.php | Explain disabled-2FA challenge skip in the test. |
| tests/Feature/Database/FarmerIcMigrationsTest.php | Explain disposable-database prerequisite and forbid using farmer_ic_dev in skip reason. |
| docs/authorization-scaffolding.md | Current identity, naming, migration, shared-record rules, selected integration, and verification limits. |
| docs/authorization-review.md | Findings, all grants, schema options/inventory, model inventory, skip audit, and results. |

## Final verification

- Initial full suite: 165 passed, 4 skipped, 2,422 assertions.
- Final `php artisan test`: **173 passed, 2 skipped, 2,550 assertions** (175 total).
- PHPStan: passed, zero errors.
- Pint on affected PHP files: passed using the Laravel preset with explicit
  fully qualified identity-type preservation.
- `npm.cmd run build`: passed. Existing optional-fontaine and chunk-size warnings;
  generated frontend diffs were removed.
- Protected-file hashes confirm this review changed **no route, controller,
  or isolated-baseline migration file**. No production feature flags, model
  business behavior, local schema, live data, or .env values were changed.
- Read-only metadata checks verified the live UUID PostgreSQL keys and legacy
  SQLite integer keys. No authenticated-user data was read.
- Integration remains open; no native-auth/domain-PostgreSQL runtime verification
  or browser verification is claimed. Tests use SQLite fixtures or offline
  PostgreSQL SQL compilation, not the completed identity integration.
