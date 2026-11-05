<?php

namespace Tests\Feature\Identity;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IdentityAccessManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_admin_can_view_role_and_permission_screens(): void
    {
        $admin = $this->systemAdmin();

        $this->actingAs($admin)->get(route('iam.roles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('identity/Roles')
                ->has('roles', 1)
                ->has('permissions', 2)
                ->where('roles.0.isSystem', true)
                ->where('roles.0.permissionCodes', ['iam.rbac.manage'])
            );

        $this->get(route('iam.permissions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('identity/Permissions')
                ->has('permissions', 2)
                ->where('permissions.0.systemOnly', true)
            );
    }

    public function test_non_admin_cannot_open_rbac_screens_or_mutate_roles(): void
    {
        $user = User::factory()->create();
        $role = $this->systemAdminRole();

        $this->actingAs($user)
            ->get(route('iam.roles.index'))
            ->assertForbidden();

        $this->post(route('iam.roles.store'), [
            'code' => 'account-reviewer',
            'label' => 'Account Reviewer',
            'permission_codes' => [],
        ])->assertForbidden();

        $this->assertDatabaseCount('roles', 1);
        $this->assertSame('system-admin', $role->fresh()->code);
    }

    public function test_roles_can_be_created_and_permission_assignments_audited(): void
    {
        $admin = $this->systemAdmin();

        $this->actingAs($admin)->post(route('iam.roles.store'), [
            'code' => 'directory-reviewer',
            'label' => 'Directory Reviewer',
            'permission_codes' => ['iam.users.view'],
        ])->assertRedirect(route('iam.roles.index'));

        $role = Role::query()->where('code', 'directory-reviewer')->sole();
        $this->assertSame('directory-reviewer', $role->name);
        $this->assertSame('Directory Reviewer', $role->label);
        $this->assertTrue($role->hasPermissionTo('iam.users.view'));

        $this->patch(route('iam.roles.update', $role), [
            'label' => 'Directory Reader',
            'permission_codes' => [],
        ])->assertRedirect(route('iam.roles.index'));

        $role->refresh();
        $this->assertSame('Directory Reader', $role->label);
        $this->assertFalse($role->hasPermissionTo('iam.users.view'));
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $admin->id,
            'action_code' => 'iam.role.created',
            'target_ref' => 'role:'.$role->id,
            'outcome' => 'SUCCESS',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $admin->id,
            'action_code' => 'iam.role.updated',
            'target_ref' => 'role:'.$role->id,
            'outcome' => 'SUCCESS',
        ]);
    }

    public function test_system_only_access_control_permission_cannot_be_granted_to_another_role(): void
    {
        $this->actingAs($this->systemAdmin())->post(route('iam.roles.store'), [
            'code' => 'delegated-access-admin',
            'label' => 'Delegated Access Admin',
            'permission_codes' => ['iam.rbac.manage'],
        ])->assertSessionHasErrors('permission_codes.0');

        $this->assertDatabaseMissing('roles', ['code' => 'delegated-access-admin']);
    }

    public function test_system_admin_role_cannot_be_changed_deleted_or_delegated(): void
    {
        $this->actingAs($this->systemAdmin());
        $role = Role::query()->where('code', 'system-admin')->sole();

        $this->patch(route('iam.roles.update', $role), [
            'label' => 'Changed',
            'permission_codes' => [],
        ])->assertSessionHasErrors('role');

        $this->delete(route('iam.roles.destroy', $role))->assertSessionHasErrors('role');
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'code' => 'system-admin']);
    }

    public function test_assigned_role_cannot_be_deleted_and_unassigned_role_can(): void
    {
        $admin = $this->systemAdmin();
        $assignedRole = $this->systemAdminRole();
        $role = Role::create(['code' => 'temporary-reviewer', 'name' => 'Temporary Reviewer']);

        $this->actingAs($admin)->delete(route('iam.roles.destroy', $assignedRole))
            ->assertSessionHasErrors('role');
        $this->delete(route('iam.roles.destroy', $role))->assertRedirect(route('iam.roles.index'));

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $admin->id,
            'action_code' => 'iam.role.deleted',
            'target_ref' => 'role:'.$role->id,
            'outcome' => 'SUCCESS',
        ]);
    }

    public function test_registered_menu_permissions_can_be_mapped_and_invalid_entries_are_rejected(): void
    {
        $admin = $this->systemAdmin();
        $mappings = collect(config('iam.menus'))
            ->map(fn (array $menu): string => $menu['permission'])
            ->all();
        $mappings['users.index'] = 'iam.rbac.manage';

        $this->actingAs($admin)->get(route('iam.menus.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('identity/Menus')
                ->has('menus', 4)
            );

        $this->put(route('iam.menus.update'), ['mappings' => $mappings])
            ->assertRedirect(route('iam.menus.index'));

        $permissionId = Permission::query()->where('code', 'iam.rbac.manage')->value('id');
        $this->assertDatabaseHas('menu_permission_assignments', [
            'menu_key' => 'users.index',
            'permission_id' => $permissionId,
        ]);
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $admin->id,
            'action_code' => 'iam.menu_access.updated',
            'target_ref' => 'menu:registered',
            'outcome' => 'SUCCESS',
        ]);

        $mappings['unregistered.menu'] = 'iam.users.view';
        $this->put(route('iam.menus.update'), ['mappings' => $mappings])
            ->assertSessionHasErrors('mappings');
    }

    public function test_package_permission_revocation_is_effective_without_stale_model_relations(): void
    {
        $user = $this->systemAdmin();

        $this->assertTrue($user->can('iam.rbac.manage'));
        $user->roles()->firstOrFail()->revokePermissionTo('iam.rbac.manage');

        $this->assertFalse($user->fresh()->can('iam.rbac.manage'));
    }

    private function systemAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole($this->systemAdminRole());

        return $user;
    }

    private function systemAdminRole(): Role
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'system-admin'],
            [
                'name' => 'system-admin',
                'guard_name' => 'web',
                'label' => 'System Admin',
                'is_system' => true,
            ],
        );
        $permission = Permission::findByName('iam.rbac.manage', 'web');
        $role->givePermissionTo($permission);

        return $role;
    }
}
