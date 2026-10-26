# Static authorization scaffolding

The grant source is `config/access.php`. It contains the requested four roles,
exact permission matrix, and the single-kebun assignment limit for `petugas`.
Use Laravel's native Gate and Policies; no permission package or permission
management tables are involved. Missing roles, grants, and invalid scopes deny.

## What is installed

- `RolePermissions` reads grants and checks the existing team boundary.
- `ResourceAccessPolicy` handles the eleven resource prefixes in the matrix
  through a `ResourceAccess` policy subject. This avoids inventing domain models
  or registering policies against model classes that do not exist yet.
- `ai.access` is a named Gate requiring `any` plus membership in the target team.
- A nullable `users.role` migration supports the currently running starter
  schema. No role is assigned automatically, and `role` remains outside public
  mass assignment. Application roles and the starter's `TeamRole` are separate;
  owning a team does not grant application-owner access.
- `KebunAssignments` defines the future persisted assignment integration.
  Its default adapter returns no assignments, so `own` record checks deny until
  that adapter is replaced. It is intentionally not a repository layer.

No application controller, service, Form Request, route, Inertia props, Vue
component, or existing team policy has been changed to enforce these checks.
**Existing routes, including AI routes, do not gain RBAC protection merely from
installing this scaffolding.** Wiring enforcement and read-query filtering is
the next implementation phase.

## Scope semantics

`any` means every kebun in a team the user belongs to, including kebun without
an individual assignment. It never bypasses team membership. `owner` and
`superadmin` still need membership in each intended team. No role hierarchy,
wildcard, or `Gate::before` bypass exists.

`own` means the target record belongs to an assigned kebun. It does not mean
`created_by`, `owner_id`, the current team alone, or browser-supplied IDs.
The eventual assignment adapter must return all of the user's assigned kebun
IDs across teams, as globally unique strings. Admins may have multiple
assignments; petugas access denies if more than one distinct kebun is assigned.
Persistence-level assignment validation is deferred to that future workflow.

For lahan, activities, plantings, progress, harvests, and finance, resolve the
parent kebun through trusted persisted relationships. Resolve the team from
the same record/parent. An `own` creation requires an already resolved target
kebun; a grant alone cannot authorize creation in an unspecified kebun.

Updates, deletes, and form configuration also require visibility of the target
record. For example, `update = any` combined with `view = own` still prevents
editing records outside the visible kebun.

`viewAny` only admits a listing. It grants neither visibility of every record
nor permission to create. Future services must apply the team filter and, for
`own`, the assignment filter **before** pagination, counts, totals, exports,
and serialization. The current scaffolding does not implement read queries.

## Future usage

These examples show call sites for later; they are not installed endpoints.

```php
use App\Support\Access\ResourceAccess;
use Illuminate\Support\Facades\Gate;

// Resolve $team and $kebunId from the persisted record, never raw request scope.
$subject = new ResourceAccess('plantings', $team, (string) $kebunId);
Gate::authorize('view', $subject);
Gate::authorize('create', $subject); // target kebun for a new planting
Gate::authorize('update', $subject);

// Non-resource capability; the team argument is required to allow access.
Gate::authorize('ai.access', $team);
```

The supported resource prefixes are `kebun`, `lahan`, `activities`, `plantings`,
`plantings.progress`, `harvests`, `finance.sales`, `finance.purchases`, `users`,
`sops`, and `forms`. Use policy abilities `viewAny`, `view`, `create`, `update`,
`delete`, and `configure`. Matrix keys such as `plantings.update` are consumed
by the policy; they are not independently registered named Gates.

When real domain models are integrated, their model-specific policies may
delegate to the shared policy after resolving trusted resource context.
Replace the `KebunAssignments` binding in `AppServiceProvider` with an adapter
that reads authoritative persisted user-to-kebun relationships. Do not infer
kebun assignments from the starter team's membership-role field.

## Database and deployment

The separate PostgreSQL baseline in `database/migrations/farmer_ic` has UUID
users with `role_id`, a roles table, and no admin-to-many-kebun assignment
relationship. It is not used by the running Eloquent user model. This change
does not modify or migrate that isolated baseline. A later schema integration
must reconcile it with the static string role and assignment design; do not
run the default migrations against that baseline database.

For the current starter schema, review and apply the new nullable role
migration through the normal migration process. Existing accounts remain
unassigned. Deliberately set a validated role in a trusted backend process;
never copy it from ordinary registration/profile input. No backfill, seeder,
role editor, or migration against the working database was performed here.

Permission edits require only `config/access.php`, plus corresponding test
expectations. After deployment, rebuild cached configuration with
`php artisan config:cache` if configuration caching is used, and restart any
long-running application workers. New ownership predicates or entirely new
policy actions require code as well as grants.

## Verification

`tests/Feature/Authorization/StaticAccessTest.php` exercises the real Gate and
registered policy with persisted SQLite users/team memberships and a test-only
assignment adapter. It covers the role matrix, cross-team and cross-kebun
denials, multiple admin assignments, the petugas limit, missing/invalid grants,
mass-assignment protection, guest denial, config changes, and actual HTTP 403
behavior on a test-only route. It does not claim that application routes are
enforcing RBAC yet or that a persisted kebun assignment implementation exists.

```powershell
php artisan test --filter=StaticAccessTest
```
