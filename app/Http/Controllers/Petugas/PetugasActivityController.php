<?php

namespace App\Http\Controllers\Petugas;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Services\SopTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PetugasActivityController extends Controller
{
    public function __construct(private readonly SopTemplateService $sops) {}

    public function create(Request $request): Response
    {
        $farm = $request->user()->assignedFarm;
        if ($farm instanceof Farm) {
            Gate::authorize('view', $farm);
        }

        return Inertia::render('petugas/ActivityCreate', [
            'farm' => $farm?->only(['id', 'name']),
            'defaultSop' => $farm instanceof Farm
                ? $this->sops->defaultForFarm($farm)
                : null,
        ]);
    }
}
