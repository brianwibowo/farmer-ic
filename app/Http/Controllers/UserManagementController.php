<?php

namespace App\Http\Controllers;

use App\Http\Requests\Users\ListUsersRequest;
use App\Http\Requests\Users\SaveUserRequest;
use App\Models\Farm;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\UserManagementService;
use App\Support\RolePermissions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function __construct(private UserManagementService $users, private RolePermissions $permissions) {}

    public function index(ListUsersRequest $request): Response
    {
        $actor = $request->user();
        $tenant = $this->tenant($request);
        Gate::authorize('viewAny', [User::class, $tenant]);

        return Inertia::render('users/Index', [
            'users' => $this->users->paginate($actor, $request->validated(), $tenant)
                ->through(fn (User $user): array => [
                    ...$this->users->serialize($user),
                    'can' => [
                        'update' => Gate::allows('update', $user),
                        'delete' => ! $actor->is($user) && Gate::allows('delete', $user),
                    ],
                ]),
            'summary' => $this->users->summary($actor, $tenant),
            'filters' => $request->validated(),
            'options' => $this->options($actor, $tenant, listing: true),
            'can' => [
                'create' => Gate::allows('create', [User::class, $tenant]),
                'viewDeleted' => Gate::allows('viewDeletedAny', [User::class, $tenant]),
            ],
            'userManagementScope' => $this->scope($tenant),
        ]);
    }

    public function deleted(Request $request): Response
    {
        $tenant = $this->tenant($request);
        Gate::authorize('viewDeletedAny', [User::class, $tenant]);

        return Inertia::render('users/Deleted', [
            'users' => $this->users->deleted($request->user(), $tenant)
                ->through(fn (User $user): array => [
                    ...$this->users->serialize($user),
                    'deleted_at' => $user->deleted_at?->toISOString(),
                    'can_restore' => Gate::allows('restore', $user),
                ]),
        ]);
    }

    public function restore(Request $request, User $managedUser): RedirectResponse
    {
        Gate::authorize('restore', $managedUser);
        $this->users->restore($request->user(), $managedUser, $this->tenant($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'User berhasil dipulihkan.']);

        return to_route('user-management.deleted');
    }

    public function create(Request $request): Response
    {
        $tenant = $this->tenant($request);
        Gate::authorize('create', [User::class, $tenant]);

        return Inertia::render('users/Form', [
            'managedUser' => null,
            'options' => $this->options($request->user(), $tenant),
            'isSelf' => false,
            'can' => ['configure' => true, 'changeRole' => true],
            'userManagementScope' => $this->scope($tenant),
        ]);
    }

    public function store(SaveUserRequest $request): RedirectResponse
    {
        $tenant = $this->tenant($request);
        [$role, $teams, $farm] = $this->proposed($request);
        Gate::authorize('create', [User::class, $tenant, $role, $teams, $farm]);
        $user = $this->users->save($request->user(), $request->validated(), tenant: $tenant);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'User dan assignment berhasil ditambahkan.']);

        return to_route('user-management.show', $user);
    }

    public function show(Request $request, User $managedUser): Response
    {
        Gate::authorize('view', [$managedUser, true]);
        $tenant = $this->tenant($request);

        return Inertia::render('users/Show', [
            'managedUser' => $this->users->detail($request->user(), $managedUser, $tenant),
            'can' => [
                'update' => Gate::allows('update', $managedUser),
                'delete' => ! $request->user()->is($managedUser) && Gate::allows('delete', $managedUser),
            ],
            'userManagementScope' => $this->scope($tenant),
        ]);
    }

    public function edit(Request $request, User $managedUser): Response
    {
        Gate::authorize('update', $managedUser);
        $tenant = $this->tenant($request);

        return Inertia::render('users/Form', [
            'managedUser' => $this->users->detail($request->user(), $managedUser, $tenant),
            'options' => $this->options($request->user(), $tenant, $managedUser),
            'isSelf' => $request->user()->is($managedUser),
            'can' => [
                'configure' => Gate::allows('configure', $managedUser),
                'changeRole' => Gate::allows('users.manageAll'),
            ],
            'userManagementScope' => $this->scope($tenant),
        ]);
    }

    public function update(SaveUserRequest $request, User $managedUser): RedirectResponse
    {
        $tenant = $this->tenant($request);
        [$role, $teams, $farm] = $this->proposed($request);
        Gate::authorize('update', [$managedUser, $role]);
        if ($this->users->assignmentsChanged($managedUser, $request->validated())) {
            Gate::authorize('configure', [$managedUser, $role, $teams, $farm]);
        }
        $user = $this->users->save($request->user(), $request->validated(), $managedUser, $tenant);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data user dan assignment berhasil diperbarui.']);

        return to_route('user-management.show', $user);
    }

    public function destroy(Request $request, User $managedUser): RedirectResponse
    {
        Gate::authorize('delete', $managedUser);
        $this->users->delete($request->user(), $managedUser, $this->tenant($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'User berhasil dihapus.']);

        return to_route('user-management.index');
    }

    private function tenant(Request $request): ?Team
    {
        if (Gate::allows('users.viewAcrossTeams')) {
            return null;
        }
        $tenant = $this->permissions->singleTenant($request->user());
        abort_unless($tenant !== null, 403);

        return $tenant;
    }

    /** @return array<string, mixed> */
    private function scope(?Team $tenant): array
    {
        return [
            'global' => Gate::allows('users.manageAll'),
            'multiTenant' => $tenant === null,
            'team' => $tenant?->only(['id', 'name']),
        ];
    }

    /** @return array<string, mixed> */
    private function options(User $actor, ?Team $tenant, ?User $target = null, bool $listing = false, bool $includeFarms = true): array
    {
        $options = $this->users->options($actor, $includeFarms, $tenant);
        $options['roles'] = $this->users->roles($actor, $tenant)
            ->filter(fn (Role $role): bool => $target !== null
                ? Gate::forUser($actor)->allows('update', [$target, $role])
                : Gate::forUser($actor)->allows($listing ? 'viewAny' : 'create', [User::class, $tenant, $role]))
            ->map(fn (Role $role): array => ['value' => $role->name, 'label' => ucfirst($role->name)])->values();

        return $options;
    }

    /** @return array{Role, Collection<int, Team>, ?Farm} */
    private function proposed(SaveUserRequest $request): array
    {
        $role = Role::query()->where('name', $request->validated('role'))->firstOrFail();
        $teams = Team::query()->whereIn('id', $request->validated('team_ids'))->get();
        $farmId = $request->validated('assigned_farm_id');
        $farm = $farmId ? Farm::query()->whereKey($farmId)->firstOrFail() : null;

        return [$role, $teams, $farm];
    }
}
