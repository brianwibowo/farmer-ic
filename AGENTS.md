# AGENTS.md

## PROJECT MODE: FULLSTACK STAKEHOLDER PROTOTYPE

This repository is a Laravel 13 + Inertia.js + Vue 3 application used for a
clickable stakeholder demo. New product work may involve both frontend and
backend development. Extend the existing Laravel authentication, team,
controller, migration, and test scaffolding only as needed for prototype features.

The goal is a polished, believable interface backed by working Laravel
business logic and persistent database data. This remains a stakeholder
prototype, not an assumption that the application is production-ready.

## BACKEND AND DATABASE BOUNDARY

Implement new features using Laravel's Model–View–Controller architecture with
a Service layer (MVC + Service). Do not introduce a Repository layer or
Repository interfaces; use Eloquent models and query builders directly from
services where appropriate.

- Models in `app/Models/` represent persisted domain data and relationships.
- Controllers in `app/Http/Controllers/` handle requests, invoke authorization
  (Gate/Policy) before calling services, and return Inertia pages, redirects,
  or responses. Keep business rules out of controllers.
- Form Requests in `app/Http/Requests/` handle validation. They may check
  authorization only by delegating to the same Policy/Gate (never by
  re-implementing rules); do not rely on client-side validation alone.
- Services in `app/Services/` contain use-case/business logic, coordinate
  Eloquent persistence, and manage transactions when necessary. Services do not
  contain role or permission rules.
- Policies and Gates enforce authorization; authorization rules must not be
  buried in Vue components or scattered through controllers and services.
  See STATIC RBAC AND AUTHORIZATION below.
- Migrations, seeders, models, controllers, routes, and backend tests may be
  added or changed as needed for the prototype. Keep changes focused.
- Use Laravel routes and Inertia `<Link>` navigation. Server actions should
  follow Laravel's normal request/response and CSRF protection conventions.
- Persist domain data in the database. Do not use `localStorage`, bundled JSON,
  or frontend-only state as the source of truth for operational records.
- Frontend-only UI state (such as theme, open panels, temporary filters, or
  unsaved drafts) may remain in Vue state or browser storage.
- Seed data belongs in database seeders when it represents persistent domain
  records. Existing fixtures in `resources/js/data/` may be retained for
  explicitly frontend-only visual examples, not as live business data.
- Browser storage helpers belong in `resources/js/composables/`.
- Frontend service wrappers belong in `resources/js/services/` when useful;
  they call backend routes or encapsulate presentation-only behavior, rather
  than implementing authoritative business rules or reading operational data
  directly from `localStorage`.
- Do not install new Composer or npm packages without asking.

Keep the existing AI and RAG integration working. The current
`farmDemoData.json` dataset may be used as a migration/seed fixture, but must
not remain the authoritative source for farm records that have been migrated
to the database. Where AI/RAG requires indexed knowledge, derive or refresh
that knowledge from the same authoritative backend data (or clearly identified
reference documents). Do not silently return stale JSON-based answers when
users expect current database records. Preserve the existing AI workflow
unless a change is necessary for this transition.

The user can leave prototype mode only by explicitly writing **"exit prototype
mode"**. Until then, develop functional fullstack prototype features while
keeping data and integrations within the agreed demo scope.

## STATIC RBAC AND AUTHORIZATION

Use Laravel's native Gates and Policies, in their idiomatic form, with a small,
static role-permission matrix defined in `config/access.php`. Role names,
permission keys, and scopes are defined in code, not through dynamic
role/permission management tables or runtime permission editors. No permission
package (for example Spatie) is used.

### Source of truth

- The fixed roles are `owner`, `superadmin`, `admin`, and `petugas`.
- Define the permitted actions and their scopes in `config/access.php` under
  `roles`, for example `'harvest_details.view' => 'any'` or
  `'planting_cycles.update' => 'own'`. Absence of a role, a permission, or a
  valid scope means deny.
- `App\Support\RolePermissions` is the single reader of `config/access.php`
  (grant lookup, scope lookup, recognized-role validation, team-membership
  check, assignment limits). Nothing else reads the matrix directly.
- Permission prefixes come from the real models: one prefix per concrete model
  in `app/Models/` (snake_case plural, matching its table where one exists),
  for example `farms`, `farm_activities`, `planting_cycles`,
  `financial_transactions`, `harvest_details`, and `plant_observations`. Do not
  create a prefix for a model that does not exist, and do not leave a model
  without a prefix and policy.
