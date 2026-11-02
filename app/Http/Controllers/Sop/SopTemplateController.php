<?php

namespace App\Http\Controllers\Sop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sop\ListSopTemplatesRequest;
use App\Http\Requests\Sop\SaveSopTemplateRequest;
use App\Http\Requests\Sop\UpdateSopTemplateRequest;
use App\Models\Farm;
use App\Models\SopTemplate;
use App\Models\SopTemplateTask;
use App\Models\Team;
use App\Services\SopTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SopTemplateController extends Controller
{
    public function __construct(private readonly SopTemplateService $sops) {}

    public function index(ListSopTemplatesRequest $request): Response
    {
        $team = $request->user()->currentTeam;
        abort_unless($team instanceof Team, 403);
        Gate::authorize('viewAny', [SopTemplate::class, $team]);

        $filters = array_replace([
            'search' => '', 'farm_id' => '', 'variety_id' => '', 'is_active' => '',
            'sort' => 'name', 'direction' => 'asc', 'per_page' => 15,
        ], $request->validated());
        $farms = $this->sops->farms($request->user(), $team);
        $createFarm = Farm::query()->visibleTo($request->user(), $team)->where('team_id', $team->id)->first();

        return Inertia::render('sops/Index', [
            'sops' => $this->sops->paginate($request->user(), $team, $filters),
            'filters' => $filters,
            'farms' => $farms,
            'varieties' => $this->sops->varieties(),
            'can' => [
                'create' => $createFarm instanceof Farm && Gate::allows('create', [SopTemplate::class, $createFarm]),
            ],
        ]);
    }

    public function create(Request $request, Farm $farm): Response
    {
        Gate::authorize('create', [SopTemplate::class, $farm]);

        return Inertia::render('sops/Form', [
            'farm' => $farm->only(['id', 'name']),
            'template' => null,
            'varieties' => $this->sops->varieties(),
            'stageCodes' => SopTemplateTask::STAGE_CODES,
            'anchors' => SopTemplateTask::SCHEDULE_ANCHORS,
        ]);
    }

    public function store(SaveSopTemplateRequest $request, Farm $farm): RedirectResponse
    {
        Gate::authorize('create', [SopTemplate::class, $farm]);
        $template = $this->sops->create($request->user(), $farm, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Template SOP berhasil dibuat.']);

        return to_route('sops.show', $template);
    }

    public function show(Request $request, SopTemplate $sopTemplate): Response
    {
        Gate::authorize('view', $sopTemplate);
        $team = $request->user()->currentTeam;
        abort_unless($team instanceof Team, 403);
        $template = $this->sops->detail($sopTemplate);

        return Inertia::render('sops/Show', [
            'template' => $template,
            'activityTypes' => $this->sops->activityTypes(),
            'targetFarms' => $this->sops->farms($request->user(), $team, $template->farm_id),
            'stageCodes' => SopTemplateTask::STAGE_CODES,
            'anchors' => SopTemplateTask::SCHEDULE_ANCHORS,
            'can' => [
                'update' => Gate::allows('update', $template),
                'copy' => Gate::allows('copy', $template),
                'manageVersions' => Gate::allows('create', [\App\Models\SopTemplateVersion::class, $template]),
                'manageTasks' => $template->versions->contains(fn ($version) => $version->status === 'draft')
                    && Gate::allows('create', [SopTemplateTask::class, $template->versions->firstWhere('status', 'draft')]),
            ],
        ]);
    }

    public function edit(Request $request, SopTemplate $sopTemplate): Response
    {
        Gate::authorize('update', $sopTemplate);
        $template = $this->sops->detail($sopTemplate);

        return Inertia::render('sops/Form', [
            'farm' => $template->farm->only(['id', 'name']),
            'template' => $template->only(['id', 'farm_id', 'variety_id', 'name', 'description', 'is_active']),
            'varieties' => $this->sops->varieties(),
            'stageCodes' => SopTemplateTask::STAGE_CODES,
            'anchors' => SopTemplateTask::SCHEDULE_ANCHORS,
        ]);
    }

    public function update(UpdateSopTemplateRequest $request, SopTemplate $sopTemplate): RedirectResponse
    {
        Gate::authorize('update', $sopTemplate);
        $this->sops->update($sopTemplate, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Template SOP berhasil diperbarui.']);

        return to_route('sops.show', $sopTemplate);
    }
}
