<?php

namespace App\Http\Controllers;

use App\Http\Requests\DashboardRequest;
use App\Models\Farm;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Services\AdminDashboardService;
use App\Support\RolePermissions;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly AdminDashboardService $dashboard) {}

    public function __invoke(DashboardRequest $request): Response
    {
        $filters = $request->validated();
        $team = Gate::allows('access.global')
            ? $request->user()->currentTeam
            : (app(RolePermissions::class)->singleTenant($request->user()) ?? $request->user()->currentTeam);
        Gate::authorize('viewAny', [Farm::class, $team]);
        abort_unless($team instanceof Team, 403, 'Pilih tenant aktif untuk membuka dashboard.');
        if (! empty($filters['farm_id'])) {
            Gate::authorize('view', Farm::query()->findOrFail($filters['farm_id']));
        }
        $email = strtolower($request->user()->email);

        $pendingInvitations = TeamInvitation::query()
            ->with(['inviter', 'team'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (TeamInvitation $invitation) => [
                'code' => $invitation->code,
                'inviterName' => $invitation->inviter->name,
                'team' => [
                    'name' => $invitation->team->name,
                    'slug' => $invitation->team->slug,
                ],
            ]);

        return Inertia::render('Dashboard', [
            'pendingInvitations' => $pendingInvitations,
            'dashboard' => $this->dashboard->summary($request->user(), $team, $filters),
        ]);
    }
}