- Model files may be owned by other developers. Limit edits there to
  authorization additions (relationships, the `visibleTo` concern,
  `#[UsePolicy]`). Do not rename, move, re-namespace, or restyle models or
  change their behavior without reporting it first.
- To change role permissions, edit `config/access.php` and the corresponding
  authorization tests. Do not add a dynamic admin UI to manage roles.

### Idiomatic structure (required)

- **One Policy per Eloquent model**, in `app/Policies/`, named `{Model}Policy`
  (for example `PlantingCycle` -> `PlantingCyclePolicy`) so Laravel
  auto-discovers it. Do not add `Gate::policy()` lines for models that follow
  the naming convention.
- **Do not create stand-in subject classes or catch-all policies.** No
  `ResourceAccess`, no `ResourceAccessPolicy`, and no policy registered against
  a non-model class. Do not invent placeholder models.
- **Share logic through inheritance.** The matrix-checking logic lives in the
  abstract `App\Policies\ResourcePolicy`. Each concrete policy declares only its
  resource prefix and how to resolve the trusted farm and team from the model
  through persisted relationships (for example `$plantingCycle->farm`).
- **Duplicate or projection models.** `App\Models\User` is the only
  authentication and authorization actor. A domain projection that shares a
  class name (for example `App\Models\Farmer\User`) is never an actor. It may
  be a policy target only through `#[UsePolicy]`, and the policy must resolve it
  through a persisted link to an `App\Models\User` and a team; with no
  provable link it denies. Type-hint the fully qualified class so actor and
  target cannot be confused.
- **Gates are only for non-resource decisions**, such as `ai.access` and
  composite navigation gates built from the model policies' `viewAny`. Define
  them in `AuthorizationServiceProvider`. Resolve services lazily inside the
  closure; do not inject them into `boot()`.
- A resource that truly has no model gets a named `Gate::define`, never a
  stand-in class.
- **No extra abstraction layers for assignments.** Do not create contracts,
  interfaces, adapters, or placeholder classes for reading a user's farm
  assignments. Policies read them from the `User` model's Eloquent relationship
  (see Scope semantics).
- **Provider layout:** `AuthorizationServiceProvider` (Gates only),
  `AiServiceProvider` (AI/RAG bindings), and `AppServiceProvider` (framework
  defaults only). Create providers with `php artisan make:provider` so they are
  added to `bootstrap/providers.php`.
- Authentication stays on the existing Laravel authentication flow and is kept
  separate from authorization code.

### Resources and abilities

Policy abilities are `viewAny`, `view`, `create`, `update`, `delete`, and
`configure`. A matrix key is `{prefix}.{ability}` (for example
`planting_cycles.update`); keys are consumed by the policies and are not
registered as individual Gates. Abilities without a grant deny.

Standard call shapes:

```php
Gate::authorize('update', $plantingCycle);                    // existing record
Gate::authorize('viewAny', [PlantingCycle::class, $team]);    // listing; needs a team
Gate::authorize('create', [PlantingCycle::class, $farm]);     // persisted parent
Gate::authorize('ai.access', $team);                          // non-resource Gate
```

`create` receives a persisted parent of the correct model type: a Team for a
Farm, a Farm for a PlantingCycle, a PlantingCycle for a PlantObservation, or a
FarmActivity for a HarvestDetail. A missing or unrelated parent denies. Follow
`docs/authorization-scaffolding.md` for the full model-to-prefix mapping and
keep it current.

### Scope semantics

- `any` means every record permitted by the team boundary: every farm in a team
  the user belongs to, including farms without an individual assignment. It
  never bypasses team membership.
- `own` means the target record belongs to a farm assigned to the user. It does
  not mean `created_by`, `owner_id`, the current team alone, or any
  browser-supplied ID. One explicit exception exists: notifications use the
  persisted recipient as a personal `own` scope. Any other non-farm `own` scope
  must be defined explicitly in `docs/authorization-scaffolding.md` and tested.
- Farm-backed records resolve their farm, and then their team, through trusted
  persisted relationships of the record itself. Never take farm, team, role, or
  user ID from request input as proof of authority.
- A record with no provable team boundary denies, even for `any` (for example a
  farm with no team or an unallocated financial transaction).
- Application-wide reference or system records with no team column (for example
  commodities, crop varieties, financial categories, activity types, roles, and
  audit logs) require an explicit grant plus membership in at least one team
  for single-record `view`; listings use `viewAny` with the team context. They
  never grant access to team-owned records. Document the exact rule per model.
