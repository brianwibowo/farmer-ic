<?php

namespace App\Http\Controllers\Petugas;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\PlantingCycleTask;
use App\Models\Team;
use App\Services\PetugasTaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PetugasTaskController extends Controller
{
    public function __construct(private readonly PetugasTaskService $tasks) {}

    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', 'all');
        if (! in_array($status, ['all', 'overdue', 'today', 'upcoming', 'completed'], true)) {
            $status = 'all';
        }

        $user = $request->user();
        $farm = $user->assignedFarm;
        $tasks = [];

        if ($farm instanceof Farm) {
            $team = $farm->team;
            abort_unless($team instanceof Team, 403);

            Gate::authorize('viewAny', [PlantingCycleTask::class, $team]);
            Gate::authorize('view', $farm);
            $tasks = $this->tasks->forFarm($user, $team, $farm);
        }

        return Inertia::render('petugas/Tasks', [
            'tasks' => $tasks,
            'filters' => ['status' => $status],
            'isFixture' => false,
        ]);
    }
}
