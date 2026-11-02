<?php

namespace App\Http\Controllers\Sop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sop\SaveSopTemplateTaskRequest;
use App\Models\SopTemplate;
use App\Models\SopTemplateTask;
use App\Models\SopTemplateVersion;
use App\Services\SopTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class SopTemplateTaskController extends Controller
{
    public function __construct(private readonly SopTemplateService $sops) {}

    public function store(SaveSopTemplateTaskRequest $request, SopTemplate $sopTemplate, SopTemplateVersion $version): RedirectResponse
    {
        abort_unless($version->sop_template_id === $sopTemplate->id, 404);
        Gate::authorize('create', [SopTemplateTask::class, $version]);
        $this->sops->createTask($version, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Langkah SOP berhasil ditambahkan.']);

        return to_route('sops.show', $sopTemplate);
    }

    public function update(SaveSopTemplateTaskRequest $request, SopTemplate $sopTemplate, SopTemplateVersion $version, SopTemplateTask $task): RedirectResponse
    {
        abort_unless($version->sop_template_id === $sopTemplate->id && $task->sop_template_version_id === $version->id, 404);
        Gate::authorize('update', $task);
        $this->sops->updateTask($task, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Langkah SOP berhasil diperbarui.']);

        return to_route('sops.show', $sopTemplate);
    }

    public function destroy(SopTemplate $sopTemplate, SopTemplateVersion $version, SopTemplateTask $task): RedirectResponse
    {
        abort_unless($version->sop_template_id === $sopTemplate->id && $task->sop_template_version_id === $version->id, 404);
        Gate::authorize('delete', $task);
        $this->sops->deleteTask($task);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Langkah SOP berhasil dihapus.']);

        return to_route('sops.show', $sopTemplate);
    }
}