- `create` requires an already resolved persisted parent. A grant alone cannot
  authorize creation in an unspecified farm; derive the farm from the parent
  instead of accepting a separate `farm_id` field.
- `update`, `delete`, and `configure` also require visibility of the target
  record. For example, `update = any` with `view = own` still prevents editing
  records outside the visible farm.
- `viewAny` only admits a listing. It does not grant visibility of every record
  and does not grant create.
- Farm assignments come from a persisted many-to-many relationship on the
  `User` model (`$user->farms()` backed by a `farm_user` pivot table with a
  unique `(user_id, farm_id)` constraint). The pivot's column types must match
  the real key types of the users and farms tables; verify them, do not assume.
  Policies and query scopes compare the record's resolved farm against that
  relationship, across all teams. Do not infer assignments from the starter
  team's membership-role field, `created_by`, or request input. Until the
  relationship and its migration exist, `own` checks deny (fail closed); `any`
  checks still work.
- Within one request, load the user's assigned farm IDs once (memoized on the
  `User` instance or eager loaded) and reuse them. Assignment workflows must
  validate the configured limit and clear or reload the relation after a
  change.
- `petugas` may have at most one assigned farm; deny if more than one distinct
  farm is assigned. `admin` may have several.
- Application roles are separate from the starter's team roles. Owning a team
  does not grant application-owner access.

### Schema prerequisite

- The domain tables (the isolated UUID PostgreSQL baseline in
  `database/migrations/farmer_ic`) and the running authentication tables
  (integer users and teams) are not integrated. Do not claim that authorization
  works on real domain data until they are, and do not choose an integration
  approach silently: report the options and ask.
- Do not modify or migrate the isolated baseline. Authorization migrations live
  in `database/migrations/authorization`, are not applied by default, and are
  never run against a non-demo database.

### Enforcement layers

1. **Controllers** call `Gate::authorize(...)` (or `$this->authorize(...)`)
   with the model or trusted parent record before invoking a service.
2. **Non-HTTP entry points** (queued jobs, commands, AI tools) have no
   controller. Use `Gate::forUser($actor)->authorize(...)` there before
   acting.
3. **Read queries** must be scoped, not just guarded. Every model exposes the
   `visibleTo(User $user, Team $team)` Eloquent scope, which reuses its
   policy's query constraints so record checks and list queries agree. Apply it
   before pagination, counts, totals, exports, serialization, and AI/RAG
   retrieval. A missing or invalid grant yields an empty result, never all
   rows.
4. **Inertia and Vue:** the backend computes booleans with `Gate::allows(...)`
   and passes them as props (for example `can: { update, delete }`). Vue only
   renders them. Hidden buttons and routes are UX, not security. Compute
   navigation gates once per request (shared props), not once per component.
5. **Routes:** use `auth`/`verified` middleware, and `can:` middleware only
   where the target is already a bound model or team (for example
   `can:ai.access,team`). Record-level scope checks happen in controllers
   through policies, not in route middleware, because middleware cannot safely
   resolve a farm from raw request data.
6. Return proper `403 Forbidden` for denied actions.

### Hard prohibitions

- No `Gate::before` and no superadmin bypass.
- No implicit role inheritance, wildcards, or "allow everything" overrides. A
  role gets only the permissions explicitly listed in the matrix.
- The `owner` role can view all aspects of Farm, Activity, Finance, and User
  data (and other read-only features defined in the matrix), but must not
  create, update, or delete operational data. Only `owner` can access AI/RAG
  features. Other roles must not gain `ai.access` through a wildcard override.
- `users.role` is never mass-assignable and is never copied from registration
  or profile input. Roles are set only by a trusted backend process that
  validates the string with `RolePermissions::isKnownRole()` before saving.
- Do not modify or migrate the isolated `database/migrations/farmer_ic`
  baseline.

### Tests and maintenance

- Add tests covering each role's allowed and denied actions per model policy,
  cross-team and cross-farm denial, the `own` scope, multiple admin
  assignments, the `petugas` single-farm limit, missing or invalid grants,
  default deny for undefined actions, and that a client-supplied `farm_id` or
  `team_id` cannot change a decision.
- Add HTTP tests asserting real 403 responses on protected routes, and tests
  that paginator totals, counts, and exports never include records outside the
  user's scope.
- Add a test that validates `config/access.php` (known roles, known
  `{prefix}.{ability}` keys, scopes only `any` or `own`).
- Fixture-based tests (for example SQLite) prove policy logic only. Label them
  as such, give every skipped test a documented reason, and add tests against
  the integrated schema once the domain and authentication tables are
  integrated.
