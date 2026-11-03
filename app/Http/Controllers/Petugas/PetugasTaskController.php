<?php

namespace App\Http\Controllers\Petugas;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\PlantingCycleTask;
use App\Services\PetugasTaskReadService;
use App\Support\TaskActivityFields;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PetugasTaskController extends Controller
{
    public function __construct(private readonly PetugasTaskReadService $tasks) {}

    public function index(Request $request): Response
    {
        $farm = $request->user()->assignedFarm;
        $status = $request->query('status', 'all');
        if (! is_string($status) || ! in_array($status, ['all', 'overdue', 'today', 'upcoming', 'completed'], true)) {
            $status = 'all';
        }
        if (! $farm instanceof Farm) {
            return Inertia::render('petugas/Tasks', [
                'tasks' => [], 'filters' => ['status' => $status], 'farm' => null,
                'summary' => ['overdue' => 0, 'today' => 0, 'upcoming' => 0, 'completed' => 0], 'defaultSopMissing' => true,
            ]);
        }
        Gate::authorize('view', $farm);
        Gate::authorize('viewAny', [PlantingCycleTask::class, $farm->team]);
        $data = $this->tasks->listing($request->user(), $farm, $status);
        $data['tasks'] = array_map(fn ($task) => [...$task, 'can' => ['start' => false, 'submit' => false,
            'record' => Gate::allows('recordTask', [FarmActivity::class, PlantingCycleTask::visibleTo($request->user(), $farm->team)->findOrFail($task['id'])])
                && in_array($task['status'], ['pending', 'in_progress'], true) && $task['subblok']['planted_at'] !== null,
        ]], $data['tasks']);

        return Inertia::render('petugas/Tasks', [
            ...$data, 'filters' => ['status' => $status], 'farm' => $farm->only(['id', 'name']),
            'defaultSopMissing' => $farm->getAttribute('default_sop_template_id') === null,
        ]);
    }

    public function show(Request $request, PlantingCycleTask $task): Response
    {
        Gate::authorize('view', $task);
        abort_unless($task->plantingCycle->farm_id === $request->user()->assigned_farm_id, 403);

        return Inertia::render('petugas/TaskShow', [
            'task' => $this->tasks->detail($request->user(), $task),
            'reports' => $this->tasks->reports($request->user(), $task),
            'fields' => TaskActivityFields::forTask($task),
            'canRecord' => Gate::allows('recordTask', [FarmActivity::class, $task])
                && in_array($task->status, ['pending', 'in_progress'], true) && $task->plantingCycle->planted_at !== null,
        ]);
    }
}
