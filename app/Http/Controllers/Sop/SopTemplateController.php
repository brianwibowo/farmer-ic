<?php

namespace App\Http\Controllers\Sop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sop\ListSopTemplatesRequest;
use App\Http\Requests\Sop\SaveSopTemplateRequest;
use App\Http\Requests\Sop\UpdateSopTemplateRequest;
use App\Models\SopTemplate;
use App\Models\SopTemplateTask;
use App\Models\Team;
use App\Services\SopTemplateService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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
            'search' => '', 'variety_id' => '', 'is_active' => '',
            'sort' => 'name', 'direction' => 'asc', 'per_page' => 15,
        ], $request->validated());
        $sops = $this->sops->paginate($request->user(), $team, $filters)->through(function (SopTemplate $sop): array {
            $row = $sop->toArray();
            $row['can'] = [
                'view' => Gate::allows('view', $sop),
                'update' => Gate::allows('update', $sop),
                'delete' => Gate::allows('delete', $sop),
            ];

            return $row;
        });

        return Inertia::render('sops/Index', [
            'sops' => $sops,
            'filters' => $filters,
            'varieties' => $this->sops->varieties(),
            'team' => $team->only(['id', 'name']),
            'can' => [
                'create' => Gate::allows('create', [SopTemplate::class, $team]),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        abort_unless($team instanceof Team, 403);
        Gate::authorize('create', [SopTemplate::class, $team]);

        return Inertia::render('sops/Form', [
            'team' => $team->only(['id', 'name']),
            'template' => null,
            'varieties' => $this->sops->varieties(),
        ]);
    }

    public function store(SaveSopTemplateRequest $request): RedirectResponse
    {
        $team = $request->user()->currentTeam;
        abort_unless($team instanceof Team, 403);
        Gate::authorize('create', [SopTemplate::class, $team]);
        $template = $this->sops->create($request->user(), $team, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Template SOP berhasil dibuat.']);

        return to_route('sops.show', $template);
    }

    public function show(SopTemplate $sopTemplate): Response
    {
        Gate::authorize('view', $sopTemplate);
        $template = $this->sops->detail($sopTemplate);

        return Inertia::render('sops/Show', [
            'template' => $template,
            'can' => [
                'update' => Gate::allows('update', $template),
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
        $team = $request->user()->currentTeam;
        abort_unless($team instanceof Team, 403);

        return Inertia::render('sops/Form', [
            'team' => $team->only(['id', 'name']),
            'template' => $template->only(['id', 'variety_id', 'name', 'description', 'is_active']),
            'varieties' => $this->sops->varieties(),
        ]);
    }

    public function update(UpdateSopTemplateRequest $request, SopTemplate $sopTemplate): RedirectResponse
    {
        Gate::authorize('update', $sopTemplate);
        $this->sops->update($sopTemplate, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Template SOP berhasil diperbarui.']);

        return to_route('sops.show', $sopTemplate);
    }

    public function destroy(SopTemplate $sopTemplate): RedirectResponse
    {
        Gate::authorize('delete', $sopTemplate);

        try {
            $this->sops->delete($sopTemplate);
        } catch (QueryException $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'delete' => 'SOP tidak dapat dihapus karena masih dipakai oleh batch atau data lain.',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Template SOP berhasil dihapus.']);

        return to_route('sops.index');
    }
}
