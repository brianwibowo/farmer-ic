<?php

namespace App\Services;

use App\Models\PlantingCycle;
use App\Models\PlantingCycleTask;
use App\Models\SopTemplateVersion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlantingCycleTaskService
{
    /** @var array<string, string> */
    private const STAGE_BY_CATEGORY = [
        'planting' => 'planting',
        'maintenance' => 'maintenance',
        'harvest' => 'harvest',
    ];

    /**
     * Generate the subblok task snapshot from its farm's default SOP.
     *
     * @return array{status: string, created: int, rescheduled: int, version_id: string|null, detail: string|null}
     */
    public function generateForSubblok(PlantingCycle $subblok): array
    {
        return DB::transaction(function () use ($subblok): array {
            $cycle = PlantingCycle::query()->lockForUpdate()->findOrFail($subblok->id);
            $farm = $cycle->farm;

            if ($farm === null) {
                return $this->result('missing_farm');
            }

            $tasks = $cycle->tasks()->get();
            $version = null;

            if ($cycle->sop_template_version_id !== null) {
                $version = SopTemplateVersion::query()
                    ->with('sopTemplate:id,team_id')
                    ->find($cycle->sop_template_version_id);

                if (! $version instanceof SopTemplateVersion) {
                    return $this->result('missing_sop_version');
                }

                if ((int) $version->sopTemplate?->team_id !== (int) $farm->team_id) {
                    return $this->result('invalid_sop_tenant');
                }

                if ($tasks->isNotEmpty()) {
                    return $this->result(
                        'already_generated',
                        0,
                        $this->reschedulePending($tasks, $this->anchorDate($cycle)),
                        $version->id,
                    );
                }
            } else {
                $template = $farm->defaultSopTemplate;

                if ($template === null) {
                    return $this->result('missing_sop');
                }

                if ((int) $template->team_id !== (int) $farm->team_id) {
                    return $this->result('invalid_sop_tenant');
                }

                if (! $template->is_active) {
                    return $this->result('inactive_sop');
                }

                $version = $template->versions()
                    ->where('status', 'published')
                    ->orderByDesc('version')
                    ->first();

                if (! $version instanceof SopTemplateVersion) {
                    return $this->result('missing_published_version');
                }

                if ($tasks->isNotEmpty()) {
                    $cycle->forceFill(['sop_template_version_id' => $version->id])->save();

                    return $this->result(
                        'already_generated',
                        0,
                        $this->reschedulePending($tasks, $this->anchorDate($cycle)),
                        $version->id,
                    );
                }
            }

            $sourceTasks = $version->tasks()->get();

            if ($sourceTasks->isEmpty()) {
                $cycle->forceFill(['sop_template_version_id' => $version->id])->save();

                return $this->result('no_tasks', 0, 0, $version->id);
            }

            $stages = $cycle->stages()->get()->keyBy('code');
            $anchor = $this->anchorDate($cycle);
            $created = 0;

            foreach ($sourceTasks as $source) {
                // Planting is the trigger and the HST anchor, not follow-up work.
                // It stays in the SOP definition for the planting activity form.
                if ($source->activity_category === 'planting') {
                    continue;
                }

                $stageCode = self::STAGE_BY_CATEGORY[$source->activity_category] ?? null;
                $stage = $stageCode === null ? null : $stages->get($stageCode);

                if ($stage === null) {
                    return $this->result('missing_stage', $created, 0, $version->id, $source->activity_category);
                }

                $scheduled = $anchor?->addDays($source->offset_days);
                $due = $scheduled?->addDays($source->tolerance_days);

                PlantingCycleTask::query()->create([
                    'planting_cycle_id' => $cycle->id,
                    'planting_cycle_stage_id' => $stage->id,
                    'sop_template_task_id' => $source->id,
                    'activity_category' => $source->activity_category,
                    'activity_subcategory' => $source->activity_subcategory,
                    'name' => $source->name,
                    'instructions' => $source->instructions,
                    'offset_days' => $source->offset_days,
                    'tolerance_days' => $source->tolerance_days,
                    'scheduled_date' => $scheduled?->toDateString(),
                    'due_date' => $due?->toDateString(),
                    'is_required' => $source->is_required,
                    'status' => 'pending',
                ]);
                $created++;
            }

            $cycle->forceFill(['sop_template_version_id' => $version->id])->save();

            return $this->result('generated', $created, 0, $version->id);
        });
    }

    private function anchorDate(PlantingCycle $cycle): ?CarbonImmutable
    {
        return $cycle->planted_at ?? $cycle->planned_planting_at;
    }

    /** @param Collection<int, PlantingCycleTask> $tasks */
    private function reschedulePending(Collection $tasks, ?CarbonImmutable $anchor): int
    {
        $rescheduled = 0;

        foreach ($tasks as $task) {
            if ($task->status !== 'pending') {
                continue;
            }

            $scheduled = $anchor?->addDays($task->offset_days);
            $due = $scheduled?->addDays($task->tolerance_days);
            $scheduledDate = $scheduled?->toDateString();
            $dueDate = $due?->toDateString();

            if ($task->scheduled_date?->toDateString() === $scheduledDate
                && $task->due_date?->toDateString() === $dueDate) {
                continue;
            }

            $task->forceFill([
                'scheduled_date' => $scheduledDate,
                'due_date' => $dueDate,
            ])->save();
            $rescheduled++;
        }

        return $rescheduled;
    }

    /** @return array{status: string, created: int, rescheduled: int, version_id: string|null, detail: string|null} */
    private function result(
        string $status,
        int $created = 0,
        int $rescheduled = 0,
        ?string $versionId = null,
        ?string $detail = null,
    ): array {
        return [
            'status' => $status,
            'created' => $created,
            'rescheduled' => $rescheduled,
            'version_id' => $versionId,
            'detail' => $detail,
        ];
    }
}
