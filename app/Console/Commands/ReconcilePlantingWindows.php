<?php

namespace App\Console\Commands;

use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\User;
use App\Services\Activity\PlantingActivityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReconcilePlantingWindows extends Command
{
    protected $signature = 'planting:reconcile-windows';

    protected $description = 'Reconcile HST and pending SOP tasks after the three-day planting window';

    public function handle(PlantingActivityService $planting): int
    {
        // Bootstrap persisted execution identities only. All domain retrieval and
        // work below is scoped and authorized as each assigned actor, never global.
        $processed = [];
        $failed = 0;
        foreach (User::query()->whereNotNull('assigned_farm_id')->cursor() as $actor) {
            $gate = Gate::forUser($actor);
            $parent = $actor->assignedFarm;
            if (! $parent || ! $parent->team || ! $gate->allows('view', $parent)) {
                continue;
            }
            $farm = Farm::visibleTo($actor, $parent->team)->find($parent->id);
            if (! $farm) {
                continue;
            }
            foreach (PlantingCycle::visibleTo($actor, $farm->team)->where('farm_id', $farm->id)
                ->whereIn('status', ['planned', 'active'])->whereNotNull('planting_started_on')
                ->whereNull('planting_window_checked_at')->whereDate('planting_started_on', '<', today('Asia/Jakarta')->subDays(2))->cursor() as $cycle) {
                if (isset($processed[$cycle->id]) || ! $gate->allows('recordPlanting', [FarmActivity::class, $cycle]) || ! $gate->allows('synchronize', [PlantingCycleTask::class, $cycle])) {
                    continue;
                }
                $gate->authorize('recordPlanting', [FarmActivity::class, $cycle]);
                $gate->authorize('synchronize', [PlantingCycleTask::class, $cycle]);
                $processed[$cycle->id] = true;
                try {
                    $planting->reconcile($actor, $cycle);
                } catch (ValidationException $exception) {
                    $failed++;
                    $this->warn('Subblok '.$cycle->id.' belum direkonsiliasi: '.$exception->getMessage());
                }
            }
        }
        $this->info((count($processed) - $failed).' planting windows reconciled.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
