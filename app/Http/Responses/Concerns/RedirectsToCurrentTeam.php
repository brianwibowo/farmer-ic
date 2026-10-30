<?php

namespace App\Http\Responses\Concerns;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

trait RedirectsToCurrentTeam
{
    protected function redirectPathForCurrentTeam(Request $request, string $redirect): string
    {
        if ($redirect === '/dashboard') {
            $adminAccess = Gate::forUser($request->user())->allows('admin.access');
            $petugasAccess = Gate::forUser($request->user())->allows('petugas.access');
            $intendedPath = parse_url((string) $request->session()->get('url.intended', ''), PHP_URL_PATH);
            if (! $adminAccess && is_string($intendedPath)
                && ($intendedPath === '/admin' || str_starts_with($intendedPath, '/admin/'))) {
                $request->session()->forget('url.intended');
            }

            if (! $petugasAccess && is_string($intendedPath)
                && ($intendedPath === '/petugas' || str_starts_with($intendedPath, '/petugas/'))) {
                $request->session()->forget('url.intended');
            }

            return route($adminAccess ? 'admin.dashboard' : ($petugasAccess ? 'petugas.home' : 'home'), absolute: false);
        }

        $team = $this->currentTeam($request);

        URL::defaults(['current_team' => $team->slug]);

        return "/{$team->slug}{$redirect}";
    }

    protected function currentTeam(Request $request): Team
    {
        $user = $request->user();

        abort_if(! $user, 403);

        $team = $user->currentTeam ?? $user->personalTeam();

        abort_if(! $team, 403);

        return $team;
    }
}
