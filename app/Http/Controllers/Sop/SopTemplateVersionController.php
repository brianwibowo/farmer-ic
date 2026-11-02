<?php

namespace App\Http\Controllers\Sop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sop\StoreSopTemplateVersionRequest;
use App\Models\SopTemplate;
use App\Models\SopTemplateVersion;
use App\Services\SopTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class SopTemplateVersionController extends Controller
{
    public function __construct(private readonly SopTemplateService $sops) {}

    public function store(StoreSopTemplateVersionRequest $request, SopTemplate $sopTemplate): RedirectResponse
    {
        Gate::authorize('create', [SopTemplateVersion::class, $sopTemplate]);
        $version = $this->sops->storeVersion($sopTemplate, $request->user(), $request->validated('notes'));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Versi draft baru berhasil dibuat.']);

        return to_route('sops.show', $sopTemplate)->with('created_version', $version->id);
    }

    public function publish(Request $request, SopTemplate $sopTemplate, SopTemplateVersion $version): RedirectResponse
    {
        abort_unless($version->sop_template_id === $sopTemplate->id, 404);
        Gate::authorize('update', $version);
        $this->sops->publish($version, $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Versi SOP berhasil dipublikasikan.']);

        return to_route('sops.show', $sopTemplate);
    }
}
