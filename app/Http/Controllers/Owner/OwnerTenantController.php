<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Services\Owner\OwnerContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OwnerTenantController extends Controller
{
    public function __invoke(Request $request, Team $team, OwnerContextService $context): RedirectResponse
    {
        Gate::authorize('view', $team);
        abort_unless(! $team->is_personal && $request->user()->belongsToTeam($team), 403);
        $context->selectTeam($request->user(), $team);

        return to_route('home');
    }
}
