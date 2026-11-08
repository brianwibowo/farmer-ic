<?php

namespace App\Http\Requests\Blocks;

use App\Support\LandArea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePreparationBatchRequest extends FormRequest
{
    private bool $conflictingArea = false;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('subblock_label'))) {
            $this->merge(['subblock_label' => strtoupper(trim($this->input('subblock_label')))]);
        }
        if ($this->exists('area_value') || $this->exists('area_unit')) {
            $this->conflictingArea = $this->exists('reserved_area_ha');
            $unit = $this->input('area_unit');
            $squareMetres = is_string($unit) ? LandArea::squareMetres($this->input('area_value'), $unit) : null;
            $this->merge(['reserved_area_ha' => $squareMetres === null ? null : LandArea::hectares($squareMetres)]);
            if (is_string($this->input('area_value'))) {
                $this->merge(['area_value' => str_replace(',', '.', trim($this->input('area_value')))]);
            }
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $unitInput = $this->exists('area_value') || $this->exists('area_unit');
        $metres = $this->input('area_unit') === 'm2';

        return [
            'return_to' => ['sometimes', 'nullable', Rule::in(['farm', 'blocks'])],
            'area_value' => [Rule::requiredIf($unitInput), 'numeric', $metres ? 'decimal:0' : 'decimal:0,4', $metres ? 'between:1,999999999999' : 'between:0.0001,99999999.9999'],
            'area_unit' => [Rule::requiredIf($unitInput), Rule::in(['ha', 'm2'])],
            'reserved_area_ha' => [$unitInput ? 'nullable' : 'required', 'numeric', 'decimal:0,4', 'between:0.0001,99999999.9999'],
            'subblock_label' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/'],
            'planned_planting_at' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            ...array_fill_keys(['id', 'farm_id', 'team_id', 'block_id', 'planting_round_id', 'batch_number', 'created_by', 'status', 'current_stage', 'planted_at', 'planted_area_ha', 'variety_id', 'stages', 'created_at'], ['prohibited']),
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->conflictingArea) {
                $validator->errors()->add('area_value', 'Kirim satu nilai luas beserta satuannya.');
            }
            if (($this->exists('area_value') || $this->exists('area_unit')) && $this->input('reserved_area_ha') === null) {
                $validator->errors()->add('area_value', 'Masukkan luas dalam ha (maksimal 4 desimal) atau m² bulat. Contoh: 0,5 ha atau 5000 m².');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'area_value.required' => 'Isi luas yang akan dialokasikan untuk batch.',
            'area_value.numeric' => 'Masukkan angka luas tanpa pemisah ribuan.',
            'area_value.decimal' => 'Gunakan maksimal 4 desimal untuk ha atau bilangan bulat untuk m².',
            'area_value.between' => 'Luas minimal 0,0001 ha atau 1 m² dan tidak boleh melebihi batas penyimpanan.',
            'area_unit.required' => 'Pilih satuan luas ha atau m².',
            'area_unit.in' => 'Satuan luas harus ha atau m².',
        ];
    }
}
