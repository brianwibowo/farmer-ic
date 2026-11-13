<?php

namespace App\Http\Controllers\Petugas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Petugas\StoreTaskActivityRequest;
use App\Models\ActivityPhoto;
use App\Models\FarmActivity;
use App\Models\PlantingCycleTask;
use App\Services\Activity\ActivityFormLimits;
use App\Services\Activity\TaskActivityService;
use App\Services\PetugasTaskReadService;
use App\Support\ActivityInputLimits;
use App\Support\TaskActivityFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PetugasActivityController extends Controller
{
    public function create(Request $request, PlantingCycleTask $task, PetugasTaskReadService $tasks, ActivityFormLimits $limits): Response
    {
        $this->authorizeRecord($request, $task);

        return Inertia::render('petugas/ActivityCreate', [
            'task' => $tasks->detail($request->user(), $task), 'fields' => \App\Support\VoluntaryActivityFields::forCode(TaskActivityFields::activityCode($task) ?? ''),
            'areaLimitM2' => $limits->taskArea($request->user(), $task),
            'numericLimits' => ActivityInputLimits::common(),
            'today' => now('Asia/Jakarta')->toDateString(),
            'workDate' => $request->validate(['work_date' => ['sometimes', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()]])['work_date'] ?? now('Asia/Jakarta')->toDateString(),
        ]);
    }

    public function store(StoreTaskActivityRequest $request, PlantingCycleTask $task, TaskActivityService $activities): RedirectResponse
    {
        $this->authorizeRecord($request, $task);
        $activities->create($request->user(), $task, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Aktivitas ditambahkan ke laporan harian bersama dan terhubung ke tugas. Tugas belum diajukan selesai.']);

        return to_route('petugas.tasks.show', $task);
    }

    private function authorizeRecord(Request $request, PlantingCycleTask $task): void
    {
        Gate::authorize('view', $task);
        $farm = $task->plantingCycle->farm;
        abort_unless($farm->id === $request->user()->assigned_farm_id, 403);
        Gate::authorize('recordTask', [FarmActivity::class, $task]);
        abort_unless(in_array($task->status, ['pending', 'in_progress'], true) && $task->plantingCycle->planted_at !== null, 403, 'Tugas belum ditanam, sedang direview, atau sudah ditutup.');
    }

    public function photo(Request $request, FarmActivity $activity, ActivityPhoto $photo): StreamedResponse
    {
        Gate::authorize('view', $activity);
        Gate::authorize('view', $photo);
        abort_unless($photo->activity_id === $activity->id && $activity->farm_id === $request->user()->assigned_farm_id, 403);
        $team = $activity->farm->team;
        $photo = ActivityPhoto::visibleTo($request->user(), $team)->where('activity_id', $activity->id)->findOrFail($photo->id);
        abort_unless(str_starts_with($photo->image_url, 'task-activities/'.$activity->id.'/'), 404);

        return Storage::disk('local')->response($photo->image_url, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
