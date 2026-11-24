<?php

namespace App\Http\Requests\Auth;

use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class LoginRequest extends FortifyLoginRequest
{
    public function rules(): array
    {
        return array_replace(parent::rules(), ['email' => ['required', 'string', 'email']]);
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Masukkan alamat email Anda.',
            'email.string' => 'Masukkan alamat email yang valid.',
            'email.email' => 'Masukkan alamat email yang valid.',
            'password.required' => 'Masukkan kata sandi Anda.',
            'password.string' => 'Masukkan kata sandi yang valid.',
        ];
    }
}
