<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertNotSame($sessionId, $this->app['session']->driver()->getId());
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();
        $token = 'test-token';

        $this->withSession(['_token' => $token])->post(route('login.store'), [
            '_token' => $token,
            'email' => $user->email,
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
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->withSession(['_token' => $token])->post(route('login.store'), [
            '_token' => $token,
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }
}
