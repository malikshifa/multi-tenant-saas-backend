<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Models\Tenant\User as TenantUser;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\CauserResolver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(
            PersonalAccessToken::class
        );

        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(5)->by(
                strtolower((string) $request->input('email')).'|'.$request->ip()
            ),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        ResetPassword::createUrlUsing(fn ($notifiable, string $token) => rtrim(config('app.frontend_url'), '/')
            .'/reset-password?token='.$token
            .'&email='.urlencode($notifiable->getEmailForPasswordReset()));

        VerifyEmail::createUrlUsing(fn ($notifiable) => URL::temporarySignedRoute(
            $notifiable instanceof TenantUser ? 'tenant.verification.verify' : 'verification.verify',
            now()->addMinutes(config('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        ));

        // Activity log's default causer resolver checks the "web" guard,
        // but this app never authenticates through it — every request is
        // either "central" or "tenant". Check both explicitly instead.
        app(CauserResolver::class)->resolveUsing(
            fn () => Auth::guard('central')->user() ?? Auth::guard('tenant')->user()
        );
    }
}
