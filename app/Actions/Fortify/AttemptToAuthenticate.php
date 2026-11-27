<?php

namespace App\Actions\Fortify;

use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\AttemptToAuthenticate as FortifyAttemptToAuthenticate;

class AttemptToAuthenticate extends FortifyAttemptToAuthenticate
{
    protected function throwFailedAuthenticationException($request)
    {
        $this->limiter->increment($request);

        throw ValidationException::withMessages([
            'login' => 'Email atau kata sandi salah. Periksa kembali dan coba lagi.',
        ]);
    }
}
