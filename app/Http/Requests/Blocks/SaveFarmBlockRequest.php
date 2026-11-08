<?php

namespace App\Http\Requests\Blocks;

use App\Support\LandArea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveFarmBlockRequest extends FormRequest
{
    private bool $conflictingArea = false;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
        if ($this->exists('area_value') || $this->exists('area_unit')) {
            $this->conflictingArea = $this->exists('area_ha');
            $unit = $this->input('area_unit');
            $metres = is_string($unit) ? LandArea::squareMetres($this->input('area_value'), $unit) : null;
            $this->merge(['area_ha' => $metres === null ? null : LandArea::hectares($metres)]);
            if (is_string($this->input('area_value'))) {
                $this->merge(['area_value' => str_replace(',', '.', trim($this->input('area_value')))]);
            }
        }
    }

    public function authorize(): bool
    {
        return true; // The controller checks the policy before calling the service.
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $presence = $this->isMethod('patch') ? ['sometimes', 'required'] : ['required'];
        $unitInput = $this->exists('area_value') || $this->exists('area_unit');
        $metres = $this->input('area_unit') === 'm2';

        return [
            'code' => [...$presence, 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/'],
            'name' => [...$presence, 'string', 'max:150'],
            'area_ha' => [...$presence, 'numeric', 'decimal:0,4', 'between:0.0001,99999999.9999'],
            'area_value' => [Rule::requiredIf($unitInput), 'numeric', $metres ? 'decimal:0' : 'decimal:0,4', $metres ? 'between:1,999999999999' : 'between:0.0001,99999999.9999'],
            'area_unit' => [Rule::requiredIf($unitInput), Rule::in(['ha', 'm2'])],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            ...array_fill_keys(['id', 'farm_id', 'team_id', 'created_by', 'created_at', 'updated_at', 'status', 'current_stage', 'land_clearing_started_at', 'land_clearing_completed_at'], ['prohibited']),
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->conflictingArea) {
                $validator->errors()->add('area_value', 'Kirim satu nilai luas beserta satuannya.');
            }
            if (($this->exists('area_value') || $this->exists('area_unit')) && $this->input('area_ha') === null) {
                $validator->errors()->add('area_value', 'Masukkan luas dalam ha (maksimal 4 desimal) atau m² bulat. Contoh: 0,5 ha atau 5000 m².');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'area_value.required' => 'Isi luas blok.',
            'area_value.numeric' => 'Masukkan angka luas tanpa pemisah ribuan.',
            'area_value.decimal' => 'Gunakan maksimal 4 desimal untuk ha atau bilangan bulat untuk m².',
            'area_value.between' => 'Luas minimal 0,0001 ha atau 1 m² dan tidak boleh melebihi batas penyimpanan.',
            'area_unit.required' => 'Pilih satuan luas ha atau m².',
            'area_unit.in' => 'Satuan luas harus ha atau m².',
        ];
    }
}
