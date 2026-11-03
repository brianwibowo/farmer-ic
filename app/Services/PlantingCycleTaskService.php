<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\PlantingCycle;
use App\Models\PlantingCycleStage;
use App\Models\PlantingCycleTask;
use App\Models\SopTemplate;
use App\Models\SopTemplateTask;
use App\Models\SopTemplateVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlantingCycleTaskService
{
    /**
     * Callers authorize the parent-cycle use case before invoking this service.
     *
     * @return array{status: string, created: int, rescheduled: int, version_id: string|null, detail: string|null}
     */
    public function generateForSubblok(PlantingCycle $subblok, User $actor): array
    {
        return DB::transaction(function () use ($subblok, $actor): array {
            $team = $subblok->farm?->team;
            if ($team === null) {
                return $this->result('missing_farm');
            }
            $farm = Farm::visibleTo($actor, $team)->whereKey($subblok->farm_id)->lockForUpdate()->firstOrFail();
            $cycle = PlantingCycle::visibleTo($actor, $team)->whereKey($subblok->id)->where('farm_id', $farm->id)->lockForUpdate()->firstOrFail();
            if (! in_array($cycle->status, ['planned', 'active'], true)) {
                return $this->result('closed_cycle');
            }
            $anchor = $cycle->planted_at ?? $cycle->planned_planting_at;
            if ($anchor === null) {
                return $this->result('missing_anchor');
            }
            $tasks = PlantingCycleTask::visibleTo($actor, $team)->where('planting_cycle_id', $cycle->id)->lockForUpdate()->get();
            $version = null;
            if ($cycle->sop_template_version_id !== null) {
                // Resolve the persisted boundary before a scoped version read.
                $linked = $cycle->sopTemplateVersion;
                if ($linked === null) {
                    return $this->result('missing_sop_version');
                }
                if ((int) $linked->sopTemplate->team_id !== (int) $team->id) {
                    return $this->result('invalid_sop_tenant');
                }
                $version = SopTemplateVersion::visibleTo($actor, $team)->whereKey($linked->id)->first();
                if ($version === null || ! in_array($version->status, ['published', 'retired'], true)) {
                    return $this->result('missing_published_version');
                }
                if ($tasks->isNotEmpty()) {
                    return $this->result('already_generated', 0, $this->reschedulePending($tasks, $anchor), $version->id);
                }
            } else {
                $linked = $farm->defaultSopTemplate;
                if ($linked === null) {
                    return $this->result('missing_sop');
                }
                if ((int) $linked->team_id !== (int) $team->id) {
                    return $this->result('invalid_sop_tenant');
                }
                $template = SopTemplate::visibleTo($actor, $team)->whereKey($linked->id)->first();
                if ($template === null || ! $template->is_active) {
                    return $this->result('inactive_sop');
                }
                $version = SopTemplateVersion::visibleTo($actor, $team)->where('sop_template_id', $template->id)
                    ->where('status', 'published')->orderByDesc('version')->first();
                if ($version === null) {
                    return $this->result('missing_published_version');
                }
            }
            $sources = SopTemplateTask::visibleTo($actor, $team)->where('sop_template_version_id', $version->id)->get();
            if ($cycle->variety_id !== null && $cycle->variety?->commodity_id !== $version->sopTemplate->commodity_id) {
                return $this->result('commodity_mismatch');
            }
            if ($tasks->isNotEmpty()) {
                return $this->result('missing_sop_version');
            }
            $stages = PlantingCycleStage::visibleTo($actor, $team)->where('planting_cycle_id', $cycle->id)->get()->keyBy('code');
            foreach ($sources as $source) {
                if ($source->activity_category !== 'planting' && ! $stages->has($source->activity_category)) {
                    return $this->result('missing_stage', 0, 0, $version->id, $source->activity_category);
                }
            }
            $created = 0;
            foreach ($sources as $source) {
                if ($source->activity_category === 'planting') {
                    continue;
                }
                $date = $anchor->addDays($source->offset_days);
                PlantingCycleTask::create([
                    'planting_cycle_id' => $cycle->id, 'planting_cycle_stage_id' => $stages->get($source->activity_category)->id,
                    'sop_template_task_id' => $source->id, 'activity_category' => $source->activity_category,
                    'activity_subcategory' => $source->activity_subcategory, 'name' => $source->name, 'instructions' => $source->instructions,
                    'offset_days' => $source->offset_days, 'tolerance_days' => $source->tolerance_days,
                    'scheduled_date' => $date->toDateString(), 'due_date' => $date->addDays($source->tolerance_days)->toDateString(),
                    'is_required' => $source->is_required, 'status' => 'pending',
                ]);
                $created++;
            }
            $cycle->forceFill(['sop_template_version_id' => $version->id])->save();

            return $this->result($created > 0 ? 'generated' : 'no_tasks', $created, 0, $version->id);
        });
    }

    /** @param Collection<int, PlantingCycleTask> $tasks */
    private function reschedulePending(Collection $tasks, CarbonImmutable $anchor): int
    {
        $count = 0;
        foreach ($tasks as $task) {
            if ($task->status !== 'pending') {
                continue;
            }
            $date = $anchor->addDays($task->offset_days);
            // Preserve an Admin's explicit override when the base HST schedule moves.
            $due = ($task->rescheduled_date ?? $date)->addDays($task->tolerance_days);
            if ($task->scheduled_date?->toDateString() !== $date->toDateString() || $task->due_date?->toDateString() !== $due->toDateString()) {
                $task->forceFill(['scheduled_date' => $date->toDateString(), 'due_date' => $due->toDateString()])->save();
                $count++;
            }
        }

        return $count;
    }

    /** @return array{status: string, created: int, rescheduled: int, version_id: string|null, detail: string|null} */
    private function result(string $status, int $created = 0, int $rescheduled = 0, ?string $versionId = null, ?string $detail = null): array
    {
        return ['status' => $status, 'created' => $created, 'rescheduled' => $rescheduled, 'version_id' => $versionId, 'detail' => $detail];
    }
}
