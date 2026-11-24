<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class KeycloakSsoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app['env'] = 'local';
        $this->withoutMiddleware(HandleInertiaRequests::class);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'iam.mfa_enabled' => false,
            'services.keycloak.enabled' => true, 'services.keycloak.subject' => 'subject-1', 'services.keycloak.username' => 'testuser']);
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username')->nullable();
            $table->string('username_canonical')->nullable();
            $table->string('name');
            $table->string('password');
            $table->string('status');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    private function identity(string $subject): void
    {
        $identity = Mockery::mock(\SocialiteProviders\Manager\OAuth2\User::class)->makePartial();
        $identity->accessTokenResponseBody = ['id_token' => 'example-id-token'];
        $identity->shouldReceive('getId')->andReturn($subject);
        $provider = Mockery::mock();
        $provider->shouldReceive('enablePKCE')->once()->andReturnSelf();
        $provider->shouldReceive('user')->once()->andReturn($identity);
        Socialite::shouldReceive('driver')->once()->with('keycloak')->andReturn($provider);
    }

    public function test_linked_active_user_can_sign_in(): void
    {
        $user = User::create(['username' => 'testuser', 'name' => 'Test', 'password' => 'example-password', 'status' => 'ACTIVE']);
        $this->identity('subject-1');
        $this->get('/auth/keycloak/callback')->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('example-id-token', session('keycloak.id_token'));
    }

    public function test_unlinked_or_inactive_user_cannot_sign_in(): void
    {
        User::create(['username' => 'testuser', 'name' => 'Test', 'password' => 'example-password', 'status' => 'INACTIVE']);
        $this->identity('subject-1');
        $this->get('/auth/keycloak/callback')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_other_subject_cannot_sign_in(): void
    {
        $this->identity('wrong-subject');
        $this->get('/auth/keycloak/callback')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_invalid_oauth_state_does_not_authenticate(): void
    {
        config(['services.keycloak.client_id' => 'kutmb-hr', 'services.keycloak.client_secret' => 'test-secret',
            'services.keycloak.base_url' => 'http://keycloak.localtest.me:8081', 'services.keycloak.realms' => 'kutmb',
            'services.keycloak.redirect' => 'http://kutmb-hr.localhost:8080/auth/keycloak/callback']);
        $this->withSession(['state' => 'expected'])->get('/auth/keycloak/callback?state=wrong&code=example')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_sso_logout_ends_hr_session_and_redirects_browser_to_keycloak(): void
    {
        config(['services.keycloak.client_id' => 'kutmb-hr', 'services.keycloak.client_secret' => 'test-secret',
            'services.keycloak.base_url' => 'http://keycloak.localtest.me:8081', 'services.keycloak.realms' => 'kutmb',
            'services.keycloak.redirect' => 'http://kutmb-hr.localhost:8080/auth/keycloak/callback']);
        $user = User::create(['username' => 'testuser', 'name' => 'Test', 'password' => 'example-password', 'status' => 'ACTIVE']);
        $response = $this->actingAs($user)->withSession(['keycloak.login' => true, 'keycloak.id_token' => 'example-id-token', '_token' => 'test-csrf-token'])
            ->withHeader('X-Inertia', 'true')->post('/logout', ['_token' => 'test-csrf-token']);
        $response->assertStatus(409);
        $url = $response->headers->get('X-Inertia-Location');
        $this->assertStringStartsWith('http://keycloak.localtest.me:8081/realms/kutmb/protocol/openid-connect/logout?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('example-id-token', $query['id_token_hint']);
        $this->assertSame(route('login'), $query['post_logout_redirect_uri']);
        $this->assertGuest();
        $this->assertNull(session('keycloak.id_token'));
    }

    public function test_local_logout_does_not_redirect_to_keycloak(): void
    {
        $user = User::create(['username' => 'testuser', 'name' => 'Test', 'password' => 'example-password', 'status' => 'ACTIVE']);
        $this->actingAs($user)->withSession(['_token' => 'test-csrf-token'])->post('/logout', ['_token' => 'test-csrf-token'])->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_mfa_enforcement_blocks_sso(): void
    {
        config(['iam.mfa_enabled' => true]);
        $this->get('/auth/keycloak')->assertNotFound();
        $this->get('/auth/keycloak/callback')->assertNotFound();
    }

    public function test_sso_is_unavailable_outside_local_environment(): void
    {
        $this->app['env'] = 'production';
        $this->get('/auth/keycloak')->assertNotFound();
    }
}
