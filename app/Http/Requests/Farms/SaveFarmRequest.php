<?php

namespace App\Http\Requests\Farms;

use App\Models\Farm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveFarmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The controller is the HTTP authorization boundary.
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $farm = $this->route('farm');
        $teamId = $farm instanceof Farm ? $farm->team_id : $this->user()?->current_team_id;
        $presence = $this->isMethod('patch') ? ['sometimes', 'required'] : ['required'];

        return [
            'name' => [...$presence, 'string', 'max:150'],
            'land_tenure' => [...$presence, Rule::in(['owned', 'rented'])],
            'total_area_ha' => [...$presence, 'numeric', 'decimal:0,4', 'min:0.0001', 'max:99999999.9999'],
            'owner_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('users', 'id')->where(fn ($query) => $query
                ->where('is_active', true)
                ->whereIn('id', fn ($members) => $members->select('user_id')->from('team_members')->where('team_id', $teamId))
                ->whereIn('role_id', fn ($roles) => $roles->select('id')->from('roles')->where('name', 'owner')))],
            'created_by' => ['prohibited'],
            'team_id' => ['prohibited'],
            'assigned_farm_id' => ['prohibited'],
            'address' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'district' => ['sometimes', 'nullable', 'string', 'max:100'],
            'province' => ['sometimes', 'nullable', 'string', 'max:100'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'decimal:0,7', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'decimal:0,7', 'between:-180,180'],
            'image_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'id' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $parameter = $this->route('farm');
            $farm = $parameter instanceof Farm ? $parameter : null;
            $latitude = $this->exists('latitude') ? $this->input('latitude') : $farm?->latitude;
            $longitude = $this->exists('longitude') ? $this->input('longitude') : $farm?->longitude;
            if (($latitude === null) !== ($longitude === null)) {
                $validator->errors()->add('latitude', 'Latitude dan longitude harus diisi atau dikosongkan bersamaan.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'land_tenure.required' => 'Pilih status penguasaan lahan.',
            'land_tenure.in' => 'Status lahan harus Milik sendiri atau Sewa.',
        ];
    }
}
