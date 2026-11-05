<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\UserRecoveryCode;
use App\Services\Identity\RecoveryCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class MfaAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_login_for_an_enrolled_user_requires_a_valid_totp_code(): void
    {
        [$user, $secret] = $this->createEnrolledUser();

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'a sufficiently long password',
        ])->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
        $this->get(route('two-factor.login'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/TwoFactorChallenge'));

        $validCode = app(Google2FA::class)->getCurrentOtp($secret);
        $invalidCode = str_pad((string) ((intval($validCode) + 1) % 1_000_000), 6, '0', STR_PAD_LEFT);

        $this->from(route('two-factor.login'))
            ->post(route('two-factor.login.store'), ['code' => $invalidCode])
            ->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post(route('two-factor.login.store'), ['code' => $validCode])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_totp_code_cannot_be_reused_after_a_successful_challenge(): void
    {
        [$user, $secret] = $this->createEnrolledUser();
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'a sufficiently long password',
        ])->assertRedirect(route('two-factor.login'));
        $this->post(route('two-factor.login.store'), ['code' => $code])
            ->assertRedirect('/dashboard');

        $this->post(route('logout'));
        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'a sufficiently long password',
        ])->assertRedirect(route('two-factor.login'));

        $this->from(route('two-factor.login'))
            ->post(route('two-factor.login.store'), ['code' => $code])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_code_authenticates_once_and_is_stored_only_as_a_hash(): void
    {
        [$user, , $codes] = $this->createEnrolledUser();
        $code = $codes[0];

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'a sufficiently long password',
        ])->assertRedirect(route('two-factor.login'));

        $this->post(route('two-factor.login.store'), ['recovery_code' => $code])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('user_recovery_codes', [
            'user_id' => $user->getKey(),
            'code_hash' => hash('sha256', $code),
        ]);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $user->getKey(),
            'action_code' => 'iam.mfa.recovery_code_used',
            'target_ref' => 'user:'.$user->getKey(),
            'outcome' => 'SUCCESS',
        ]);
        $this->assertDatabaseMissing('user_recovery_codes', [
            'user_id' => $user->getKey(),
            'code_hash' => hash('sha256', $code),
            'consumed_at' => null,
        ]);

        $this->post(route('logout'));
        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'a sufficiently long password',
        ])->assertRedirect(route('two-factor.login'));

        $this->from(route('two-factor.login'))
            ->post(route('two-factor.login.store'), ['recovery_code' => $code])
            ->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    public function test_enforcement_redirects_an_unenrolled_account_to_setup_and_allows_only_after_confirmation(): void
    {
        config(['iam.mfa_enforced' => true]);
        $password = 'a sufficiently long password';
        $user = User::factory()->create(['password' => Hash::make($password)]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('mfa.setup'));
        $this->actingAs($user)
            ->get(route('mfa.setup'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/MfaSetup')
                ->where('enabled', false)
                ->where('pendingSetup', false)
            );

        $this->actingAs($user)->withSession(['url.intended' => route('mfa.setup')])
            ->post(route('password.confirm.store'), ['password' => $password])
            ->assertRedirect(route('mfa.setup'));

        $this->post(route('mfa.setup.start'))->assertRedirect(route('mfa.setup'));
        $user->refresh();
        $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);
        $this->get(route('mfa.setup'))
            ->assertOk()
            ->assertDontSee($secret)
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/MfaSetup')
                ->where('pendingSetup', true)
            );

        $qrCode = $this->get(route('mfa.setup.qr-code'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('<svg', false);
        $this->assertStringContainsString('no-store', (string) $qrCode->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $qrCode->headers->get('Cache-Control'));
        $this->assertStringNotContainsString($secret, $qrCode->getContent());
        $this->assertStringNotContainsString('otpauth://', $qrCode->getContent());

        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->post(route('mfa.setup.confirm'), ['code' => $code])
            ->assertRedirect(route('mfa.setup'))
            ->assertSessionHas('iam.recovery_codes');

        $this->assertTrue($user->fresh()->hasEnabledTwoFactorAuthentication());
        $this->get(route('mfa.setup.qr-code'))->assertNotFound();
        $this->get(route('dashboard'))->assertOk();
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $user->getKey(),
            'action_code' => 'iam.mfa.enrolled',
            'outcome' => 'SUCCESS',
        ]);
    }

    public function test_fortify_factor_management_routes_cannot_disable_or_bypass_custom_recovery_storage(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/user/two-factor-authentication')->assertNotFound();
        $this->actingAs($user)->post('/user/confirmed-two-factor-authentication')->assertNotFound();
        $this->actingAs($user)->delete('/user/two-factor-authentication')->assertNotFound();
        $this->actingAs($user)->get('/user/two-factor-qr-code')->assertNotFound();
        $this->actingAs($user)->get('/user/two-factor-secret-key')->assertNotFound();
        $this->actingAs($user)->get('/user/two-factor-recovery-codes')->assertNotFound();
        $this->actingAs($user)->post('/user/two-factor-recovery-codes')->assertNotFound();
    }

    public function test_enrolled_user_challenge_is_rate_limited(): void
    {
        [$user] = $this->createEnrolledUser();
        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'a sufficiently long password',
        ])->assertRedirect(route('two-factor.login'));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('two-factor.login.store'), ['code' => '000000']);
        }

        $this->post(route('two-factor.login.store'), ['code' => '000000'])
            ->assertTooManyRequests();
        $this->assertGuest();
    }

    /** @return array{User, string, list<string>} */
    private function createEnrolledUser(): array
    {
        $password = 'a sufficiently long password';
        $user = User::factory()->create(['password' => Hash::make($password)]);
        $secret = app(Google2FA::class)->generateSecretKey(32);
        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_confirmed_at' => now(),
        ])->save();
        $codes = app(RecoveryCodeService::class)->issue($user);

        $this->assertSame(10, UserRecoveryCode::query()->where('user_id', $user->getKey())->count());
        $this->assertDatabaseMissing('user_recovery_codes', [
            'user_id' => $user->getKey(),
            'code_hash' => $codes[0],
        ]);
        $this->assertDatabaseCount('audit_events', 0);

        return [$user, $secret, $codes];
    }
}
