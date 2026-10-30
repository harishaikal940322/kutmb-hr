<?php

namespace Tests\Feature\Profile;

use App\Models\AuditEvent;
use App\Models\Permission;
use App\Models\Role;
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
                ->where('myAccess', ['roleNames' => []])
                ->missing('profile.password')
                ->missing('profile.remember_token')
                ->missing('profile.session_id')
                ->missing('auth.user.password')
                ->missing('auth.user.remember_token')
                ->missing('auth.user.username_canonical')
                ->missing('auth.user.session_id')
                ->missing('myAccess.permissions')
                ->missing('myAccess.roleObjects')
                ->missing('permissions')
                ->missing('roles')
                ->missing('users')
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

    public function test_my_access_shows_only_the_current_users_role_names_and_no_permission_graph(): void
    {
        $user = User::factory()->create(['username' => 'access.owner']);
        $other = User::factory()->create(['username' => 'access.other']);
        $role = Role::query()->create(['code' => 'synthetic-viewer', 'name' => 'Synthetic Viewer']);
        $otherRole = Role::query()->create(['code' => 'synthetic-operator', 'name' => 'Synthetic Operator']);
        $permission = Permission::query()->create(['code' => 'synthetic.read', 'name' => 'Synthetic Read']);

        $user->roles()->attach($role);
        $role->permissions()->attach($permission);
        $other->roles()->attach($otherRole);

        $this->actingAs($user)->get(route('profile'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MyProfile')
                ->where('myAccess', ['roleNames' => ['Synthetic Viewer']])
                ->missing('myAccess.permissions')
                ->missing('myAccess.roleObjects')
                ->missing('permissions')
                ->missing('roles')
                ->missing('users')
            );

        $this->actingAs($other)->get(route('profile'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('MyProfile')
                ->where('myAccess', ['roleNames' => ['Synthetic Operator']])
            );
    }

    public function test_role_assignment_changes_are_reflected_on_the_next_profile_request(): void
    {
        $user = User::factory()->create(['username' => 'access.refresh']);
        $role = Role::query()->create(['code' => 'synthetic-member', 'name' => 'Synthetic Member']);

        $this->actingAs($user)->get(route('profile'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('myAccess.roleNames', [])
            );

        $user->roles()->attach($role);

        $this->get(route('profile'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('myAccess.roleNames', ['Synthetic Member'])
            );
    }

    public function test_active_user_can_update_only_their_trimmed_display_name_and_audit_is_redacted(): void
    {
        $user = User::factory()->create([
            'name' => 'Before Name',
            'username' => 'self.edit',
            'email' => 'self@example.test',
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($user)
            ->from(route('profile'))
            ->patch(route('profile.update'), ['display_name' => '  Updated Name  '])
            ->assertRedirect(route('profile'))
            ->assertSessionHas('success', 'Display name updated.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'username' => 'self.edit',
            'email' => 'self@example.test',
            'status' => 'ACTIVE',
        ]);

        $event = AuditEvent::query()->where('target_ref', 'user:'.$user->id)->sole();
        $this->assertSame($user->id, $event->actor_id);
        $this->assertSame('iam.profile.display_name_updated', $event->action_code);
        $this->assertSame('SUCCESS', $event->outcome);
        $this->assertSame(['source' => 'self_service', 'changed_fields' => ['name']], (array) $event->metadata);
        $this->assertStringNotContainsString('Updated Name', (string) $event->getRawOriginal('metadata'));
    }

    public function test_blank_and_overlong_display_names_are_rejected(): void
    {
        $user = User::factory()->create(['name' => 'Keep This Name']);

        foreach (['   ', str_repeat('a', 256)] as $invalidName) {
            $this->actingAs($user)
                ->from(route('profile'))
                ->patch(route('profile.update'), ['display_name' => $invalidName])
                ->assertRedirect(route('profile'))
                ->assertSessionHasErrors('display_name');
        }

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Keep This Name']);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_restricted_fields_and_other_user_target_tampering_are_rejected(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'username' => 'owner.account',
            'email' => 'owner@example.test',
            'status' => 'ACTIVE',
        ]);
        $other = User::factory()->create(['name' => 'Other Name', 'username' => 'other.account']);
        $restricted = [
            'username' => 'attacker.username',
            'email' => 'attacker@example.test',
            'status' => 'INACTIVE',
            'roles' => ['system-admin'],
            'permissions' => ['users.manage'],
            'password' => 'synthetic-forbidden-password',
            'user_id' => $other->id,
        ];

        foreach ($restricted as $field => $value) {
            $this->actingAs($user)
                ->from(route('profile'))
                ->patch(route('profile.update'), ['display_name' => 'Attempted Name', $field => $value])
                ->assertRedirect(route('profile'))
                ->assertSessionHasErrors('display_name');
        }

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Original Name',
            'username' => 'owner.account',
            'email' => 'owner@example.test',
            'status' => 'ACTIVE',
        ]);
        $this->assertDatabaseHas('users', ['id' => $other->id, 'name' => 'Other Name']);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_guests_and_inactive_accounts_cannot_update_display_name(): void
    {
        $this->patch(route('profile.update'), ['display_name' => 'Guest Change'])
            ->assertRedirect(route('login'));

        $user = User::factory()->create(['name' => 'Inactive Name']);
        $this->actingAs($user)->withSession(['sensitive_marker' => 'discard-me']);
        DB::table('users')->where('id', $user->id)->update(['status' => 'INACTIVE']);

        $this->patch(route('profile.update'), ['display_name' => 'Blocked Change'])
            ->assertRedirect(route('login'))
            ->assertSessionMissing('sensitive_marker');

        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Inactive Name']);
        $this->assertDatabaseCount('audit_events', 0);
    }
}
