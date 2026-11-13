<?php

namespace App\Http\Controllers\Petugas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Petugas\CompleteTaskRequest;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\PlantingCycleTask;
use App\Services\Activity\ActivitySequenceService;
use App\Services\PetugasTaskReadService;
use App\Services\PlantingCycleTaskManagementService;
use App\Support\TaskActivityFields;
use App\Support\VoluntaryActivityFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PetugasTaskController extends Controller
{
    public function __construct(private readonly PetugasTaskReadService $tasks, private readonly ActivitySequenceService $sequence) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate(['block_id' => ['nullable', 'uuid']]);
        $farm = $request->user()->assignedFarm;
        $status = $request->query('status', 'all');
        if (! is_string($status) || ! in_array($status, ['all', 'overdue', 'today', 'upcoming', 'completed'], true)) {
            $status = 'all';
        }
        if (! $farm instanceof Farm) {
            return Inertia::render('petugas/Aktivitas', [
                'tasks' => [], 'filters' => ['status' => $status, 'block_id' => $filters['block_id'] ?? null], 'farm' => null,
                'summary' => ['overdue' => 0, 'today' => 0, 'upcoming' => 0, 'completed' => 0], 'defaultSopMissing' => true,
            ]);
        }
        Gate::authorize('view', $farm);
        Gate::authorize('viewAny', [PlantingCycleTask::class, $farm->team]);
        $data = $this->tasks->listing($request->user(), $farm, $status);
        $data['tasks'] = array_map(function (array $task) use ($request, $farm): array {
            $model = PlantingCycleTask::visibleTo($request->user(), $farm->team)->whereKey($task['id'])->firstOrFail();
            $reason = $this->sequence->blockedReason($request->user(), $model->plantingCycle, TaskActivityFields::activityCode($model) ?? '');
            $open = in_array($task['status'], ['pending', 'in_progress'], true) && $task['subblok']['planted_at'] !== null;

            return [...$task, 'can' => [
                'record' => $open && $reason === null && Gate::allows('recordTask', [FarmActivity::class, $model]),
                'complete' => $open && $task['linked_activities_count'] > 0 && Gate::allows('complete', $model),
            ]];
        }, $data['tasks']);

        return Inertia::render('petugas/Aktivitas', [
            ...$data, 'filters' => ['status' => $status, 'block_id' => $filters['block_id'] ?? null], 'farm' => $farm->only(['id', 'name']),
            'defaultSopMissing' => $farm->getAttribute('default_sop_template_id') === null,
        ]);
    }

    public function show(Request $request, PlantingCycleTask $task): Response
    {
        Gate::authorize('view', $task);
        abort_unless($task->plantingCycle->farm_id === $request->user()->assigned_farm_id, 403);

        $detail = $this->tasks->detail($request->user(), $task);
        $blockedReason = $this->sequence->blockedReason($request->user(), $task->plantingCycle, TaskActivityFields::activityCode($task) ?? '');
        $open = in_array($task->status, ['pending', 'in_progress'], true) && $task->plantingCycle->planted_at !== null;
        $detail['can']['record'] = $open && $blockedReason === null && Gate::allows('recordTask', [FarmActivity::class, $task]);
        $detail['can']['complete'] = $open && $detail['linked_activities_count'] > 0 && Gate::allows('complete', $task);

        return Inertia::render('petugas/TaskShow', [
            'task' => $detail,
            'reports' => $this->tasks->reports($request->user(), $task),
            'fields' => collect([...TaskActivityFields::forTask($task), ...VoluntaryActivityFields::forCode(TaskActivityFields::activityCode($task) ?? '')])->unique('key')->values()->all(),
            'canRecord' => $detail['can']['record'],
            'blockedReason' => $blockedReason,
        ]);
    }

    public function complete(CompleteTaskRequest $request, PlantingCycleTask $task, PlantingCycleTaskManagementService $tasks): RedirectResponse
    {
        Gate::authorize('view', $task);
        abort_unless($task->plantingCycle->farm_id === $request->user()->assigned_farm_id, 403);
        Gate::authorize('complete', $task);
        $note = $request->validated('note');
        $tasks->complete($task, $request->user(), is_string($note) ? $note : null);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tugas ditandai selesai.']);

        return to_route('petugas.tasks.show', $task);
    }
}
