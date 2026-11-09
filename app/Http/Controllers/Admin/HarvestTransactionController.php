<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListHarvestTransactionsRequest;
use App\Models\ActivityPhoto;
use App\Models\FarmActivity;
use App\Models\HarvestDetail;
use App\Models\Team;
use App\Services\Activity\HarvestTransactionService;
use App\Support\RolePermissions;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HarvestTransactionController extends Controller
{
    public function __construct(private readonly HarvestTransactionService $transactions) {}

    public function index(ListHarvestTransactionsRequest $request): Response
    {
        $user = $request->user();
        $team = app(RolePermissions::class)->singleTenant($user) ?? $user->currentTeam;
        abort_unless($team instanceof Team, 403, 'Pilih tim aktif untuk membuka transaksi panen.');
        Gate::authorize('viewAny', [FarmActivity::class, $team]);
        Gate::authorize('viewAny', [HarvestDetail::class, $team]);
        Gate::authorize('viewAny', [ActivityPhoto::class, $team]);

        $filters = array_replace([
            'search' => '',
            'farm_id' => '',
            'block_id' => '',
            'variety_id' => '',
            'from_date' => '',
            'to_date' => '',
            'per_page' => 15,
        ], $request->validated());

        return Inertia::render('harvest-transactions/Index', [
            'transactions' => $this->transactions->paginate($user, $team, $filters),
            'summary' => $this->transactions->summary($user, $team, $filters),
            'filters' => $filters,
            'farms' => $this->transactions->farms($user, $team),
            'blocks' => $this->transactions->blocks($user, $team),
            'varieties' => $this->transactions->varieties($user, $team),
            'team' => $team->only(['id', 'name']),
        ]);
    }
}