- After deployment, rebuild cached configuration with
  `php artisan config:cache` if used, and restart long-running workers.
  Genuinely new ownership rules or abilities require code as well as grants.
- After each authorization task, report which routes and queries are still
  unprotected. Installing policies alone does not protect any route.

## DUMMY DATA RULES

- Seed realistic data in the database for fresh prototype installations; do not
  use placeholder text such as `test 123` or `lorem ipsum`.
- Prefix browser storage keys with `demo_` and include a version, for example
  `demo_v1_products`. Use these keys only for UI/demo preferences, not as the
  authoritative storage for operational records.
- Wrap browser storage reads and writes in `try/catch` and fall back to safe
  default UI state; load persistent domain data from the backend instead.
- Add a visible **Reset demo data** action that clears demo browser keys and
  restores the demo database seed state through an explicitly authorized,
  prototype-only backend action. Require confirmation and never reset
  non-demo/production data.
- Show loading states for backend requests. For any remaining mock-only
  frontend service, simulate 300 to 600 ms service delays and show loading
  states; do not artificially delay real backend operations.
- Use the existing Laravel authentication for user login and RBAC. Do not
  replace it with a browser-only fake login.

## REPOSITORY CONVENTIONS

- Vue 3 with `<script setup>` and the Composition API. Do not use the Options
  API.
- Pages go in `resources/js/pages/` and must match the page name passed to
  `Inertia::render()`.
- Reusable UI belongs in `resources/js/components/`; layouts belong in
  `resources/js/layouts/`.
- Keep components small and readable.
- Use the existing Tailwind, Vue, Inertia, and UI primitives. Do not install a
  new npm package without asking.
- Before any frontend UI work, read and follow the project design guidance in
  [`design.md`](design.md), including its tokens, typography, component
  patterns, and mobile layout conventions.
- Use the existing semantic theme utilities for all UI colors, such as
  `text-primary`, `text-foreground`, `text-muted-foreground`, `bg-card`,
  `bg-muted`, `border-border`, `text-success`, `text-warning`, and
  `text-error`. Do not hardcode hex, RGB, or RGBA colors in page or component
  classes. If a needed color, shadow, and related does not exist, then a
  hardcoding of style is allowed/necessary.
- Ensure light mode is default mode, ignoring system dark mode
- Check every new screen in both light and dark themes. Avoid fixed widths;
  support small, medium, and large phone viewports, including standard,
  Pro/Plus, and Pro Max iPhone sizes.
- Do not edit `.env` or commit changes.

## WIREFRAME-TO-VUE WORKFLOW

Treat an attached wireframe or screenshot as the source of truth. Inspect the
whole image before coding and identify every section, label, control, field,
state, and annotation.

1. Map each screen to a page in `resources/js/pages/`.
2. Extract repeated or distinct UI into components in
   `resources/js/components/`.
3. Match the wireframe's order, hierarchy, grouping, and visible content.
4. Use the existing project design language; do not invent a new UI system.
5. Implement annotated navigation, tabs, filters, dropdowns, modals, sorting,
   and forms with frontend state; persist submitted domain data through the
   appropriate Laravel controller and service.
6. Fill lists, tables, cards, charts, and profiles with realistic
   database-seeded prototype data returned by the backend.
7. Use local placeholder assets instead of hot-linked images.
8. Make the layout responsive for desktop and mobile.
9. Use semantic HTML: real buttons, links, labels, inputs, headings, and
   tables.

When a wireframe is unreadable or ambiguous, ask before building. For small
visual details such as spacing or icon choice, make a reasonable assumption
and report it afterward. If only an inaccessible Figma link is provided, ask
for a screenshot or export.

## UI QUALITY REQUIREMENTS

Every screen should provide:

- a loading state;
- an empty state;
- success or error feedback after actions;
- client-side validation for forms;
- working desktop and mobile layouts; and
- no dead buttons, links, menus, console errors, or warnings.

After each screen, report the pages and components changed, assumptions made,
and any wireframe content intentionally skipped.

## VERIFICATION

Install dependencies with `composer install` and `npm install` when needed.
Run the frontend build after changes:

```text
npm run build
```

On Windows PowerShell, use `npm.cmd run build` if the PowerShell execution
policy blocks `npm.ps1`. Report whether the build passed and mention any
environment limitation. For backend changes, run relevant Laravel tests
(including authorization and service behavior) and report their results.
Verify frontend UI, database persistence, validation, and the frontend/backend
integration as appropriate. Do not run destructive migrations or demo-data
resets against a non-demo database.