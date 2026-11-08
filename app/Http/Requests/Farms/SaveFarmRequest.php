<?php

namespace App\Http\Requests\Farms;

use App\Models\Farm;
use App\Services\AdministrativeRegionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveFarmRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Region lookup validation must not turn a forbidden mutation into 422.
        // Reject these payloads early through the same Policy; the controller
        // remains the explicit authorization boundary before invoking services.
        if ($this->exists('district_code')) {
            $farm = $this->route('farm');

            return $farm instanceof Farm
                ? Gate::allows('update', $farm)
                : Gate::allows('create', [Farm::class, $this->user()?->currentTeam]);
        }

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
            'land_tenure' => [...$presence, Rule::in(['owned', 'rented', 'partnership'])],
            'total_area_ha' => [...$presence, 'numeric', 'decimal:0,4', 'min:0.0001', 'max:99999999.9999'],
            'owner_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('users', 'id')->where(fn ($query) => $query
                ->where('is_active', true)
                ->whereIn('id', fn ($members) => $members->select('user_id')->from('team_members')->where('team_id', $teamId))
                ->whereIn('role_id', fn ($roles) => $roles->select('id')->from('roles')->where('name', 'owner')))],
            'pic_user_id' => [$this->routeIs('farm-management.*') ? 'sometimes' : 'prohibited', 'nullable', 'uuid'],
            'target_volume_ton' => ['sometimes', 'nullable', 'numeric', 'decimal:0,3', 'between:0.001,999999999.999'],
            'commodity_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('commodities', 'id')],
            'default_sop_template_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('sop_templates', 'id')
                ->where(fn ($query) => $query->where('team_id', $teamId))],
            'created_by' => ['prohibited'],
            'team_id' => ['prohibited'],
            'assigned_farm_id' => ['prohibited'],
            'address' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'district' => ['sometimes', 'nullable', 'string', 'max:100'],
            'district_code' => ['sometimes', 'nullable', 'string', 'regex:/^\d{2}\.\d{2}\.\d{2}$/'],
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
            $commodityId = $this->exists('commodity_id') ? $this->input('commodity_id') : $farm?->getAttribute('commodity_id');
            $sopId = $this->exists('default_sop_template_id') ? $this->input('default_sop_template_id') : $farm?->default_sop_template_id;
            $team = $farm instanceof Farm ? $farm->team : $this->user()?->currentTeam;
            if (! $validator->errors()->has('commodity_id') && $this->filled('commodity_id') && $team && ! \App\Models\Commodity::visibleTo($this->user(), $team)->whereKey($commodityId)->exists()) {
                $validator->errors()->add('commodity_id', 'Komoditas tidak tersedia.');
            }
            if (! $validator->errors()->has('commodity_id') && ! $validator->errors()->has('default_sop_template_id') && $sopId && $team && ($this->exists('commodity_id') || $commodityId)) {
                $matches = \App\Models\SopTemplate::visibleTo($this->user(), $team)->whereKey($sopId)->where('commodity_id', $commodityId)->exists();
                if (! $matches) {
                    $validator->errors()->add('default_sop_template_id', 'Pilih SOP yang sesuai dengan komoditas kebun.');
                }
            }
            if ($this->exists('district_code')) {
                $code = $this->input('district_code');
                $team = $farm instanceof Farm ? $farm->team : $this->user()?->currentTeam;
                $region = is_string($code) && $team ? app(AdministrativeRegionService::class)->district($code, $this->user(), $team) : null;
                $hasNames = collect(['district', 'city', 'province'])->contains(fn ($key) => filled($this->input($key)));
                $unchangedLegacy = $farm && $farm->getAttribute('district_code') === null && ! $code
                    && collect(['district', 'city', 'province'])->every(fn ($key) => ($this->input($key) ?: null) === $farm->getAttribute($key));
                if ((! $region && ($code || $hasNames) && ! $unchangedLegacy)
                    || ($region && collect(['district', 'city', 'province'])->contains(fn ($key) => $this->input($key) !== $region[$key]))) {
                    $validator->errors()->add('district_code', 'Pilih kecamatan, kota/kabupaten, dan provinsi yang sesuai dari daftar wilayah.');
                }
            }
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
            'land_tenure.in' => 'Status lahan harus Milik sendiri, Sewa, atau Kerja sama.',
        ];
    }
}
