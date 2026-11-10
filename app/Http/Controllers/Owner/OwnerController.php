<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\HarvestProjectionRequest;
use App\Http\Requests\Owner\OwnerReadRequest;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\FarmLegality;
use App\Models\SopTemplate;
use App\Models\Team;
use App\Models\User;
use App\Services\Owner\HarvestProjectionService;
use App\Services\Owner\OwnerReadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OwnerController extends Controller
{
    public function __construct(private readonly OwnerReadService $reads) {}

    public function dashboard(OwnerReadRequest $request): Response
    {
        $team = $this->team($request);
        Gate::authorize('viewAny', [Farm::class, $team]);
        $this->authorizeFarmFilter($request);

        // The Owner dashboard temporarily always shows every visible farm in the active tenant.
        return Inertia::render('owner/Dashboard', $this->reads->dashboard($request->user(), $team, $request->safe()->except(['farm_id'])));
    }

    public function farms(OwnerReadRequest $request): Response
    {
        $team = $this->team($request);
        Gate::authorize('viewAny', [Farm::class, $team]);

        return Inertia::render('owner/Farms', $this->reads->farms($request->user(), $team, $request->validated()));
    }

    public function farm(OwnerReadRequest $request, Farm $farm): Response
    {
        Gate::authorize('view', $farm);

        return Inertia::render('owner/Farm', $this->reads->farm($request->user(), $this->team($request), $farm));
    }

    public function block(OwnerReadRequest $request, Farm $farm, FarmBlock $block): Response
    {
        Gate::authorize('view', $farm);
        Gate::authorize('view', $block);
        abort_unless($block->farm_id === $farm->id, 404);

        return Inertia::render('owner/Block', $this->reads->block($request->user(), $this->team($request), $farm, $block));
    }

    public function legality(OwnerReadRequest $request, Farm $farm): Response
    {
        $team = $this->team($request);
        Gate::authorize('view', $farm);
        Gate::authorize('viewAny', [FarmLegality::class, $team]);

        return Inertia::render('owner/Legality', $this->reads->legality($request->user(), $team, $farm));
    }

    public function activities(OwnerReadRequest $request): Response
    {
        $team = $this->team($request);
        Gate::authorize('viewAny', [FarmActivity::class, $team]);
        $this->authorizeFarmFilter($request);

        return Inertia::render('owner/Activities', $this->reads->activities($request->user(), $team, $request->validated()));
    }

    public function activity(OwnerReadRequest $request, FarmActivity $activity): Response
    {
        Gate::authorize('view', $activity);

        return Inertia::render('owner/Activity', ['activity' => $this->reads->activity($request->user(), $this->team($request), $activity)]);
    }

    public function sops(OwnerReadRequest $request): Response
    {
        $team = $this->team($request);
        Gate::authorize('viewAny', [SopTemplate::class, $team]);

        return Inertia::render('owner/Sops', $this->reads->sops($request->user(), $team, $request->validated()));
    }

    public function sop(OwnerReadRequest $request, SopTemplate $sopTemplate): Response
    {
        Gate::authorize('view', $sopTemplate);

        return Inertia::render('owner/Sop', ['template' => $this->reads->sop($request->user(), $this->team($request), $sopTemplate)]);
    }

    public function users(OwnerReadRequest $request): Response
    {
        $team = $this->team($request);
        Gate::authorize('viewAny', [User::class, $team]);

        return Inertia::render('owner/Users', $this->reads->users($request->user(), $team, $request->validated()));
    }

    public function user(OwnerReadRequest $request, User $managedUser): Response
    {
        Gate::authorize('view', [$managedUser, true]);

        return Inertia::render('owner/User', ['managedUser' => $this->reads->user($request->user(), $this->team($request), $managedUser)]);
    }

    public function more(): Response
    {
        return Inertia::render('owner/More');
    }

    public function projection(HarvestProjectionRequest $request, HarvestProjectionService $projections): Response
    {
        $team = $this->team($request);
        Gate::authorize('viewAny', [Farm::class, $team]);
        $this->authorizeFarmFilter($request);

        return Inertia::render('owner/Projection', [
            'projection' => $projections->summary($request->user(), $team, $request->validated('period', 'cycle'), $request->validated('farm_id')),
            'urls' => [
                'index' => route('owner.projection', [], false),
                'home' => route('home', [], false),
            ],
        ]);
    }

    private function team(Request $request): Team
    {
        $team = $request->user()->currentTeam;
        abort_unless($team instanceof Team, 403, 'Pilih tim aktif untuk melihat data.');

        return $team;
    }

    private function authorizeFarmFilter(OwnerReadRequest|HarvestProjectionRequest $request): void
    {
        if ($request->filled('farm_id')) {
            $farm = Farm::query()->whereKey($request->validated('farm_id'))->firstOrFail();
            Gate::authorize('view', $farm);
            abort_unless((int) $farm->team_id === (int) $this->team($request)->id, 404);
        }
    }
}
