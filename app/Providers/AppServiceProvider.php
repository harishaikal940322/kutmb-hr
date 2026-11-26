<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;
use SocialiteProviders\Keycloak\KeycloakExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

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
        Event::listen(SocialiteWasCalled::class, [KeycloakExtendSocialite::class, 'handle']);
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Gate::before(function (?User $user): ?bool {
            if ($user === null) {
                return false;
            }

            $accountIsActive = DB::table('users')
                ->where('id', $user->getAuthIdentifier())
                ->whereNotNull('username')
                ->where('status', 'ACTIVE')
                ->exists();

            return $accountIsActive ? null : false;
        });

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(! $this->isDisposableDatabase());

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if (! in_array($event->command, ['db:wipe', 'migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback'], true)) {
                return;
            }

            $connection = $event->input->getParameterOption('--database', null);
            if (! $this->isDisposableDatabase(is_string($connection) ? $connection : null)) {
                throw new RuntimeException('Destructive database commands require the configured disposable test database.');
            }
        });

        Password::defaults(fn (): Password => Password::min(15));
    }

    private function isDisposableDatabase(?string $name = null): bool
    {
        $name ??= config('database.default');
        $connection = config('database.connections.'.$name);

        return app()->environment('testing')
            && is_array($connection)
            && ($connection['driver'] ?? null) === 'mysql'
            && ($connection['database'] ?? null) === 'kutmb_hr_test'
            && in_array($connection['host'] ?? null, ['127.0.0.1', 'wp040-mysql-test'], true)
            && empty($connection['url'])
            && empty($connection['unix_socket'])
            && ! isset($connection['read'])
            && ! isset($connection['write']);
    }
}
