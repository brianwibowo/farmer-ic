# Authentication and RBAC guide

This guide is the implementation checkpoint for the current Laravel 13
authorization scaffolding. It explains where authentication ends, where RBAC
starts, and the exact call shape for each layer. The authoritative role matrix
is [`config/access.php`](../config/access.php); this document describes how
code consumes it.

## The request flow

1. Laravel authenticates the request with the existing `auth` middleware. The
   authenticated actor is always `App\Models\User`.
2. A route reaches a controller only after authentication. A route may use
   `can:` when its target is already a bound model or team, but record scope is
   still checked in the controller policy call.
3. A Form Request delegates authorization to the same Gate or Policy before
   validation and service execution. It must not duplicate the matrix.
4. The controller calls `Gate::authorize(...)` with a persisted model or trusted
   parent, then invokes the service.
5. Services contain persistence and business transactions. They do not inspect
   roles or read `config/access.php` directly.
6. List and export queries apply the model's `visibleTo(User $user, Team $team)`
   scope before pagination, counts, totals, serialization, or AI retrieval.
7. A denied request returns HTTP 403. An unauthenticated request is handled by
   Laravel's authentication middleware.

The current protected HTTP slices are Farm CRUD and Daily Activity Logs. Other
controllers and frontend demo services remain outside this scaffolding
checkpoint.

## Authentication files

- `app/Models/User.php` is the only authentication actor. It resolves the
  application role through `role_id`, the current team through `current_team_id`,
  memberships through `teams()`, and the petugas assignment through the direct
  nullable `assigned_farm_id` → `assignedFarm()` relation.
- `app/Concerns/HasTeams.php` provides persisted team membership checks. A team
  membership is not an application role.
- `routes/web.php` and `routes/api.php` apply Laravel's `auth` middleware.
- `app/Http/Requests/` contains request-level delegation to Gates/Policies.

Do not accept `role`, `team_id`, `farm_id`, or `created_by` from the browser as
proof of authority. Derive the actor, team, and farm from the authenticated user
and persisted model relationships.

## Authorization files

- `config/access.php` contains explicit `{prefix}.{ability} => scope` grants.
  A missing grant or invalid scope denies access.
- `app/Support/RolePermissions.php` is the only reader of that matrix. Use it
  for grant lookup, recognized roles, team membership limits, and assignment
  limits. Do not call `config('access.roles')` elsewhere.
- `app/Policies/ResourcePolicy.php` contains shared record, parent, team, farm,
  and visibility logic.
- `app/Policies/{Model}Policy.php` declares a concrete model's prefix and its
  trusted relationship path. Laravel discovers these policies by model name.
- `app/Providers/AuthorizationServiceProvider.php` defines non-resource Gates.
  `ai.access` is a named Gate. `Gate::before` returns `true` only for the
  reserved developer `superadmin` role; there is no detailed superadmin grant
  list in `config/access.php`.
- Models using the policy query contract expose `visibleTo()` through
  `app/Concerns/HasVisibleScope.php`.

## Role and scope semantics

`any` means every record inside a team the actor belongs to. It does not skip
team membership. `own` means a farm-backed record belongs to the actor's one
assigned farm (`users.assigned_farm_id`). Notifications are the only personal
`own` exception. A record without a trusted farm/team boundary is denied.

The application roles are independent from the membership role stored in
`team_members.role`:

- `owner` has read-only matrix grants and `ai.access` plus the AI conversation,
  message, attachment, and knowledge operations explicitly listed in config.
- `admin` has team-wide CRUD grants where listed.
- `petugas` has farm-assigned operational grants with `own` scope.
- `superadmin` is the developer role and is handled only by
  `AuthorizationServiceProvider::boot()` through `Gate::before`.

## How to use Policies and Gates

### Existing record

```php
use Illuminate\Support\Facades\Gate;

Gate::authorize('view', $farm);
Gate::authorize('update', $plantingCycle);
Gate::authorize('delete', $financialTransaction);
```

The model's persisted relationships resolve its farm and team. Never pass a
browser-supplied farm or team ID as the authority argument.

### Listing a team resource

```php
Gate::authorize('viewAny', [Farm::class, $team]);

$farms = Farm::visibleTo($request->user(), $team)
    ->latest()
    ->paginate();
```

`viewAny` admits a listing; `visibleTo` decides which rows are returned.

### Creating a child record

```php
Gate::authorize('create', [PlantingCycle::class, $farm]);

$cycle = $plantingCycles->create($validated, $farm);
```

The parent must be persisted and must be the correct model type. Do not accept a
separate `farm_id` and treat it as proof of access.

### Non-resource AI access

```php
Gate::authorize('ai.access', $team);
```

For commands, jobs, or tools without an HTTP request, carry an explicit native
`App\Models\User` actor and use `Gate::forUser($actor)->authorize(...)` before
work begins. Re-check authorization inside queued work instead of trusting the
dispatching request forever.

## How frontend code consumes authorization

Vue is never the security boundary. Controllers or shared Inertia props compute
booleans once per request:

```php
return Inertia::render('Farms/Show', [
    'farm' => new FarmResource($farm),
    'can' => [
        'update' => Gate::allows('update', $farm),
        'delete' => Gate::allows('delete', $farm),
    ],
]);
```

The page uses those booleans only for presentation:

```vue
<Link v-if="can.update" :href="route('farms.edit', farm.id)">
  Edit farm
</Link>
```

The server must still authorize the update route. Do not put role names,
permission matrices, or ownership decisions in Vue components. Frontend service
wrappers call backend routes; they must not read operational records from
`localStorage` or decide whether a user may act.

## Changing a grant safely

1. Edit only `config/access.php`.
2. Keep the prefix tied to a real model and use only `any` or `own`.
3. Add or update the corresponding policy and authorization tests when the
   ability is new.
4. Run `php artisan config:cache` in deployments that cache configuration and
   restart long-running workers.

Adding a new ownership rule requires code in the policy and query scope as well
as the matrix entry. A config grant alone cannot create a missing relationship.

## Verification commands

```powershell
php artisan test --compact
vendor\bin\phpstan analyse --memory-limit=1G
vendor\bin\pint --test
```

The PostgreSQL integration and migration tests are the authoritative checks for
the current UUID users, integer teams, `farms.team_id`, and
`users.assigned_farm_id` relationships. Do not run destructive migrations
against a non-demo database.
