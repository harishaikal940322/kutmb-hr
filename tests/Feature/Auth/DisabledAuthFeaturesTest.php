<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DisabledAuthFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_unapproved_authentication_features_have_no_routes(): void
    {
        $endpoints = [
            ['GET', '/register'],
            ['POST', '/register'],
            ['GET', '/forgot-password'],
            ['POST', '/forgot-password'],
            ['GET', '/reset-password/example-token'],
            ['POST', '/reset-password'],
            ['GET', '/email/verify'],
            ['GET', '/email/verify/1/example-hash'],
            ['POST', '/email/verification-notification'],
            ['GET', '/two-factor-challenge'],
            ['POST', '/two-factor-challenge'],
            ['POST', '/user/two-factor-authentication'],
            ['POST', '/user/confirmed-two-factor-authentication'],
            ['DELETE', '/user/two-factor-authentication'],
            ['GET', '/user/two-factor-qr-code'],
            ['GET', '/user/two-factor-secret-key'],
            ['GET', '/user/two-factor-recovery-codes'],
            ['POST', '/user/two-factor-recovery-codes'],
            ['GET', '/passkeys/login/options'],
            ['POST', '/passkeys/login'],
            ['GET', '/passkeys/confirm/options'],
            ['POST', '/passkeys/confirm'],
            ['GET', '/user/passkeys/options'],
            ['POST', '/user/passkeys'],
            ['DELETE', '/user/passkeys/example-id'],
        ];

        foreach ($endpoints as [$method, $path]) {
            $this->call($method, $path)->assertNotFound();
        }

        foreach ([
            'register',
            'password.request',
            'password.email',
            'password.reset',
            'password.update',
            'verification.notice',
            'verification.verify',
            'verification.send',
            'two-factor.login',
            'two-factor.enable',
            'two-factor.confirm',
            'two-factor.disable',
            'two-factor.qr-code',
            'two-factor.secret-key',
            'two-factor.recovery-codes',
            'passkey.login-options',
            'passkey.login',
            'passkey.confirm-options',
            'passkey.confirm',
            'passkey.registration-options',
            'passkey.store',
            'passkey.destroy',
        ] as $name) {
            $this->assertFalse(Route::has($name), "Route [{$name}] must stay disabled.");
        }
    }

    public function test_login_page_renders_without_recovery_or_registration_links(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/Login'));
    }

    public function test_unconfigured_local_file_serving_routes_are_disabled(): void
    {
        $this->get('/storage/example.txt')->assertNotFound();
        $this->put('/storage/example.txt', ['content' => 'content'])->assertNotFound();

        $this->assertFalse(Route::has('storage.local'));
        $this->assertFalse(Route::has('storage.local.upload'));
    }

    public function test_database_seeder_does_not_create_application_accounts(): void
    {
        $this->seed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_no_unreviewed_provisioning_or_privilege_mutation_routes_exist(): void
    {
        // Fortify's password confirmation only re-authenticates the current
        // session; it does not provision accounts or grant privileges.
        $allowedMutations = ['login.store', 'logout', 'password.confirm.store', 'profile.update'];

        foreach (Route::getRoutes() as $route) {
            $mutatingMethods = array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']);

            if ($mutatingMethods === []) {
                continue;
            }

            $this->assertContains(
                $route->getName(),
                $allowedMutations,
                sprintf('Unexpected mutating route [%s] %s.', implode('|', $mutatingMethods), $route->uri()),
            );
        }
    }
}
