<?php

namespace App\Http\Middleware;

use App\Services\RememberedDeviceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateRememberedDevice
{
    public function __construct(private RememberedDeviceService $devices) {}

    public function handle(Request $request, Closure $next): Response
    {
        $devices = $this->devices;
        $guard = Auth::guard('web');
        $user = $guard->user();
        if ($user && $guard->viaRemember()) {
            $guard->logoutCurrentDevice();
            $request->session()->invalidate();
            $user = null;
        }
        $deviceUser = $devices->resolve($request);
        $boundId = $request->session()->get(RememberedDeviceService::SESSION);
        if ($user && $boundId && (! is_string($boundId) || ! $devices->sessionDeviceIsValid($boundId, $user))) {
            $guard->logoutCurrentDevice();
            $request->session()->invalidate();
            $user = null;
            $deviceUser = null;
        }
        if (! $user && $deviceUser) {
            $guard->login($deviceUser);
            $request->session()->put(RememberedDeviceService::SESSION, explode('|', $request->cookie(RememberedDeviceService::COOKIE), 2)[0]);
            $user = $deviceUser;
        }
        if ($user && $deviceUser && $deviceUser->is($user)) {
            if (! $request->session()->has(RememberedDeviceService::SESSION)) {
                $request->session()->put(RememberedDeviceService::SESSION, explode('|', $request->cookie(RememberedDeviceService::COOKIE), 2)[0]);
            }
            $devices->refresh($request, $user);
        }

        return $next($request);
    }
}
