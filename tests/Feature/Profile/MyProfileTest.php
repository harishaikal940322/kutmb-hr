<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MyProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('profile'))->assertRedirect(route('login'));
    }

    public function test_active_user_can_view_only_their_allowlisted_identity_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Synthetic Profile User',
            'username' => 'synthetic.profile',
            'email' => 'profile@example.test',
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($user)->get(route('profile'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MyProfile')
                ->where('profile', [
                    'displayName' => 'Synthetic Profile User',
                    'username' => 'synthetic.profile',
                    'email' => 'profile@example.test',
                    'status' => 'ACTIVE',
                ])
                ->where('auth.user', [
                    'name' => 'Synthetic Profile User',
                    'email' => 'profile@example.test',
                ])
                ->missing('profile.password')
                ->missing('profile.remember_token')
                ->missing('profile.session_id')
                ->missing('auth.user.password')
                ->missing('auth.user.remember_token')
                ->missing('auth.user.username_canonical')
                ->missing('auth.user.session_id')
                ->missing('session')
            );
    }

    public function test_missing_email_is_omitted_from_identity_details(): void
    {
        $user = User::factory()->create(['email' => null]);

        $this->actingAs($user)->get(route('profile'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MyProfile')
                ->where('profile.email', null)
                ->where('profile.username', $user->username)
            );
    }

    public function test_inactive_user_session_is_invalidated_on_profile_access(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withSession(['sensitive_marker' => 'discard-me']);
        DB::table('users')->where('id', $user->id)->update(['status' => 'INACTIVE']);

        $this->get(route('profile'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('sensitive_marker');

        $this->assertGuest();
    }

    public function test_null_username_session_is_rejected_and_invalidated_on_profile_access(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->withSession(['sensitive_marker' => 'discard-me']);
        DB::table('users')->where('id', $user->id)->update([
            'username' => null,
            'username_canonical' => null,
        ]);

        $this->get(route('profile'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('sensitive_marker');

        $this->assertGuest();
    }

    public function test_account_selector_query_cannot_change_the_profile_owner(): void
    {
        $user = User::factory()->create(['username' => 'profile.owner']);
        $other = User::factory()->create(['username' => 'other.account']);

        $this->actingAs($user)->get(route('profile', ['user' => $other->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MyProfile')
                ->where('profile.username', 'profile.owner')
            );

        $this->get('/profile/'.$other->id)->assertNotFound();
    }

    public function test_profile_requires_no_role_and_grants_no_administrative_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile'))->assertOk();

        $this->assertDatabaseCount('user_roles', 0);
        $this->assertFalse(Gate::forUser($user)->allows('permission', 'users.manage'));
        $this->assertFalse(Gate::forUser($user)->allows('permission', 'roles.manage'));
    }
}
