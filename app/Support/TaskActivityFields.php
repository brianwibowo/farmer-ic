<?php

namespace App\Support;

use App\Models\PlantingCycleTask;

/** Shared presentation/validation contract; never resolves authority from browser input. */
class TaskActivityFields
{
    /** @return list<array<string, mixed>> */
    public static function forTask(PlantingCycleTask $task): array
    {
        $area = ['key' => 'area_ha', 'label' => 'Luas dikerjakan (ha)', 'type' => 'number', 'max' => 99999999.9999, 'step' => '0.0001'];

        return match (self::activityCode($task)) {
            'maintenance' => [$area],
            'growth_observation' => [['key' => 'growth_phase', 'label' => 'Fase pertumbuhan', 'type' => 'text']],
            'harvest' => [$area, ['key' => 'weight_ton', 'label' => 'Berat panen (ton)', 'type' => 'number', 'max' => 99999999999.999, 'step' => '0.001']],
            default => [],
        };
    }

    public static function activityCode(PlantingCycleTask $task): ?string
    {
        return match ($task->activity_category) {
            'maintenance' => 'maintenance',
            'growth_observation' => 'growth_observation',
            'harvest' => 'harvest',
            default => null,
        };
    }

    public static function activityTypeCode(PlantingCycleTask $task): ?string
    {
        return self::activityCode($task);
    }

    public static function requiresWorkerCount(PlantingCycleTask $task): bool
    {
        return $task->activity_category !== 'growth_observation';
    }

    public static function detailKey(PlantingCycleTask $task): ?string
    {
        return match (self::activityCode($task)) {
            'growth_observation' => 'growth_check',
            'harvest' => 'harvest',
            default => null,
        };
    }
}
