<?php

namespace App\Services\Activity;

use App\Http\Resources\DailyActivityHeaderResource;
use App\Models\ActivityPhoto;
use App\Models\ActivityType;
use App\Models\BlockMappingDetail;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\FertilizationDetail;
use App\Models\HarvestDetail;
use App\Models\IncidentDetail;
use App\Models\IrrigationDetail;
use App\Models\PesticideDetail;
use App\Models\PlantingCycle;
use App\Models\PlantingDetail;
use App\Models\PlantingRound;
use App\Models\PlantObservation;
use App\Models\User;

class DailyActivityReportService
{
    /** @return array<string, mixed> */
    public function report(User $actor, Farm $farm, DailyActivityLog $log): array
    {
        $team = $farm->team;
        $log = DailyActivityLog::visibleTo($actor, $team)->where('farm_id', $farm->id)->whereKey($log->id)->with('recorder:id,name')->firstOrFail();
        $activities = FarmActivity::visibleTo($actor, $team)->where('daily_activity_log_id', $log->id)->with('performer:id,name')->orderBy('position')->get();

        return [
            ...(new DailyActivityHeaderResource($log))->resolve(),
            'activities' => $activities->map(function (FarmActivity $activity) use ($actor, $team): array {
                $type = ActivityType::visibleTo($actor, $team)->whereKey($activity->activity_type_id)->first();
                $cycle = PlantingCycle::visibleTo($actor, $team)->whereKey($activity->planting_cycle_id)->first();
                $round = $cycle === null ? null : PlantingRound::visibleTo($actor, $team)->whereKey($cycle->getAttribute('planting_round_id'))->first();
                $block = $round === null ? null : FarmBlock::visibleTo($actor, $team)->whereKey($round->block_id)->first();
                $detailClass = match ($type?->code) {
                    'planting' => PlantingDetail::class, 'harvest' => HarvestDetail::class,
                    'irrigation' => IrrigationDetail::class, 'fertilization' => FertilizationDetail::class,
                    'pesticide' => PesticideDetail::class, 'growth_check' => PlantObservation::class,
                    'incident' => IncidentDetail::class, 'block_mapping' => BlockMappingDetail::class,
                    default => null,
                };
                $detail = $detailClass === null ? null : $detailClass::visibleTo($actor, $team)->where('activity_id', $activity->id)->first();

                return [
                    'id' => $activity->id, 'name' => $type->name ?? 'Aktivitas', 'position' => $activity->position,
                    'performed_by' => $activity->performed_by, 'performer_name' => $activity->performer?->name,
                    'subblok' => $cycle?->getAttribute('subblock_label'), 'notes' => $activity->description,
                    'block' => $block?->name,
                    'task_id' => $activity->getAttribute('planting_cycle_task_id'),
                    'details' => $this->details(($detail?->attributesToArray() ?? []) + (json_decode((string) $activity->getAttribute('task_details'), true) ?? [])),
                    'photos' => ActivityPhoto::visibleTo($actor, $team)->where('activity_id', $activity->id)->get()->map(fn (ActivityPhoto $photo) => [
                        'id' => $photo->id, 'url' => route('daily-logs.photos', ['farm' => $activity->farm_id, 'activity' => $activity->id, 'photo' => $photo->id]),
                    ])->all(),
                ];
            })->all(),
        ];
    }

    /** @param array<string, mixed> $values
     * @return list<array{label: string, value: mixed}>
     */
    private function details(array $values): array
    {
        $labels = [
            'seed_name' => 'Benih', 'quantity_kg' => 'Jumlah (kg)', 'planting_pattern' => 'Pola tanam', 'area_ha' => 'Luas (ha)',
            'harvested_area_ha' => 'Luas panen (ha)', 'gross_weight_ton' => 'Berat (ton)', 'moisture_percentage' => 'Kadar air (%)',
            'water_source' => 'Sumber air', 'irrigation_method' => 'Metode', 'duration_hours' => 'Durasi (jam)', 'machine_type' => 'Mesin',
            'fertilizer_type' => 'Pupuk', 'application_method' => 'Metode aplikasi', 'pesticide_category' => 'Kategori pengendalian',
            'purpose' => 'Sasaran', 'brand_name' => 'Produk', 'dosage_per_tank' => 'Dosis per tangki', 'tank_count' => 'Jumlah tangki',
            'growth_phase' => 'Fase', 'average_height_cm' => 'Tinggi (cm)', 'pest_indication' => 'Hama', 'disease_indication' => 'Penyakit',
            'block_name' => 'Blok', 'length_m' => 'Panjang (m)', 'width_m' => 'Lebar (m)', 'incident_category' => 'Kejadian', 'severity' => 'Tingkat',
            'method' => 'Metode pekerjaan', 'depth_cm' => 'Kedalaman (cm)', 'sample_count' => 'Jumlah sampel',
            'fertilizer' => 'Nama pupuk', 'target' => 'Sasaran pekerjaan', 'product' => 'Produk pengendali',
        ];
        $details = [];
        foreach ($labels as $key => $label) {
            if ($key === 'method' && (isset($values['irrigation_method']) || isset($values['application_method']))) {
                continue;
            }
            if ($key === 'fertilizer' && isset($values['fertilizer_type'])) {
                continue;
            }
            if (isset($values[$key])) {
                $details[] = ['label' => $label, 'value' => $values[$key]];
            }
        }

        return $details;
    }
}
