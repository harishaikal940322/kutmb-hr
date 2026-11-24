<?php

namespace Tests\Feature\Identity;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TransactionalRbacVerificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_synthetic_role_operations_and_permission_writes_remain_in_transaction(): void
    {
        DB::listen(function ($query): void {
            $this->assertSame(DB::connection()->getName(), $query->connectionName);
            $this->assertDoesNotMatchRegularExpression('/^\s*(?:create|alter|drop|truncate|rename)\b/i', $query->sql);
            $this->assertGreaterThan(0, DB::transactionLevel());
        });
        $suffix = strtolower(Str::random(16));
        $outsider = User::factory()->create(['username' => 'denied'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $outsider->forceFill(['two_factor_secret' => 'synthetic', 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($outsider)->get(route('iam.roles.index'))->assertForbidden();
        $actor = User::factory()->create(['username' => 'test'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $actor->forceFill(['two_factor_secret' => 'synthetic', 'two_factor_confirmed_at' => now()])->save();
        $actor->givePermissionTo('iam.rbac.manage');
        $this->assertGreaterThan(0, DB::transactionLevel());
        $this->assertTrue($actor->fresh()->can('iam.rbac.manage'));
        $this->actingAs($actor);
        $this->get(route('iam.roles.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('identity/Roles')
            ->where('templates.0.permissions', ['iam.users.view'])
            ->where('templates.0.available', true)
        );
        $code = 'test-'.$suffix;
        $this->post(route('iam.roles.store'), ['code' => $code, 'label' => 'Synthetic reader', 'permission_codes' => ['iam.users.view']])->assertRedirect();
        $role = Role::query()->where('code', $code)->sole();
        $this->assertTrue($role->hasPermissionTo('iam.users.view'));
        $this->assertDatabaseHas('role_has_permissions', ['role_id' => $role->id, 'permission_id' => Permission::findByName('iam.users.view', 'web')->id]);
        $this->patch(route('iam.roles.update', $role), ['label' => 'Synthetic edited', 'permission_codes' => []])->assertRedirect();
        $this->assertFalse($role->fresh()->hasPermissionTo('iam.users.view'));
        $this->assertDatabaseHas('audit_events', ['actor_id' => $actor->id, 'action_code' => 'iam.role.updated']);
        $this->post(route('iam.roles.store'), ['code' => 'bad-'.$suffix, 'label' => 'Synthetic invalid', 'permission_codes' => ['iam.rbac.manage']])->assertSessionHasErrors('permission_codes.0');
        $this->post(route('iam.roles.store'), ['code' => 'unknown-'.$suffix, 'label' => 'Synthetic invalid', 'permission_codes' => ['synthetic.unknown']])->assertSessionHasErrors('permission_codes.0');
        $protected = Role::create(['code' => 'protected-'.$suffix, 'name' => 'protected-'.$suffix, 'label' => 'Synthetic protected', 'is_system' => true]);
        $this->patch(route('iam.roles.update', $protected), ['label' => 'Changed', 'permission_codes' => []])->assertSessionHasErrors('role');
        $legacy = Permission::create(['code' => 'test.'.$suffix, 'name' => 'test.'.$suffix, 'label' => 'Synthetic unknown']);
        $role->givePermissionTo($legacy);
        $this->patch(route('iam.roles.update', $role), ['label' => 'Changed', 'permission_codes' => []])->assertSessionHasErrors('role');
        $this->assertTrue($role->fresh()->hasPermissionTo($legacy));
        $this->assertSame('Synthetic edited', $role->fresh()->label);
        $this->assertGreaterThan(0, DB::transactionLevel());
    }
}
