## 6. What changes in a fresh Laravel 13 application

Laravel **13.36.0 is a published framework release**. This checkout is Laravel 12, so the implementation below is a proposed design for your target project, not a patch to Snipe-IT. See the [13.36.0 release](https://github.com/laravel/framework/releases/tag/v13.36.0).

| Concern | This Snipe-IT checkout | Fresh Laravel 13 target |
| --- | --- | --- |
| Application bootstrap | Explicit kernel bindings | `Application::configure(...)` in `bootstrap/app.php`. |
| App provider list | `config/app.php` | `bootstrap/providers.php`. |
| Middleware customization | `app/Http/Kernel.php` | `withMiddleware(...)` in `bootstrap/app.php`. |
| Policy registration | Dedicated auth provider `$policies` | Convention discovery or explicit `Gate::policy()` in `AppServiceProvider::boot()`. |
| Exception customization | `app/Exceptions/Handler.php` | `withExceptions(...)` in `bootstrap/app.php`. |
| UI rendering | Blade, Bootstrap tables, some Livewire | `Inertia::render()` with Vue page components. |
| Permission source | User/group JSON in the database | `config/access.php` plus a trusted user role assignment. |
| Browser authentication | Session plus Passport integration | Usually the existing session-based `web` authentication flow. |

An upgraded Laravel 13 application may retain an older structure. Inspect your target project before choosing registration locations. Do not create a kernel or auth provider just because Snipe-IT has one. The [Laravel 13 skeleton](https://raw.githubusercontent.com/laravel/laravel/13.x/bootstrap/app.php), [providers guide](https://laravel.com/docs/13.x/providers), and [middleware guide](https://laravel.com/docs/13.x/middleware) show the fresh-project arrangement.

Your Inertia pages can live behind ordinary `web` routes with session cookies and CSRF protection. A separate token API, Passport, and Spatie are not prerequisites for this design. The framework's [request lifecycle](https://laravel.com/docs/13.x/lifecycle) supplies the bootstrap background.

## 7. Implement static roles step by step

### Assumptions and example domain

The following code belongs in **your target application**, not this checkout. It is a coherent reference implementation, not a claim that your future application already contains these files.

- Existing authentication and Inertia/Vue 3 setup already work.
- One role per user: `viewer`, `editor`, or `admin`.
- Example `assets` table: `id`, `user_id`, `name`, timestamps; `user_id` is a required owner foreign key.
- No tenancy or company rules in this example. Add tenant boundaries before using it in a multitenant app.
- `own` means the record's persisted `user_id` matches the authenticated user's ID.
- `any` means all records **within this application's intended data boundary**.
- Missing roles, permissions, or invalid scope values deny access.
- Admin has an explicit grant list; there is no global superuser bypass.

The owner predicate is application code. The role grants and choice between `own` and `any` live in one file. Changing a supported role's grants needs only that file; adding a fundamentally new rule such as “only during business hours” requires implementing that predicate and its tests too.

### Step 1: inspect the target and scaffold only what is missing

Run these in the target project, not in Snipe-IT:

```powershell
composer show laravel/framework
composer show inertiajs/inertia-laravel
Get-Content package.json
php artisan make:class Support/RolePermissions --no-interaction
php artisan make:policy AssetPolicy --model=Asset --no-interaction
php artisan make:controller AssetController --no-interaction
php artisan make:request StoreAssetRequest --no-interaction
php artisan make:request UpdateAssetRequest --no-interaction
php artisan make:migration add_role_to_users_table --table=users --no-interaction
```

Inspect generated files before replacing their contents. Create an Asset model/table/factory only if your project needs this example domain; otherwise adapt the recipe to your real resource. Do not change dependencies merely to follow the example.

### Step 2: put role grants in `config/access.php`

```php
<?php

return [
    'default_role' => 'viewer',

    'roles' => [
        'viewer' => [
            'assets.view' => 'own',
        ],
        'editor' => [
            'assets.view' => 'own',
            'assets.create' => 'any',
            'assets.update' => 'own',
        ],
        'admin' => [
            'assets.view' => 'any',
            'assets.create' => 'any',
            'assets.update' => 'any',
            'assets.delete' => 'any',
            'reports.view' => 'any',
        ],
    ],
];
```

For example, granting editors access to every asset changes `assets.view` to `any` for that role. Giving editors delete access to their own assets adds `assets.delete => own`.

Do not duplicate these grants in Vue or scatter `role === 'admin'` checks across controllers. There is no implicit role hierarchy and no wildcard grant.

### Step 3: store and assign the user's role safely

In the generated migration, add a nullable string column in `up()` and drop it in `down()`:

```php
// Inside up():
Schema::table('users', function (Blueprint $table): void {
    $table->string('role', 32)->nullable();
});

// Inside down():
Schema::table('users', function (Blueprint $table): void {
    $table->dropColumn('role');
});
```

These are method-body fragments; retain the migration class and `Schema`/`Blueprint` imports generated by Artisan.

Keep `role` out of ordinary profile/registration `$fillable` input. In your existing server-side user creation flow, assign it explicitly before saving:

```php
$user->role = config('access.default_role');
```

Do this regardless of whether the browser submits a `role` field. Only validated ordinary profile fields should be copied from public registration. This is a server decision, not a select box on a public form.

Existing accounts remain denied until deliberately assigned a recognized role. Plan a controlled backfill and initial-admin assignment through a reviewed seeder or trusted administrative process. A missing role must not silently become admin. Validate the configured default against the configured role keys in tests.

Role assignment is a privileged workflow of its own. If you later build a role editor, give it an explicit policy/Gate, an allowed-role list from this config, audit logging, and rules for self-changes and the last administrator. Do not add that workflow just to implement static permission definitions.

### Step 4: create a small, fail-closed resolver

`app/Support/RolePermissions.php`:

```php
<?php

namespace App\Support;

use App\Models\User;

final class RolePermissions
{
    public function scope(User $user, string $permission): ?string
    {
        if (! is_string($user->role) || $user->role === '') {
            return null;
        }

        $roles = config('access.roles', []);
        $scope = $roles[$user->role][$permission] ?? null;

        return in_array($scope, ['own', 'any'], true) ? $scope : null;
    }
}
```

The role is a plain string in this example. If your target already casts it to an enum, adapt the resolver explicitly. Do not maintain a second independent enum list unless you deliberately want to coordinate both sources.

No container binding is necessary for this concrete, dependency-free class. Laravel can resolve it when constructing the policy/controller/provider.

### Step 5: make the policy the resource decision point

`app/Policies/AssetPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;
use App\Support\RolePermissions;

final class AssetPolicy
{
    public function __construct(private RolePermissions $permissions) {}

    public function viewAny(User $user): bool
    {
        return $this->permissions->scope($user, 'assets.view') !== null;
    }

    public function view(User $user, Asset $asset): bool
    {
        return $this->allowsRecord($user, $asset, 'assets.view');
    }

    public function create(User $user): bool
    {
        return $this->permissions->scope($user, 'assets.create') === 'any';
    }

    public function update(User $user, Asset $asset): bool
    {
        return $this->view($user, $asset)
            && $this->allowsRecord($user, $asset, 'assets.update');
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $this->view($user, $asset)
            && $this->allowsRecord($user, $asset, 'assets.delete');
    }

    private function allowsRecord(User $user, Asset $asset, string $permission): bool
    {
        return match ($this->permissions->scope($user, $permission)) {
            'any' => true,
            'own' => $asset->user_id !== null
                && (string) $asset->user_id === (string) $user->getAuthIdentifier(),
            default => false,
        };
    }
}
```

This example requires read access as well as the mutation grant for update/delete. Consequently, giving `assets.update = any` while leaving `assets.view = own` does not permit editing someone else's asset. That composition is intentional and must be tested.

`viewAny` admits the user to the listing; it does not mean every row may be returned. `create` has no existing record to own, so this implementation accepts only `any` for that permission. The controller assigns the owner when creating the record.

### Step 6: register the policy and general Gates at boot

Merge the following into your existing `app/Providers/AppServiceProvider.php`; preserve its other registrations:

```php
<?php

namespace App\Providers;

use App\Models\Asset;
use App\Models\User;
use App\Policies\AssetPolicy;
use App\Support\RolePermissions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(RolePermissions $permissions): void
    {
        Gate::policy(Asset::class, AssetPolicy::class);

        Gate::define('reports.view', function (User $user) use ($permissions): bool {
            return $permissions->scope($user, 'reports.view') === 'any';
        });
    }
}
```

Confirm `AppServiceProvider` is listed in `bootstrap/providers.php`. Convention-based policy discovery is also supported, but this recipe uses an explicit mapping so the registration is visible to a learner. Do not add a second custom auth provider for the same purpose. See [Laravel policy registration](https://laravel.com/docs/13.x/authorization#registering-policies).

There is deliberately no `Gate::before(fn (...) => true)` for admin. Explicit grants keep newly introduced capabilities denied until the file grants them and prevent bypassing later ownership/state predicates.

In this design, `assets.update` is a configuration key consumed by `AssetPolicy`; it is not registered as a standalone Gate. Use `can('update', $asset)` for the complete resource decision. `reports.view`, in contrast, is explicitly registered as a named Gate and can be checked without a model.

### Step 7: register authenticated web routes

Merge into `routes/web.php`:

```php
<?php

use App\Http\Controllers\AssetController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::resource('assets', AssetController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy']);
});
```

This example places creation and editing forms on the index/show pages, so it does not register separate `create`/`edit` pages. If your UI needs those pages, add both their routes and their `create`/`update` policy checks.

`Route::resource()` creates route conventions; it does not install all policy checks. `{asset}` must match the controller parameter and Form Request lookup.

For a non-resource route, Laravel's built-in `can` middleware can enforce a named Gate:

```php
// Alternative example, after your ReportsController::index exists:
Route::get('/reports', [ReportsController::class, 'index'])
    ->middleware(['auth', 'can:reports.view'])
    ->name('reports.index');
```

Import the real ReportsController if you implement that route. For record checks, `can:update,asset` or `->can('update', 'asset')` dispatches to the policy with the bound model. The baseline below instead uses Form Requests for write authorization and explicit Gate calls for reads/deletion. Pick clear enforcement points; do not duplicate the entire rule in middleware.

### Step 8: authorize and validate mutations in Form Requests

`app/Http/Requests/StoreAssetRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;

final class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Asset::class) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }
}
```

`app/Http/Requests/UpdateAssetRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $asset = $this->route('asset');

        return $asset instanceof Asset
            && ($this->user()?->can('update', $asset) ?? false);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255']];
    }
}
```

Only `name` is accepted for these operations. `role`, `user_id`, and tenant identifiers must never be copied from `$request->all()`. Laravel resolves these Form Requests before entering the action; a denied authorization prevents the action from executing. See [Form Request authorization](https://laravel.com/docs/13.x/validation#authorizing-form-requests).

`prepareForValidation()` can run before `authorize()`. Keep preparation limited to input normalization; do not perform database mutations or other privileged side effects there.

### Step 9: enforce reads, filter rows, and return capability props

`app/Http/Controllers/AssetController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\UpdateAssetRequest;
use App\Models\Asset;
use App\Support\RolePermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class AssetController extends Controller
{
    public function index(Request $request, RolePermissions $permissions): Response
    {
        Gate::authorize('viewAny', Asset::class);

        $query = Asset::query()->select(['id', 'user_id', 'name']);
        $scope = $permissions->scope($request->user(), 'assets.view');

        if ($scope === 'own') {
            $query->where('user_id', $request->user()->getAuthIdentifier());
        } elseif ($scope !== 'any') {
            abort(403);
        }

        $assets = $query->orderBy('id')->paginate(15)->through(
            fn (Asset $asset): array => [
                'id' => $asset->id,
                'name' => $asset->name,
                'urls' => [
                    'show' => route('assets.show', $asset),
                    'update' => route('assets.update', $asset),
                    'destroy' => route('assets.destroy', $asset),
                ],
                'can' => [
                    'update' => Gate::allows('update', $asset),
                    'delete' => Gate::allows('delete', $asset),
                ],
            ],
        );

        return Inertia::render('Assets/Index', [
            'assets' => $assets,
            'storeUrl' => route('assets.store'),
            'can' => ['create' => Gate::allows('create', Asset::class)],
        ]);
    }

    public function show(Asset $asset): Response
    {
        Gate::authorize('view', $asset);

        return Inertia::render('Assets/Show', [
            'asset' => [
                'id' => $asset->id,
                'name' => $asset->name,
            ],
            'urls' => [
                'update' => route('assets.update', $asset),
                'destroy' => route('assets.destroy', $asset),
            ],
            'can' => [
                'update' => Gate::allows('update', $asset),
                'delete' => Gate::allows('delete', $asset),
            ],
        ]);
    }

    public function store(StoreAssetRequest $request): RedirectResponse
    {
        $asset = new Asset;
        $asset->name = $request->validated('name');
        $asset->user_id = $request->user()->getAuthIdentifier();
        $asset->save();

        return redirect()->route('assets.show', $asset);
    }

    public function update(UpdateAssetRequest $request, Asset $asset): RedirectResponse
    {
        $asset->name = $request->validated('name');
        $asset->save();

        return redirect()->route('assets.show', $asset);
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        Gate::authorize('delete', $asset);
        $asset->delete();

        return redirect()->route('assets.index');
    }
}
```

The model in this example is an ordinary Eloquent Asset, not Snipe-IT's self-validating Asset. Adapt save failure handling to your actual model conventions. The target Asset needs the expected table/columns; tests using `Asset::factory()` also need its factory and `HasFactory`.

Why these details matter:

- The `where` runs **before pagination**, so row counts and pagination metadata obey visibility.
- Including `user_id` in the selected database columns lets ownership policies run. It is omitted from the serialized props.
- The server computes `can` with the same policy as the write endpoint.
- Mutations rely on the injected Form Request's authorization; `destroy` authorizes explicitly.
- Explicit props avoid accidentally exposing hidden business data or loaded relationships.
- Named route URLs are generated by Laravel; the Vue examples do not require Ziggy.

If several controllers, exports, or jobs need the same visibility query, extract it to a shared query scope/service and reuse it everywhere. Never paginate everything and merely hide unauthorized rows in Vue. Eloquent's [mass-assignment documentation](https://laravel.com/docs/13.x/eloquent#mass-assignment) explains another reason to avoid passing arbitrary request arrays into models.

### Step 10: share only global navigation capabilities

Add the following shape inside your existing `HandleInertiaRequests::share()` return array, preserving `parent::share($request)` and existing auth fields. If `auth` already exists, merge into it rather than overwrite it:

```php
'auth' => [
    'user' => fn (): ?array => $request->user() ? [
        'id' => $request->user()->id,
        'name' => $request->user()->name,
    ] : null,
    'can' => fn (): array => [
        'viewAssets' => $request->user()?->can('viewAny', Asset::class) ?? false,
        'viewReports' => $request->user()?->can('reports.view') ?? false,
    ],
],
```

Import `App\Models\Asset`. This is an array fragment, not a complete middleware file. Verify that your existing `HandleInertiaRequests` is registered in the `web` group via `bootstrap/app.php`; do not append it a second time.

Global capabilities belong in shared props; record-specific decisions belong beside that record in page props. Do not share the entire role matrix or sensitive user attributes. See [Inertia shared data](https://inertiajs.com/docs/v3/data-props/shared-data).

### Step 11: use server decisions in Vue

The configured page resolver determines whether your project uses `resources/js/pages` or `resources/js/Pages`. Follow the existing convention; the examples below assume lowercase `pages`.

`resources/js/pages/Assets/Show.vue`:

```vue
<script setup>
import { router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    asset: { type: Object, required: true },
    urls: { type: Object, required: true },
    can: { type: Object, required: true },
});

const form = useForm({ name: props.asset.name });

function save() {
    form.put(props.urls.update);
}

function remove() {
    router.delete(props.urls.destroy);
}
</script>

<template>
    <h1>{{ asset.name }}</h1>

    <form v-if="can.update" @submit.prevent="save">
        <label for="asset-name">Name</label>
        <input id="asset-name" v-model="form.name" />
        <p v-if="form.errors.name">{{ form.errors.name }}</p>
        <button :disabled="form.processing" type="submit">Save</button>
    </form>

    <button v-if="can.delete" type="button" @click="remove">Delete</button>
</template>
```

Adapt labels to your target's translation system and add its usual deletion confirmation. For `Assets/Index.vue`, consume `assets.data` and paginator links, show the creation form only when `can.create`, and send its name to `storeUrl` with `useForm().post(...)`. Each row's `can.update`/`can.delete` controls that row's buttons.

A shared navigation component can read:

```vue
<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const canViewAssets = computed(() => page.props.auth?.can?.viewAssets ?? false);
</script>

<template>
    <span v-if="canViewAssets">Assets navigation is available</span>
</template>
```

Use your existing link component/route props for real navigation. Add the page components named in `Inertia::render()` before expecting their routes to render successfully. No frontend role comparison is needed. See [Inertia authorization](https://inertiajs.com/docs/v3/security/authorization).

### Step 12: preserve denial responses and deploy configuration changes

A denied authenticated operation should remain a **403**, even when displayed through a Vue error page. Missing models normally produce **404**; an existing foreign-owned record in this example produces **403** after its policy check. If you require existence hiding, deliberately use scoped binding or a deny-as-not-found response and update tests accordingly.

Guests on these authenticated web routes normally redirect to login; JSON authentication failures normally use 401. Do not conflate an expired login, invalid input, and a permission denial. Inertia form validation commonly returns errors through redirects rather than a conventional API 422 response.

For production, adapt your project's exception handling so 403/404 responses render the appropriate Inertia error component while retaining the status. Keep ordinary JSON API error responses as JSON. Follow the [Inertia error-handling guide](https://inertiajs.com/docs/v3/advanced/error-handling) for the adapter version you actually install.

When changing `config/access.php`:

```powershell
# Local development, if stale cached configuration is present:
php artisan config:clear --no-interaction

# Deployment, once the new code/config has been released:
php artisan config:cache --no-interaction
```

Reload long-running workers/application servers through the deployment's established process. Capability props already displayed in an open browser tab can be stale until refreshed; the next backend request must still check current permissions. No permission seeder is needed in the native design. See [Laravel configuration caching](https://laravel.com/docs/13.x/configuration#configuration-caching).

## 8. Testing and acceptance criteria

Test requests as well as individual policy decisions. A passing policy test cannot detect a controller that forgot to invoke authorization. Keep this work in your target project's existing test framework and follow its testing skill/conventions before writing tests.

| Scenario | Expected result in this example |
| --- | --- |
| Guest opens asset list | Redirect to login. |
| Unknown/null role opens list | 403. |
| Viewer opens list | Only own assets and scoped pagination totals. |
| Viewer opens own asset | 200 with update/delete flags false. |
| Viewer opens another user's asset | 403. |
| Viewer submits create/update/delete directly | 403; database unchanged. |
| Editor creates asset | Owner assigned from authenticated user. |
| Editor updates own asset | Success. |
| Editor updates another user's asset | 403; database unchanged. |
| Editor deletes any asset | 403. |
| Admin lists/views/updates/deletes | Allowed according to the explicit matrix. |
| Permission absent or scope misspelled | Deny. |
| Public registration submits `role=admin` | Ignore/reject requested role; server assigns configured default. |
| Profile update submits role or asset update submits `user_id` | Role/ownership unchanged. |
| Revoked grant followed by a new request | Backend denies even if the old page still shows a button. |
| Inertia response inspected | Only permitted rows/fields and correct capability flags. |

Also cover these implementation details:

1. Recognized config scopes are only `own` and `any`; `create` and `reports.view` support `any` only in this implementation.
2. Every configured mutation grant has the intended accompanying read grant.
3. Unknown roles do not fall back to the default role; that default applies at trusted user creation only.
4. After changing a supported role's config in a test, the resolver and HTTP result change without reseeding permission tables.
5. A denied mutation leaves the persisted record unchanged, not merely a failed response.
6. If you add bulk operations, authorize every record. Choose and test atomic failure versus explicit partial success; never assume a class-level check covers all IDs.
7. If you add reports, exports, downloads, lookups, or jobs, verify their data scopes and entry points too.

Useful test structure for `tests/Feature/Authorization/AssetAuthorizationTest.php`:

```text
Use the project's database-reset trait and real User/Asset factories.
Create an editor, a different owner, and one asset for each.
Act as the editor.
PUT a new name to the other owner's assets.update route.
Assert 403 and assert the original database name is unchanged.
PUT a new name to the editor's own asset.
Assert redirect and assert the database name changed.
GET assets.index and assert the foreign asset is absent from the Inertia props.
Assert total equals the number of visible assets, not the whole table.
```

This is a test specification, not an executed test. Use `assertForbidden()` and database assertions for the actual regression tests; use the Inertia adapter's assertions if that version supplies them. Laravel's [HTTP test documentation](https://laravel.com/docs/13.x/http-tests) covers request assertions.

In the implemented target, run:

```powershell
php artisan route:list --path=assets --except-vendor --no-interaction
php artisan test --filter=AssetAuthorizationTest --no-interaction
```

Run the target's frontend type/lint checks after creating Vue pages, and inspect denied/allowed behavior in a browser. Use that project's declared scripts. Snipe-IT uses Laravel Mix and has `npm run dev`/`npm run prod`; do not copy its build commands into a target using a different bundler.

## 9. Optional Spatie approach

Spatie Permission manages roles and permissions in a database and integrates those grants with Laravel's Gate. It does not replace resource policies or ownership/query boundaries. See the [package introduction](https://spatie.be/docs/laravel-permission/v8/introduction?trk=public_post_comment-text).

| Approach | Where grants are evaluated | Does editing the file alone update runtime grants? |
| --- | --- | --- |
| Native resolver above | PHP config and trusted user role | Yes, after normal configuration deployment/cache refresh. |
| Spatie with file-driven synchronization | Database rows generated from a file | No; deploy must synchronize rows and refresh relevant caches. |

For your stated goal, use the native approach. Spatie becomes attractive when you want its assignment APIs, multiple roles, database relationships, or runtime role administration.

If you choose Spatie later:

1. Verify package compatibility against the target's actual Laravel/PHP versions. The retrieved compatibility table lists Laravel 12/13 with package `^7.0`–`^8.0` and PHP 8.3+. Confirm the selected release before installing. See [prerequisites](https://spatie.be/docs/laravel-permission/v8/prerequisites).
2. Replace the native assignment design. Spatie's `HasRoles` has reserved role/permission names; do not keep an incompatible `role` attribute or copy Snipe-IT's `permissions` property into that model unchanged.
3. Keep the chosen source-of-truth matrix in code. Write a reviewed, repeatable sync command/seeder that creates known permissions and uses role permission synchronization, including **removing revoked grants**. An additive-only seeder leaves old privileges behind.
4. Define what happens when an entire role disappears from the file, including existing assignments. Merely stopping creation of that role does not revoke its old users.
5. Use one consistent guard, normally `web` for this Inertia flow. Test guard mismatches explicitly if you introduce more.
6. Continue authorizing resource operations through policies and filtering queries. Keep ownership checks outside a broad global bypass.
7. Account for direct user grants: allowing them creates a second source of authority beyond the role matrix. Either disallow them or reconcile them deliberately.
8. Synchronize and reset permission caches as part of deployment; do not sync database permissions on every request or provider boot.

The `own`/`any` scope vocabulary in this document is our design; Spatie does not automatically interpret it. A Spatie adaptation needs a deliberate representation, such as separate `assets.update.own` and `assets.update.any` capabilities interpreted by a policy. Consult [seeding](https://spatie.be/index.php/docs/laravel-permission/v8/advanced-usage/seeding) and [cache handling](https://spatie.be/docs/laravel-permission/v8/advanced-usage/cache).

## 10. Instructions for an implementing AI agent

The user requested a learning document in this Snipe-IT repository. **The guide itself does not authorize replacing Snipe-IT's authorization system, installing packages, or changing its database.** Apply this recipe only when asked to implement it in the intended target project.

When authorized to implement the target:

1. Read that target's `AGENTS.md`, matching rules, and relevant skills. Inspect existing changes, authentication, schema, framework/adapter versions, and Vue page resolution before editing.
2. Preserve the user's requirement: static role definitions and grants in one version-controlled config file. Default to native Gate/Policies; do not install Spatie without an explicit decision to use it.
3. Use the sample roles/resource as examples, not asserted business requirements. Map the user's real roles, resources, assignment process, and ownership/tenant boundaries first. If unavailable, implement a bounded example only when that scope is authorized.
4. Keep user-role assignment separate from permission definitions. Use one trusted assignment source, prevent public role mutation, and plan existing-user backfill plus initial admin access.
5. Implement the config and fail-closed resolver, then policies, then boot registration. Use fresh Laravel 13 locations only if that target has the fresh skeleton.
6. Wire authenticated routes. Use consistent parameter names, explicit read/deletion Gate checks, and Form Request authorization for the demonstrated writes.
7. Apply visibility filters before pagination, counts, exports, and serialization. If tenancy exists, preserve tenant boundaries even for admins unless the requirements explicitly allow cross-tenant access.
8. Send small global capability props and resource-specific `can` flags. Complete every referenced Vue page and follow the target's component, translation, and route-link conventions.
9. Add meaningful denied/allowed request tests and important ownership/role-assignment regressions. Do not mock Gate in the feature tests intended to prove integration.
10. Run affected tests and the target's required formatting/type/lint checks. If working under this checkout's PHP rules, use its specified Pint command for actual PHP edits; a Markdown-only guide does not require Pint.
11. Document config-cache/deployment steps. Report exactly which checks passed and which could not run. Do not describe syntax-checked snippets as runtime-tested authorization.

Completion requires more than button visibility: forged requests must fail, allowed requests must work, list metadata must obey visibility, sensitive fields must stay out of props, and role changes must not be accepted through ordinary user input.

## 11. Troubleshooting and further reading

### Debug in this order

1. Does the route exist with the expected verb and parameter name?
2. Is the intended guard authenticating the user?
3. Do activation, 2FA, tenant, or model scopes stop the request first?
4. Which entry point invokes Gate: middleware, Form Request, controller, or only the UI?
5. Is the correct policy registered for this model?
6. Does a before callback return a decision unexpectedly?
7. Is this a class-level or record-level check? Does the method signature match?
8. Does the stored role/permission key match the configured key exactly?
9. Is configuration or a long-running process stale?
10. Are the Inertia capability props stale, missing, or overwritten by another `auth` prop?

For the existing Snipe-IT repository, these searches help find additional consumers:

```powershell
rg -n 'Gate::|\$this->authorize\(|->can\(|@can|@cannot|hasAccess\(' app routes resources/views
rg -n 'middleware|authorize:|can:' routes app/Http/Kernel.php
rg -n 'function authorize|Gate::|->can\(' app/Http/Requests
rg -n 'available_actions' app/Http/Transformers resources/views
rg -n 'CompanyableTrait|scopeCompanyables|isCurrentUserHasAccess' app
```

Once dependencies are installed, inspect actual route registration with `php artisan route:list`; do not rely solely on comments or a resource-route declaration. The Laravel Boost tools were not available in this session, so official documentation and local source reads were used.

### Primary references

- [Laravel 13 authorization](https://laravel.com/docs/13.x/authorization): Gates, policy registration, and enforcement APIs.
- [Laravel 13 middleware](https://laravel.com/docs/13.x/middleware): aliases and registration.
- [Laravel 13 providers](https://laravel.com/docs/13.x/providers): provider registration and boot.
- [Laravel 13 lifecycle](https://laravel.com/docs/13.x/lifecycle): bootstrap and request processing.
- [Laravel 13 Form Requests](https://laravel.com/docs/13.x/validation#authorizing-form-requests): authorization before controller execution.
- [Inertia authorization](https://inertiajs.com/docs/v3/security/authorization), [shared data](https://inertiajs.com/docs/v3/data-props/shared-data), and [error handling](https://inertiajs.com/docs/v3/advanced/error-handling).
- [Spatie Permission prerequisites](https://spatie.be/docs/laravel-permission/v8/prerequisites), [seeding](https://spatie.be/index.php/docs/laravel-permission/v8/advanced-usage/seeding), and [cache](https://spatie.be/docs/laravel-permission/v8/advanced-usage/cache).

The retrieved Inertia documentation is v3. Your requested stack did not specify an Inertia version; confirm the installed server/client adapters and use their matching documentation. The PHP/Vue snippets are proposed application code; this Snipe-IT checkout contains no installed Composer dependencies, so no Laravel runtime, route-list, or authorization tests were executed for this guide.
