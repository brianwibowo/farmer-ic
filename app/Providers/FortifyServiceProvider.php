<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Responses\LoginResponse;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Services\RememberedDeviceService;
use App\Support\RolePermissions;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\Laravel\Fortify\Actions\AttemptToAuthenticate::class, \App\Actions\Fortify\AttemptToAuthenticate::class);
        $this->app->bind(\Laravel\Fortify\Http\Requests\LoginRequest::class, \App\Http\Requests\Auth\LoginRequest::class);
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->bind(\Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::class, AuthenticatedSessionController::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureEmails();
        $this->configureViews();
        $this->configureRateLimiting();
        Event::listen(Login::class, function (Login $event): void {
            $request = request();
            if (! $request->routeIs('login.store') || ! $event->user instanceof User) {
                return;
            }
            $guard = Auth::guard('web');
            Cookie::unqueue($guard->getRecallerName());
            Cookie::queue(Cookie::forget($guard->getRecallerName()));
            $event->user->forceFill(['remember_token' => Str::random(60)])->save();
            if (app(RolePermissions::class)->roleName($event->user) === 'owner' || $event->remember) {
                app(RememberedDeviceService::class)->issue($event->user, $request);
            }
        });
    }

    private function configureEmails(): void
    {
        ResetPassword::toMailUsing(function ($notifiable, string $token): MailMessage {
            $url = ResetPassword::$createUrlCallback
                ? (ResetPassword::$createUrlCallback)($notifiable, $token)
                : url(route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], false));
            $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject('Atur ulang kata sandi Anda')
                ->line('Anda menerima email ini karena kami menerima permintaan untuk mengatur ulang kata sandi akun Anda.')
                ->action('Atur Ulang Kata Sandi', $url)
                ->line("Tautan pengaturan ulang kata sandi ini berlaku selama {$minutes} menit.")
                ->line('Jika Anda tidak meminta pengaturan ulang kata sandi, Anda tidak perlu melakukan tindakan apa pun.');
        });

        VerifyEmail::toMailUsing(fn ($notifiable, string $url): MailMessage => (new MailMessage)
            ->subject('Verifikasi alamat email Anda')
            ->line('Klik tombol di bawah ini untuk memverifikasi alamat email Anda.')
            ->action('Verifikasi Alamat Email', $url)
            ->line('Jika Anda tidak membuat akun, Anda tidak perlu melakukan tindakan apa pun.'));
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'turnstileSiteKey' => config('services.turnstile.site_key'),
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
            'teamInvitation' => $this->teamInvitation($request),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'turnstileSiteKey' => config('services.turnstile.site_key'),
            'status' => $request->session()->get('status'),
        ]));

    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey)->response(function (Request $request, array $headers) {
                $seconds = (int) $headers['Retry-After'];
                $message = "Terlalu banyak percobaan masuk. Coba lagi dalam {$seconds} detik.";

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => $message,
                        'errors' => ['login' => [$message]],
                    ], 429, $headers);
                }

                return redirect()->route('login')
                    ->withErrors(['login' => $message])
                    ->withInput($request->only('email', 'remember'));
            });
        });

    }

    /**
     * Get the pending team invitation context for auth pages.
     *
     * @return array{code: string, teamName: string}|null
     */
    private function teamInvitation(Request $request): ?array
    {
        $invitationCode = $request->query('invitation');

        if (! is_string($invitationCode)) {
            return null;
        }

        $invitation = TeamInvitation::query()
            ->with('team')
            ->where('code', $invitationCode)
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->first();

        if (! $invitation) {
            return null;
        }

        return [
            'code' => $invitation->code,
            'teamName' => $invitation->team->name,
        ];
    }
}
