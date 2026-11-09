<?php

namespace App\Services\Activity;

use App\Http\Resources\DailyActivityHeaderResource;
use App\Models\ActivityPhoto;
use App\Models\ActivityType;
use App\Models\DailyActivityLog;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FarmBlock;
use App\Models\PlantingCycle;
use App\Models\User;
use App\Support\ActivityDetailMap;
use App\Support\VoluntaryActivityFields;

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
                $blockId = $activity->getAttribute('block_id') ?? $cycle?->block_id;
                $block = $blockId === null ? null : FarmBlock::visibleTo($actor, $team)->whereKey($blockId)->first();
                $detailClass = ActivityDetailMap::model($type?->detail_key);
                $detail = $detailClass === null ? null : $detailClass::visibleTo($actor, $team)->where('activity_id', $activity->id)->first();

                return [
                    'id' => $activity->id, 'name' => $type->name ?? 'Aktivitas', 'position' => $activity->position,
                    'worker_count' => $activity->worker_count,
                    'performed_by' => $activity->performed_by, 'performer_name' => $activity->performer?->name,
                    'subblok' => $cycle?->getAttribute('subblock_label'), 'notes' => $activity->description,
                    'block' => $block?->name,
                    'task_id' => $activity->getAttribute('planting_cycle_task_id'),
                    'details' => $this->details(($detail?->attributesToArray() ?? []) + (json_decode((string) $activity->getAttribute('task_details'), true) ?? []), $type?->code),
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
    private function details(array $values, ?string $code = null): array
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
            'fertilization_stage' => 'Tahap pemupukan', 'quantity_unit' => 'Satuan jumlah', 'sack_size_kg' => 'Ukuran karung (kg)',
            'product_name' => 'Produk', 'water_liters' => 'Volume air (liter)', 'bed_count' => 'Jumlah bedengan',
            'height_cm' => 'Tinggi (cm)', 'bed_spacing_cm' => 'Jarak antar bedengan (cm)',
            'material_type' => 'Jenis bahan', 'quantity_value' => 'Jumlah bahan', 'description' => 'Deskripsi',
            'quantity' => 'Jumlah', 'bag_size' => 'Berat per sak (kg)', 'area' => 'Luas input', 'area_unit' => 'Satuan luas',
        ];
        foreach (VoluntaryActivityFields::forCode($code ?? '') as $field) {
            $labels[$field['key']] ??= $field['label'];
        }
        $details = [];
        foreach ($labels as $key => $label) {
            if ($key === 'area_unit' && ! isset($values['area'])) {
                continue;
            }
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
