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
        $method = ['key' => 'method', 'label' => 'Metode', 'type' => 'text'];
        $fields = match ($task->activity_category === 'harvest' ? 'harvest' : $task->activity_subcategory) {
            'irrigation' => [$area, ['key' => 'water_source', 'label' => 'Sumber air', 'type' => 'text'], $method, ['key' => 'pump', 'label' => 'Mesin pompa (opsional)', 'type' => 'text', 'optional' => true], ['key' => 'duration_hours', 'label' => 'Durasi (jam)', 'type' => 'number', 'max' => 999999.99, 'step' => '0.01']],
            'weeding' => [$area, $method, ['key' => 'depth_cm', 'label' => 'Kedalaman (cm, opsional)', 'type' => 'number', 'max' => 1000, 'step' => '0.01', 'optional' => true]],
            'follow_up_fertilization' => [$area, ['key' => 'fertilizer', 'label' => 'Jenis pupuk', 'type' => 'text'], ['key' => 'quantity_kg', 'label' => 'Jumlah pupuk (kg)', 'type' => 'number', 'max' => 999999999.999, 'step' => '0.001'], $method],
            'growth_check' => [['key' => 'phase', 'label' => 'Fase pertumbuhan', 'type' => 'text'], ['key' => 'height_cm', 'label' => 'Tinggi rata-rata (cm)', 'type' => 'number', 'max' => 999999.99, 'step' => '0.01'], ['key' => 'sample_count', 'label' => 'Jumlah sampel', 'type' => 'number', 'max' => 32767, 'step' => '1'], ['key' => 'pest', 'label' => 'Indikasi hama', 'type' => 'select', 'options' => ['Aman', 'Ringan', 'Sedang', 'Berat']], ['key' => 'disease', 'label' => 'Indikasi penyakit', 'type' => 'select', 'options' => ['Aman', 'Ringan', 'Sedang', 'Berat']]],
            'pest_control' => [$area, ['key' => 'control_type', 'label' => 'Jenis pengendalian', 'type' => 'select', 'options' => ['Insektisida', 'Fungisida', 'Pestisida', 'Lainnya']], ['key' => 'target', 'label' => 'Target hama / penyakit', 'type' => 'text'], ['key' => 'product', 'label' => 'Nama produk', 'type' => 'text'], ['key' => 'dosage', 'label' => 'Dosis per tangki (sertakan satuan)', 'type' => 'text'], ['key' => 'water_liters', 'label' => 'Volume air per tangki (liter)', 'type' => 'number', 'max' => 999999.99, 'step' => '0.01'], ['key' => 'tank_count', 'label' => 'Jumlah tangki', 'type' => 'number', 'max' => 32767, 'step' => '1'], $method],
            'harvest' => [$area, ['key' => 'weight_ton', 'label' => 'Berat panen (ton)', 'type' => 'number', 'max' => 99999999999.999, 'step' => '0.001'], ['key' => 'moisture', 'label' => 'Kadar air (%, opsional)', 'type' => 'number', 'max' => 100, 'step' => '0.01', 'optional' => true], $method],
            default => [],
        };

        return $fields;
    }

    public static function activityCode(PlantingCycleTask $task): ?string
    {
        return $task->activity_category === 'harvest' ? 'harvest' : match ($task->activity_subcategory) {
            'irrigation' => 'irrigation', 'weeding' => 'weeding', 'follow_up_fertilization' => 'fertilization',
            'growth_check' => 'growth_check', 'pest_control' => 'pesticide', default => null,
        };
    }
}
