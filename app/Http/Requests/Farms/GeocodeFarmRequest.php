<?php

namespace App\Http\Requests\Farms;

use Illuminate\Foundation\Http\FormRequest;

class GeocodeFarmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The controller authorizes against the trusted team or farm.
    }

    public function rules(): array
    {
        return [
            'address' => ['required', 'string', 'min:3', 'max:5000'],
            'district_code' => ['required', 'string', 'regex:/^\d{2}\.\d{2}\.\d{2}$/'],
            'team_id' => ['prohibited'],
            'farm_id' => ['prohibited'],
            'url' => ['prohibited'],
        ];
    }
}
