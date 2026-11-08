<?php

namespace App\Http\Controllers;

use App\Http\Requests\Owner\OwnerReadRequest;
use App\Models\Farm;
use App\Models\Team;
use App\Services\Owner\OwnerReadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(OwnerReadRequest $request, OwnerReadService $owner): RedirectResponse|Response
    {
        if (Gate::allows('admin.access')) {
            return redirect()->route('admin.dashboard');
        }

        if (Gate::allows('petugas.access')) {
            return redirect()->route('petugas.home');
        }

        Gate::authorize('owner.access');
        $team = $request->user()->currentTeam;
        abort_unless($team instanceof Team, 403);
        Gate::authorize('viewAny', [Farm::class, $team]);
        if ($request->filled('farm_id')) {
            $farm = Farm::query()->whereKey($request->validated('farm_id'))->firstOrFail();
            Gate::authorize('view', $farm);
            abort_unless((int) $farm->team_id === (int) $team->id, 404);
        }

        return Inertia::render('owner/Dashboard', $owner->dashboard($request->user(), $team, $request->safe()->except(['farm_id'])));
    }
}
