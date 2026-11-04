<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/** Shared input contract; quantities retain their original units. */
class VoluntaryActivityFields
{
    public static function forCode(string $code): array
    {
        $n = fn ($key, $label, $optional = false, $step = '0.01', $max = 999999) => compact('key', 'label', 'optional', 'step', 'max') + ['type' => 'number'];
        $s = fn ($key, $label, $options, $optional = false) => compact('key', 'label', 'options', 'optional') + ['type' => 'select'];
        $t = fn ($key, $label, $optional = false) => compact('key', 'label', 'optional') + ['type' => 'text'];
        $area = [$n('area', 'Luas area', false, '0.0001'), $s('area_unit', 'Satuan luas', ['m²', 'Ha'])];
        $quantity = [$n('quantity', 'Jumlah'), $s('quantity_unit', 'Satuan jumlah', $code === 'follow_up_fertilization' ? ['kg', 'sak', 'liter'] : ['kg', 'sak', 'ton']), $n('bag_size', 'Berat per sak (kg)') + ['when' => ['quantity_unit', 'sak']]];
        $method = fn ($options, $optional = false) => $s('method', 'Metode', $options, $optional);
        $spray = [$t('product', 'Nama produk'), $n('dosage', 'Dosis (ml per liter air)'), $n('water_liters', 'Volume air per tangki (liter)'), $n('tank_count', 'Jumlah tangki', false, '1', 32767)];

        return match ($code) {
            'land_cleaning' => [...$area, $method(['Manual', 'Herbisida', 'Lainnya']), ...array_map(fn ($f) => $f + ['when' => ['method', 'Herbisida']], $spray)],
            'loosening' => [...$area, $method(['Manual', 'Cultivator', 'Lainnya']), $n('depth_cm', 'Kedalaman (cm)')],
            'beds' => [$n('bed_count', 'Jumlah bedeng', false, '1', 32767), $n('length_m', 'Panjang bedeng (m)'), $n('width_m', 'Lebar bedeng (m)'), $n('height_cm', 'Tinggi bedeng (cm)', true), $n('spacing_cm', 'Jarak antar bedeng (cm)', true)],
            'liming' => [...$area, $s('material', 'Jenis material', ['Dolomit', 'Kapur pertanian', 'Lainnya']), $t('brand', 'Merek', true), ...$quantity, $method(['Tabur', 'Cover', 'Lainnya'])],
            'base_fertilization', 'follow_up_fertilization' => [...$area, $s('fertilizer', 'Jenis pupuk', $code === 'base_fertilization' ? ['Kandang', 'Kompos', 'Lainnya'] : ['Urea', 'NPK', 'KCl', 'Lainnya']), $t('brand', 'Merek', true), ...$quantity, $method(['Tabur', 'Benam', 'Kocor', 'Lainnya'])],
            'irrigation' => [...$area, $s('water_source', 'Sumber air', ['Sumur bor', 'Irigasi', 'Embung', 'Lainnya']), $method(['Irigasi parit', 'Sprinkler', 'Pompa', 'Lainnya']), $t('pump', 'Mesin pompa') + ['when' => ['method', 'Pompa']], $n('duration_hours', 'Durasi (jam)')],
            'weeding' => [...$area, $method(['Manual', 'Cangkul', 'Mesin', 'Lainnya']), $n('depth_cm', 'Kedalaman (cm)', true)],
            'growth_check' => [$s('phase', 'Fase pertumbuhan', ['Vegetatif', 'Pembungaan', 'Pengisian', 'Pemasakan', 'Lainnya']), $n('height_cm', 'Tinggi rata-rata (cm)'), $n('sample_count', 'Jumlah sampel', true, '1', 32767), $s('pest', 'Indikasi hama', ['Aman', 'Ringan', 'Sedang', 'Berat']), $s('disease', 'Indikasi penyakit', ['Aman', 'Ringan', 'Sedang', 'Berat'])],
            'pest_control' => [...$area, $s('control_type', 'Jenis pengendalian', ['Insektisida', 'Fungisida', 'Pestisida', 'Lainnya']), $s('target', 'Target', ['Hama', 'Penyakit', 'Gulma', 'Lainnya']), ...$spray, $method(['Merata', 'Spot', 'Lainnya'])],
            'harvest' => [...$area, $n('weight', 'Berat seluruh hasil panen'), $s('weight_unit', 'Satuan berat', ['kg', 'ton']), $n('moisture', 'Kadar air (%)', true, '0.01', 100), $method(['Manual', 'Mesin', 'Lainnya'], true)],
            'incident' => [$s('incident_category', 'Kategori insiden', ['Hama', 'Penyakit', 'Cuaca', 'Banjir', 'Alat rusak', 'Tenaga kerja', 'Lainnya']), $t('description', 'Deskripsi kejadian'), ...$area, $s('severity', 'Tingkat kedaruratan', ['Ringan', 'Sedang', 'Berat', 'Darurat']), $t('immediate_action', 'Tindakan segera', true), $s('incident_status', 'Status insiden', ['Open', 'In Progress', 'Resolved'], true)],
            'other' => [$t('description', 'Deskripsi pekerjaan'), $n('area', 'Luas area', true, '0.0001'), $s('area_unit', 'Satuan luas', ['m²', 'Ha'], true)],
            default => [],
        };
    }

    public static function rules(string $code): array
    {
        $fields = self::forCode($code);
        $rules = ['details' => ['required', 'array:'.implode(',', array_column($fields, 'key'))]];
        foreach ($fields as $field) {
            $checks = [];
            if (isset($field['when'])) {
                $checks[] = 'exclude_unless:details.'.$field['when'][0].','.$field['when'][1];
            }
            $checks[] = $field['optional'] ? 'nullable' : 'required';
            if ($field['type'] === 'number') {
                $checks = [...$checks, $field['step'] === '1' ? 'integer' : 'numeric', 'min:'.($field['key'] === 'moisture' ? 0 : $field['step']), 'max:'.$field['max']];
            } else {
                $checks = [...$checks, 'string', 'max:'.(in_array($field['key'], ['description', 'immediate_action']) ? 5000 : 100)];
                if ($field['type'] === 'select') {
                    $checks[] = Rule::in($field['options']);
                }
            }
            $rules['details.'.$field['key']] = $checks;
            if ($field['key'] === 'area') {
                $rules['details.area'][] = Rule::when(fn ($input) => data_get($input, 'details.area_unit') === 'm²', ['integer', 'min:1'], ['decimal:0,4']);
            }
        }
        if ($code === 'other') {
            $rules['details.area_unit'][] = 'required_with:details.area';
        }

        return $rules;
    }

    public static function normalized(array $details): array
    {
        if (isset($details['area'])) {
            $details['area_ha'] = round((float) $details['area'] / (($details['area_unit'] ?? 'm²') === 'm²' ? 10000 : 1), 4);
        }
        if (isset($details['bed_count'])) {
            $details['area_ha'] = round($details['bed_count'] * $details['length_m'] * $details['width_m'] / 10000, 4);
        }
        if (isset($details['quantity'])) {
            $details['quantity_kg'] = match ($details['quantity_unit']) {
                'kg' => (float) $details['quantity'], 'sak' => $details['quantity'] * $details['bag_size'], 'ton' => $details['quantity'] * 1000, default => null,
            };
        }
        if (isset($details['weight'])) {
            $details['weight_ton'] = round($details['weight'] / ($details['weight_unit'] === 'kg' ? 1000 : 1), 3);
        }

        return $details;
    }
}
