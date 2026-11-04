<?php

namespace App\Http\Controllers\Petugas;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PetugasFarmController extends Controller
{
    public function show(Request $request): Response
    {
        $farm = $request->user()->assignedFarm;

        if ($farm instanceof Farm) {
            Gate::authorize('view', $farm);
        }

        return Inertia::render('petugas/Kebun', [
            'farm' => $farm instanceof Farm ? [
                'id' => (string) $farm->id,
                'name' => $farm->name,
                'address' => $farm->address,
                'city' => $farm->city,
                'totalAreaHa' => $farm->total_area_ha,
                'latitude' => $farm->latitude,
                'longitude' => $farm->longitude,
            ] : null,
        ]);
    }
}
