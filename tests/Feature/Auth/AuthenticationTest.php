<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();
        $token = 'test-token';
        $sessionId = $this->app['session']->driver()->getId();

        $response = $this->withSession(['_token' => $token])->post(route('login.store'), [
            '_token' => $token,
            'username' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertNotSame($sessionId, $this->app['session']->driver()->getId());
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_forged_remember_me_flag_does_not_enable_persistent_authentication(): void
    {
        $user = User::factory()->create();
        $rememberToken = $user->remember_token;

        $response = $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'password',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertCookieMissing($this->app['auth']->guard()->getRecallerName());

        $this->assertDatabaseHas('users', ['id' => $user->id, 'remember_token' => $rememberToken]);
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();
        $token = 'test-token';

        $this->withSession(['_token' => $token])->post(route('login.store'), [
            '_token' => $token,
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();
        $token = 'test-token';

        $response = $this->actingAs($user)->withSession([
            '_token' => $token,
            'sensitive_marker' => 'must-be-flushed',
        ])->post(route('logout'), [
            '_token' => $token,
        ]);

        $response->assertRedirect(route('home'));
        $this->assertGuest();
        $response->assertSessionMissing('sensitive_marker');
    }

    public function test_users_are_rate_limited()
    {
        $user = User::factory()->create();
        $token = 'test-token';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withSession(['_token' => $token])->post(route('login.store'), [
                '_token' => $token,
                'username' => $user->username,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('username');
        }

        $this->withSession(['_token' => $token])->post(route('login.store'), [
            '_token' => $token,
            'username' => $user->username,
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_username_login_is_case_insensitive(): void
    {
        $user = User::factory()->create(['username' => 'CaseSensitive.User']);

        $this->post(route('login.store'), ['username' => 'CASESENSITIVE.USER', 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_email_is_not_an_alternative_login_identifier(): void
    {
        $user = User::factory()->create();

        $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_invalid_and_unknown_usernames_have_generic_credential_errors(): void
    {
        $user = User::factory()->create();
        $invalid = $this->from(route('login'))->post(route('login.store'), [
            'username' => 'name with spaces',
            'password' => 'password',
        ]);
        $unknown = $this->from(route('login'))->post(route('login.store'), [
            'username' => 'unknown-user',
            'password' => 'password',
        ]);
        $incorrectPassword = $this->from(route('login'))->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $invalid->assertSessionHasErrors('username');
        $unknown->assertSessionHasErrors('username');
        $incorrectPassword->assertSessionHasErrors('username');
        $this->assertSame(
            $invalid->getSession()->get('errors')->first('username'),
            $unknown->getSession()->get('errors')->first('username'),
        );
        $this->assertSame(
            $unknown->getSession()->get('errors')->first('username'),
            $incorrectPassword->getSession()->get('errors')->first('username'),
        );
        $this->assertGuest();
    }

    public function test_null_username_account_cannot_authenticate(): void
    {
        $user = User::factory()->create(['username' => null]);

        $this->from(route('login'))->post(route('login.store'), [
            'username' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'username' => null, 'email' => $user->email]);
    }

    public function test_inactive_account_cannot_authenticate_with_correct_password(): void
    {
        $user = User::factory()->create(['status' => 'INACTIVE']);

        $this->from(route('login'))->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_authenticated_user_is_logged_out_after_database_status_becomes_inactive(): void
    {
        $user = User::factory()->create();
        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));
        $previousCsrfToken = session()->token();
        $this->withSession(['sensitive_marker' => 'flush-me']);
        DB::table('users')->where('id', $user->id)->update(['status' => 'INACTIVE']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'status' => 'INACTIVE']);

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('sensitive_marker');

        $this->assertGuest();
        $this->assertNotSame($previousCsrfToken, session()->token());
    }
}
