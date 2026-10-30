<?php

namespace Tests\Feature\Auth;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BootstrapLocalAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_account_bootstrap_is_disabled_by_default(): void
    {
        $this->assertFalse((bool) config('iam.local_account_bootstrap_enabled'));

        $this->artisan('iam:bootstrap-local-account')
            ->expectsOutputToContain('Local account bootstrap is disabled')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_local_developer_account_is_created_active_with_a_hash_and_audit_entry(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        config(['iam.local_account_bootstrap_enabled' => true]);
        $password = 'synthetic local account password';

        $this->artisan('iam:bootstrap-local-account')
            ->expectsQuestion('Username', 'Local.Dev-1')
            ->expectsQuestion('Display name', 'Local Developer')
            ->expectsQuestion('Password', $password)
            ->expectsQuestion('Confirm password', $password)
            ->expectsOutputToContain('Local account created successfully. No role or permission was assigned.')
            ->doesntExpectOutput($password)
            ->assertExitCode(0);

        $user = User::query()->where('username_canonical', 'local.dev-1')->sole();

        $this->assertSame('Local.Dev-1', $user->username);
        $this->assertSame('Local Developer', $user->name);
        $this->assertSame('ACTIVE', $user->status);
        $this->assertNotSame($password, $user->password);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertDatabaseCount('user_roles', 0);
        $this->assertDatabaseCount('roles', 0);
        $this->assertDatabaseCount('permissions', 0);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => null,
            'action_code' => 'iam.local_account_bootstrap',
            'target_ref' => 'user:'.$user->id,
            'outcome' => 'SUCCESS',
        ]);

        $event = AuditEvent::query()->where('target_ref', 'user:'.$user->id)->sole();
        $this->assertSame(['source' => 'local_console'], (array) $event->metadata);
    }

    public function test_testing_environment_can_bootstrap_and_the_account_can_log_in(): void
    {
        config([
            'app.env' => 'testing',
            'iam.local_account_bootstrap_enabled' => true,
        ]);
        $password = 'synthetic testing account password';

        $this->artisan('iam:bootstrap-local-account')
            ->expectsQuestion('Username', 'testing.dev')
            ->expectsQuestion('Display name', 'Testing Developer')
            ->expectsQuestion('Password', $password)
            ->expectsQuestion('Confirm password', $password)
            ->assertExitCode(0);

        $this->post(route('login.store'), [
            'username' => 'TESTING.DEV',
            'password' => $password,
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs(User::query()->where('username_canonical', 'testing.dev')->sole());
    }

    public function test_production_execution_is_denied_even_when_opted_in(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['iam.local_account_bootstrap_enabled' => true]);

        $this->artisan('iam:bootstrap-local-account')
            ->expectsOutputToContain('only in local or testing environments')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_existing_canonical_username_is_rejected_without_modifying_the_account(): void
    {
        config([
            'app.env' => 'testing',
            'iam.local_account_bootstrap_enabled' => true,
        ]);
        $existing = User::factory()->create(['username' => 'Developer.One']);
        $existingHash = $existing->password;

        $this->artisan('iam:bootstrap-local-account')
            ->expectsQuestion('Username', 'DEVELOPER.ONE')
            ->expectsOutputToContain('An account with that username already exists')
            ->assertExitCode(1);

        $this->assertSame($existingHash, $existing->fresh()->password);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_invalid_username_is_rejected_without_creating_an_account(): void
    {
        $this->enableTestingBootstrap();

        $this->artisan('iam:bootstrap-local-account')
            ->expectsQuestion('Username', 'has spaces')
            ->expectsOutputToContain('Username is invalid')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_invalid_display_name_is_rejected(): void
    {
        $this->enableTestingBootstrap();

        $this->artisan('iam:bootstrap-local-account')
            ->expectsQuestion('Username', 'valid.user')
            ->expectsQuestion('Display name', '  ')
            ->expectsOutputToContain('Display name is invalid')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_short_password_is_rejected_without_disclosing_it(): void
    {
        $this->enableTestingBootstrap();
        $password = 'short-pass';

        $this->artisan('iam:bootstrap-local-account')
            ->expectsQuestion('Username', 'valid.user')
            ->expectsQuestion('Display name', 'Valid User')
            ->expectsQuestion('Password', $password)
            ->expectsQuestion('Confirm password', $password)
            ->expectsOutputToContain('Password is invalid')
            ->doesntExpectOutput($password)
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_password_confirmation_must_match_without_disclosing_either_value(): void
    {
        $this->enableTestingBootstrap();
        $password = 'synthetic local account password';
        $confirmation = 'different synthetic password';

        $this->artisan('iam:bootstrap-local-account')
            ->expectsQuestion('Username', 'valid.user')
            ->expectsQuestion('Display name', 'Valid User')
            ->expectsQuestion('Password', $password)
            ->expectsQuestion('Confirm password', $confirmation)
            ->expectsOutputToContain('confirmation does not match')
            ->doesntExpectOutput($password)
            ->doesntExpectOutput($confirmation)
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_command_does_not_define_password_arguments_or_options(): void
    {
        $command = Artisan::all()['iam:bootstrap-local-account'];

        $arguments = array_filter(
            $command->getDefinition()->getArguments(),
            fn ($argument): bool => $argument->getName() !== 'command',
        );
        $this->assertSame([], $arguments);
        $this->assertSame([], array_filter(
            $command->getDefinition()->getOptions(),
            fn ($option): bool => in_array($option->getName(), ['password', 'secret'], true),
        ));
    }

    private function enableTestingBootstrap(): void
    {
        config([
            'app.env' => 'testing',
            'iam.local_account_bootstrap_enabled' => true,
        ]);
    }
}
