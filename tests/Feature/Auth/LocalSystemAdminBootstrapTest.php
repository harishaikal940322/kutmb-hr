<?php

namespace Tests\Feature\Auth;

use App\Models\AuditEvent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocalSystemAdminBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_requires_local_opt_in_and_an_empty_user_table(): void
    {
        config(['iam.local_account_bootstrap_enabled' => false]);
        $this->get(route('register'))->assertNotFound();

        config(['iam.local_account_bootstrap_enabled' => true]);
        $this->get(route('register'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Register')
            );

        User::factory()->create();

        $this->get(route('register'))->assertRedirect(route('login'));
        $this->post(route('register.store'), $this->validAccountPayload())
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('user_roles', 0);
    }

    public function test_first_registration_creates_an_active_system_admin_and_audit_event(): void
    {
        config(['iam.local_account_bootstrap_enabled' => true]);
        $password = 'synthetic initial administrator password';

        $this->post(route('register.store'), $this->validAccountPayload($password))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'System Administrator account created. You can now log in.');

        $user = User::query()->where('username_canonical', 'first.admin')->sole();
        $role = Role::query()->where('code', 'system-admin')->sole();

        $this->assertSame('ACTIVE', $user->status);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertTrue($user->roles()->whereKey($role->getKey())->exists());
        $this->assertDatabaseCount('user_roles', 1);
        $this->assertDatabaseCount('role_permissions', 0);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => null,
            'action_code' => 'iam.local_system_admin_bootstrap',
            'target_ref' => 'user:'.$user->getKey(),
            'outcome' => 'SUCCESS',
        ]);

        $event = AuditEvent::query()->where('target_ref', 'user:'.$user->getKey())->sole();
        $this->assertSame(['source' => 'local_web'], (array) $event->metadata);
    }

    public function test_bootstrap_route_is_unavailable_in_production_even_when_opted_in(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['iam.local_account_bootstrap_enabled' => true]);

        $this->get(route('register'))->assertNotFound();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('roles', 0);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_invalid_username_short_password_and_confirmation_are_rejected(): void
    {
        config(['iam.local_account_bootstrap_enabled' => true]);
        $valid = $this->validAccountPayload();

        $this->post(route('register.store'), [...$valid, 'username' => 'not valid'])
            ->assertSessionHasErrors('username');

        $this->post(route('register.store'), [...$valid, 'password' => 'short-password', 'password_confirmation' => 'short-password'])
            ->assertSessionHasErrors('password');

        $this->post(route('register.store'), [...$valid, 'password_confirmation' => 'different confirmation password'])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('roles', 0);
        $this->assertDatabaseCount('audit_events', 0);
    }

    /** @return array{username: string, name: string, password: string, password_confirmation: string} */
    private function validAccountPayload(string $password = 'synthetic initial administrator password'): array
    {
        return [
            'username' => 'First.Admin',
            'name' => 'Initial System Administrator',
            'password' => $password,
            'password_confirmation' => $password,
        ];
    }
}
