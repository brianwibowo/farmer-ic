<?php

namespace App\Http\Middleware;

use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FinancialBudget;
use App\Models\FinancialCategory;
use App\Models\FinancialProjection;
use App\Models\FinancialTransaction;
use App\Models\Notification;
use App\Models\SopTemplate;
use App\Models\Team;
use App\Models\User;
use App\Support\RolePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $guestPage = $request->route()?->defaults['farmer_guest'] ?? false;
        $user = $guestPage ? null : $request->user();
        $adminAccess = $user !== null && Gate::forUser($user)->allows('admin.access');
        $logbookTeam = $user ? (app(RolePermissions::class)->singleTenant($user) ?? $user->currentTeam) : null;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'ownerNavigation' => collect([
                'farms' => Farm::class,
                'activities' => FarmActivity::class,
                'sops' => SopTemplate::class,
                'users' => User::class,
                'finance' => FinancialTransaction::class,
            ])->map(fn (string $model): bool => $user !== null && $user->currentTeam !== null
                && Gate::forUser($user)->allows('viewAny', [$model, $user->currentTeam]))->all(),
            'navigation' => [
                'admin' => $adminAccess,
                'logbook' => $adminAccess && $logbookTeam instanceof Team
                    && Gate::forUser($user)->allows('viewAny', [Farm::class, $logbookTeam])
                    && Gate::forUser($user)->allows('viewAny', [DailyActivityLog::class, $logbookTeam]),
                'notifications' => $user !== null
                    && app(RolePermissions::class)->roleName($user) !== 'superadmin'
                    && Gate::forUser($user)->allows('viewAny', Notification::class),
                'finance' => collect([
                    'transactions' => FinancialTransaction::class,
                    'categories' => FinancialCategory::class,
                    'budgets' => FinancialBudget::class,
                    'projections' => FinancialProjection::class,
                ])->map(fn (string $model): bool => $user !== null && $user->currentTeam !== null
                    && Gate::forUser($user)->allows('viewAny', [$model, $user->currentTeam]))->all(),
                'users' => $user !== null && Gate::forUser($user)->allows('users.manage'),
                'teamSwitcher' => $user !== null && Gate::forUser($user)->allows('navigation.teamSwitcher'),
                'farms' => $adminAccess && $user->currentTeam !== null
                    && Gate::forUser($user)->allows('viewAny', [Farm::class, $user->currentTeam]),
            ],
            'auth' => [
                'user' => $user,
            ],
            'account' => fn () => $this->account($request, $guestPage, $user),
            'notificationAccess' => fn () => $this->account($request, $guestPage, $user) !== null
                && $request->user() instanceof User && Gate::forUser($request->user())->allows('viewAny', Notification::class),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentTeam' => fn () => $user?->currentTeam ? $user->toUserTeam($user->currentTeam) : null,
            'teams' => fn () => $user?->toUserTeams(includeCurrent: true) ?? [],
            'canCreateTeam' => fn () => $user !== null
                && Gate::forUser($user)->allows('create', Team::class),
        ];
    }

    /** @return array{name: string, email: string}|null */
    private function account(Request $request, bool $guestPage, ?User $user): ?array
    {
        if ($guestPage) {
            $guard = Auth::guard('web');
            // Old integer sessions must not query the current UUID user table on public demo pages.
            if (! $guard->hasUser()
                && ! Str::isUuid((string) $request->session()->get($guard->getName(), ''))) {
                return null;
            }
            $user = $guard->user();
        }

        return $user instanceof User ? ['name' => $user->name, 'email' => $user->email] : null;
    }
}
