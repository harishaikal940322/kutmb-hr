<?php

namespace App\Providers;

use App\Http\Requests\TwoFactorLoginRequest;
use App\Models\User;
use App\Support\Identity\Username;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use InvalidArgumentException;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest as FortifyTwoFactorLoginRequest;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FortifyTwoFactorLoginRequest::class, TwoFactorLoginRequest::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $submittedUsername = $request->input(Fortify::username());
            $password = $request->input('password');

            if (! is_string($submittedUsername) || ! is_string($password)) {
                return null;
            }

            try {
                $canonicalUsername = Username::canonicalize($submittedUsername);
            } catch (InvalidArgumentException) {
                return null;
            }

            $user = User::query()->where('username_canonical', $canonicalUsername)->first();

            if (! $user || $user->status !== 'ACTIVE' || ! Hash::check($password, $user->getAuthPassword())) {
                return null;
            }

            if (! config('iam.mfa_enabled') && Str::length($password) < config('iam.single_factor_password_minimum', 15)) {
                return null;
            }

            return $user;
        });

        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'status' => $request->session()->get('status'),
            'recoveryCodes' => $request->session()->pull('iam.recovery_codes'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));
        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));

        $this->configureRateLimiting();
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $submittedUsername = $request->input(Fortify::username());
            $throttleIdentity = is_string($submittedUsername) ? Str::lower($submittedUsername) : '';
            $throttleKey = $throttleIdentity.'|'.$request->ip();

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            $challengedUser = $request->session()->get('login.id', 'unknown');
            $throttleKey = $challengedUser.'|'.$request->ip();

            return Limit::perMinute(5)->by($throttleKey);
        });

    }
}
