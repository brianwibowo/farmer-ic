<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->user()->id),
            'phone_number' => ['sometimes', 'nullable', 'string', 'regex:/^[1-9][0-9]{7,14}$/D'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('phone_number')) {
            $this->merge(['phone_number' => PhoneNumber::normalize($this->input('phone_number'))]);
        }
    }
}
