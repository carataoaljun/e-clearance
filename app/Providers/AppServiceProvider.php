<?php

namespace App\Providers;

use App\Models\StudentAccount;
use App\Models\StudentSubmission;
use App\Policies\StudentAccountPolicy;
use App\Policies\StudentSubmissionPolicy;
use App\Support\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(StudentAccount::class, StudentAccountPolicy::class);
        Gate::policy(StudentSubmission::class, StudentSubmissionPolicy::class);

        RateLimiter::for('login', function (Request $request) {
            $identifier = $request->input('email')
                ?? $request->input('login')
                ?? $request->input('student_id')
                ?? 'unknown';

            return [
                Limit::perMinute(5)->by(Str::lower((string) $identifier).'|'.$request->ip()),
                Limit::perHour(30)->by('login-hour|'.$request->ip()),
            ];
        });

        RateLimiter::for('otp-send', fn (Request $request) => [
            Limit::perMinute(3)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perHour(10)->by('otp-send|'.$request->ip()),
        ]);

        RateLimiter::for('otp-verify', fn (Request $request) => [
            Limit::perMinute(10)->by('otp-verify|'.$request->ip()),
            Limit::perHour(30)->by('otp-verify-hour|'.$request->ip()),
        ]);

        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perHour(15)->by('password-reset|'.$request->ip()),
        ]);

        RateLimiter::for('uploads', function (Request $request) {
            $authenticatedIdentity = collect(['student', 'admin', 'instructor', 'registrar'])
                ->first(function (string $guard): bool {
                    return Auth::guard($guard)->check();
                });
            $userKey = $authenticatedIdentity !== null
                ? $authenticatedIdentity.'|'.Auth::guard($authenticatedIdentity)->id()
                : 'session|'.$request->session()->getId();

            return [
                Limit::perMinute(10)->by('uploads|'.$userKey),
                Limit::perHour(40)->by('uploads-ip|'.$request->ip()),
            ];
        });

        Event::listen(Login::class, fn (Login $event) => AuditLogger::record(
            'authentication.login',
            $event->guard,
            $event->user,
        ));

        Event::listen(Logout::class, fn (Logout $event) => AuditLogger::record(
            'authentication.logout',
            $event->guard,
            $event->user,
        ));

        Event::listen(Failed::class, function (Failed $event): void {
            $identifier = collect($event->credentials)
                ->except(['password', 'password_confirmation'])
                ->first();

            AuditLogger::record('authentication.failed', $event->guard, null, null, null, [
                'guard' => $event->guard,
                'identifier_hash' => is_scalar($identifier)
                    ? hash('sha256', Str::lower((string) $identifier))
                    : null,
            ]);
        });

        // Components are organized by portal in separate folders:
        // - resources/views/mainAdmin/components/ (for MainAdmin portal)
        // - resources/views/instructor/components/ (for Instructor portal)
        // - resources/views/components/ (for shared components)
        //
        // Blade auto-discovers components from these locations
    }
}
