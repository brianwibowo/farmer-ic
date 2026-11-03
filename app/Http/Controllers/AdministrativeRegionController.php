<?php

namespace App\Http\Controllers;

use App\Http\Resources\AdministrativeRegionResource;
use App\Models\AdministrativeRegion;
use App\Models\Team;
use App\Services\AdministrativeRegionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdministrativeRegionController extends Controller
{
    public function __invoke(Request $request, AdministrativeRegionService $regions): AnonymousResourceCollection
    {
        $team = $request->user()->currentTeam;
        Gate::authorize('viewAny', [AdministrativeRegion::class, $team]);
        abort_unless($team instanceof Team, 403);
        if (is_string($request->input('search'))) {
            $request->merge(['search' => trim($request->input('search'))]);
        }
        $level = $request->input('level');
        $level = is_scalar($level) ? (string) $level : null;
        $data = $request->validate([
            'level' => ['sometimes', 'required', 'integer', 'between:1,3'],
            'parent_code' => [Rule::requiredIf(in_array($level, ['2', '3'], true)), 'nullable', 'string',
                Rule::when($level === '2', ['regex:/^\d{2}$/']),
                Rule::when($level === '3', ['regex:/^\d{2}\.\d{2}$/'])],
            'search' => ['required_without:level', 'string', 'min:2', 'max:100'],
        ]);
        if (isset($data['level'])) {
            return AdministrativeRegionResource::collection($regions->options((int) $data['level'], $data['parent_code'] ?? null, $request->user(), $team));
        }

        return AdministrativeRegionResource::collection($regions->search(trim($data['search']), $request->user(), $team));
    }
}
