<?php

namespace App\Http\Controllers\Auth;

use App\Services\RememberedDeviceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController as FortifyController;

class AuthenticatedSessionController extends FortifyController
{
    public function destroy(Request $request): LogoutResponse
    {
        $user = $request->user();
        app(RememberedDeviceService::class)->revokeCurrent($request, $user);
        Auth::guard('web')->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return app(LogoutResponse::class);
    }
}
