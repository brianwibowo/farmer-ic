<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/** Shared input contract; quantities retain their original units. */
class VoluntaryActivityFields
{
    /** @return list<array<string, mixed>> */
    public static function forCode(string $code): array
    {
        $n = fn ($key, $label, $optional = false, $step = '0.01', $max = 999999) => compact('key', 'label', 'optional', 'step', 'max') + ['type' => 'number'];
        $s = fn ($key, $label, $options, $optional = false) => compact('key', 'label', 'options', 'optional') + ['type' => 'select'];
        $t = fn ($key, $label, $optional = false) => compact('key', 'label', 'optional') + ['type' => 'text'];
        $area = [$n('area', 'Luas area', false, '0.0001'), $s('area_unit', 'Satuan luas', ['m²', 'Ha'])];

        return match ($code) {
            'land_cleaning', 'soil_preparation', 'base_fertilization', 'maintenance' => $area,
            'harvest' => [...$area, $n('weight', 'Berat seluruh hasil panen'), $s('weight_unit', 'Satuan berat', ['kg', 'ton'])],
            'incident' => [
                $s('incident_category', 'Kategori insiden', ['Hama', 'Penyakit', 'Cuaca', 'Banjir', 'Alat rusak', 'Tenaga kerja', 'Lainnya']),
                $t('description', 'Deskripsi kejadian'),
                ...$area,
                $s('severity', 'Tingkat kedaruratan', ['Ringan', 'Sedang', 'Darurat']),
                $s('incident_status', 'Status awal', ['Perlu penanganan', 'Sudah ditangani langsung']),
                $t('immediate_action', 'Tindakan segera', true) + ['required_when' => ['key' => 'incident_status', 'value' => 'Sudah ditangani langsung']],
            ],
            'other' => [$t('description', 'Deskripsi pekerjaan'), $n('area', 'Luas area', true, '0.0001'), $s('area_unit', 'Satuan luas', ['m²', 'Ha'], true)],
            default => [],
        };
    }

    /** @return array<string, mixed> */
    public static function rules(string $code): array
    {
        $fields = self::forCode($code);
        $rules = ['details' => ['required', 'array:'.implode(',', array_column($fields, 'key'))]];
        foreach ($fields as $field) {
            $requiredWhen = $field['required_when'] ?? null;
            $checks = is_array($requiredWhen)
                ? ['required_if:details.'.$requiredWhen['key'].','.$requiredWhen['value'], 'nullable']
                : [$field['optional'] ? 'nullable' : 'required'];
            if ($field['type'] === 'number') {
                $checks = [...$checks, $field['step'] === '1' ? 'integer' : 'numeric', 'min:'.$field['step'], 'max:'.$field['max']];
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

    /** @param array<string, mixed> $details
     * @return array<string, mixed>
     */
    public static function normalized(array $details): array
    {
        if (isset($details['area'])) {
            $details['area_ha'] = round((float) $details['area'] / (($details['area_unit'] ?? 'm²') === 'm²' ? 10000 : 1), 4);
        }
        if (isset($details['weight'])) {
            $details['weight_ton'] = round($details['weight'] / ($details['weight_unit'] === 'kg' ? 1000 : 1), 3);
        }

        return $details;
    }
}
