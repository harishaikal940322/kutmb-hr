<?php

namespace Tests\Feature\Auth;

use App\Models\AuditEvent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRecoveryCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class LocalSystemAdminBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_requires_local_opt_in_and_an_empty_user_table(): void
    {
        config(['iam.local_account_bootstrap_enabled' => false]);
        $this->get(route('register'))->assertNotFound();
        $this->get(route('register.mfa.qr-code'))->assertNotFound();

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
        $this->assertDatabaseCount('legacy_user_roles', 0);
    }

    public function test_bootstrap_qr_is_private_session_bound_and_removed_after_first_account_creation(): void
    {
        config(['iam.local_account_bootstrap_enabled' => true]);
        $page = $this->get(route('register'))->assertOk();
        $secret = decrypt(session('iam.bootstrap_mfa_secret'));

        $page->assertDontSee($secret);

        $qrCode = $this->get(route('register.mfa.qr-code'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('<svg', false);

        $this->assertStringContainsString('no-store', (string) $qrCode->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $qrCode->headers->get('Cache-Control'));
        $this->assertStringNotContainsString($secret, $qrCode->getContent());
        $this->assertStringNotContainsString('otpauth://', $qrCode->getContent());

        User::factory()->create();

        $this->get(route('register.mfa.qr-code'))->assertNotFound();
    }

    public function test_first_registration_creates_an_active_system_admin_and_audit_event(): void
    {
        config(['iam.local_account_bootstrap_enabled' => true]);
        $this->get(route('register'))->assertOk();
        $secret = decrypt(session('iam.bootstrap_mfa_secret'));
        $password = 'Abc12345';

        $this->post(route('register.store'), [
            ...$this->validAccountPayload($password),
            'mfa_code' => app(Google2FA::class)->getCurrentOtp($secret),
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'System Administrator account created with multi-factor authentication. Save the one-time recovery codes below.')
            ->assertSessionHas('iam.recovery_codes');

        $user = User::query()->where('username_canonical', 'first.admin')->sole();
        $role = Role::query()->where('code', 'system-admin')->sole();

        $this->assertSame('ACTIVE', $user->status);
        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertTrue($user->hasEnabledTwoFactorAuthentication());
        $this->assertNotNull($user->two_factor_secret);
        $this->assertSame(10, UserRecoveryCode::query()->where('user_id', $user->getKey())->count());
        $this->assertTrue($user->roles()->whereKey($role->getKey())->exists());
        $this->assertDatabaseCount('model_has_roles', 1);
        $this->assertTrue($role->hasPermissionTo('iam.rbac.manage'));
        $this->assertDatabaseHas('role_has_permissions', [
            'role_id' => $role->id,
            'permission_id' => Permission::findByName('iam.rbac.manage', 'web')->id,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => null,
            'action_code' => 'iam.local_system_admin_bootstrap',
            'target_ref' => 'user:'.$user->getKey(),
            'outcome' => 'SUCCESS',
        ]);

        $event = AuditEvent::query()
            ->where('target_ref', 'user:'.$user->getKey())
            ->where('action_code', 'iam.local_system_admin_bootstrap')
            ->sole();
        $this->assertSame(['source' => 'local_web'], (array) $event->metadata);

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => $password,
        ])->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
    }

    public function test_bootstrap_route_is_unavailable_in_production_even_when_opted_in(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['iam.local_account_bootstrap_enabled' => true]);

        $this->get(route('register'))->assertNotFound();
        $this->get(route('register.mfa.qr-code'))->assertNotFound();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('roles', 0);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_invalid_username_short_password_and_confirmation_are_rejected(): void
    {
        config(['iam.local_account_bootstrap_enabled' => true]);
        $valid = $this->validAccountPayload();
        $this->get(route('register'));
        $secret = decrypt(session('iam.bootstrap_mfa_secret'));
        $validMfa = ['mfa_code' => app(Google2FA::class)->getCurrentOtp($secret)];

        $this->post(route('register.store'), [...$valid, ...$validMfa, 'username' => 'not valid'])
            ->assertSessionHasErrors('username');

        $this->post(route('register.store'), [...$valid, ...$validMfa, 'password' => 'short-7', 'password_confirmation' => 'short-7'])
            ->assertSessionHasErrors('password');

        $this->post(route('register.store'), [...$valid, ...$validMfa, 'password_confirmation' => 'different confirmation password'])
            ->assertSessionHasErrors('password');

        $this->post(route('register.store'), [...$valid, ...$validMfa, 'mfa_code' => '000000'])
            ->assertSessionHasErrors('mfa_code');

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
