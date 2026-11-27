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
        // Reject forbidden mutations before location validation; the controller
        // remains the explicit authorization boundary before invoking services.
        $farm = $this->route('farm');

        return $farm instanceof Farm
            ? Gate::allows('update', $farm)
            : Gate::allows('create', [Farm::class, $this->user()?->currentTeam]);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $farm = $this->route('farm');
        $teamId = $farm instanceof Farm ? $farm->team_id : $this->user()?->current_team_id;
        $presence = $this->isMethod('patch') ? ['sometimes', 'required'] : ['required'];
        $locationPresence = fn (string $field): array => $this->isMethod('patch') && $farm instanceof Farm && filled($farm->getAttribute($field))
            ? ['sometimes', 'required'] : ['required'];

        return [
            'name' => [...$presence, 'string', 'max:150'],
            'land_tenure' => [...$presence, Rule::in(['owned', 'rented', 'partnership'])],
            'land_tenure_party' => ['sometimes', 'nullable', 'string', 'max:255'],
            'total_area_ha' => [...$presence, 'numeric', 'decimal:0,4', 'min:0.0001', 'max:99999999.9999'],
            'owner_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('users', 'id')->where(fn ($query) => $query
                ->where('is_active', true)
                ->whereIn('id', fn ($members) => $members->select('user_id')->from('team_members')->where('team_id', $teamId))
                ->whereIn('role_id', fn ($roles) => $roles->select('id')->from('roles')->where('name', 'owner')))],
            'pic_user_id' => [$this->routeIs('farm-management.*') ? 'sometimes' : 'prohibited', 'nullable', 'uuid'],
            'target_volume_ton' => ['sometimes', 'nullable', 'numeric', 'decimal:0,3', 'between:0.001,999999999.999'],
            'commodity_id' => ['prohibited'],
            'default_sop_template_id' => ['prohibited'],
            'created_by' => ['prohibited'],
            'team_id' => ['prohibited'],
            'assigned_farm_id' => ['prohibited'],
            'address' => [...$locationPresence('address'), 'string', 'max:5000'],
            'city' => [...$locationPresence('city'), 'string', 'max:100'],
            'district' => [...$locationPresence('district'), 'string', 'max:100'],
            'district_code' => [...$locationPresence('district_code'), 'string', 'regex:/^\d{2}\.\d{2}\.\d{2}$/'],
            'province' => [...$locationPresence('province'), 'string', 'max:100'],
            'latitude' => [...$locationPresence('latitude'), 'numeric', 'decimal:0,7', 'between:-90,90'],
            'longitude' => [...$locationPresence('longitude'), 'numeric', 'decimal:0,7', 'between:-180,180'],
            'image_url' => ['prohibited'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:15360', Rule::prohibitedIf($this->boolean('remove_image'))],
            'remove_image' => ['sometimes', 'boolean'],
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
            $tenure = $this->input('land_tenure', $farm?->land_tenure);
            $party = $this->input('land_tenure_party', $this->isMethod('patch') ? $farm?->getAttribute('land_tenure_party') : null);
            $legalityChanged = ! $this->isMethod('patch') || $this->exists('land_tenure') || $this->exists('land_tenure_party');
            if ($legalityChanged && in_array($tenure, ['rented', 'partnership'], true) && blank($party)) {
                $validator->errors()->add('land_tenure_party', $tenure === 'rented'
                    ? 'Isi nama pemilik lahan yang disewa.'
                    : 'Isi nama pihak mitra kerja sama.');
            }
            if (collect(['district_code', 'district', 'city', 'province'])->contains(fn ($key) => $this->exists($key))) {
                $code = $this->input('district_code', $this->isMethod('patch') ? $farm?->getAttribute('district_code') : null);
                $team = $farm instanceof Farm ? $farm->team : $this->user()?->currentTeam;
                $region = is_string($code) && $team ? app(AdministrativeRegionService::class)->district($code, $this->user(), $team) : null;
                $hasNames = collect(['district', 'city', 'province'])->contains(fn ($key) => filled($this->input($key)));
                if ((! $region && ($code || $hasNames))
                    || ($region && collect(['district', 'city', 'province'])->contains(fn ($key) => $this->input($key, $this->isMethod('patch') ? $farm?->getAttribute($key) : null) !== $region[$key]))) {
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
            'province.required' => 'Pilih provinsi lokasi lahan.',
            'city.required' => 'Pilih kota/kabupaten setelah provinsi.',
            'district.required' => 'Pilih kecamatan setelah kota/kabupaten.',
            'district_code.required' => 'Lengkapi pilihan provinsi, kota/kabupaten, dan kecamatan dari daftar.',
            'address.required' => 'Isi alamat lengkap lokasi lahan.',
            'latitude.required' => 'Tentukan titik lokasi lahan di peta.',
            'longitude.required' => 'Tentukan titik lokasi lahan di peta.',
            'land_tenure.required' => 'Pilih status penguasaan lahan.',
            'land_tenure.in' => 'Status lahan harus Milik sendiri, Sewa, atau Kerja sama.',
            'land_tenure_party.string' => 'Nama pihak terkait harus berupa teks.',
            'land_tenure_party.max' => 'Nama pihak terkait maksimal 255 karakter.',
        ];
    }
}
