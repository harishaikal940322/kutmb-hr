<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Gate::before(function (User $user, string $ability): bool {
            $accountIsActive = DB::table('users')
                ->where('id', $user->getAuthIdentifier())
                ->whereNotNull('username')
                ->where('status', 'ACTIVE')
                ->exists();

            if (! $accountIsActive) {
                return false;
            }

            return DB::table('user_roles')
                ->join('role_permissions', 'role_permissions.role_id', '=', 'user_roles.role_id')
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->where('user_roles.user_id', $user->getAuthIdentifier())
                ->where('permissions.code', $ability)
                ->exists();
        });

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
