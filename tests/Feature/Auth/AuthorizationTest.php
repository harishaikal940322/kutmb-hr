<?php

namespace Tests\Feature\Auth;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::policy(SyntheticTarget::class, SyntheticTargetPolicy::class);

        Route::middleware(['protected', 'permission:synthetic.resource.view'])
            ->get('/_tests/authorization-probe', fn () => response('authorized'));
    }

    public function test_public_login_and_health_routes_remain_public(): void
    {
        $this->get(route('login'))->assertOk();
        $this->get('/up')->assertOk();
    }

    public function test_protected_route_requires_authentication(): void
    {
        $this->get('/_tests/authorization-probe')->assertRedirect(route('login'));
    }

    public function test_protected_route_requires_an_active_account(): void
    {
        $user = User::factory()->create(['status' => 'INACTIVE']);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_existing_session_is_invalidated_when_username_becomes_null(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['sensitive_marker' => 'flush-me']);
        $previousToken = session()->token();
        DB::table('users')->where('id', $user->id)->update([
            'username' => null,
            'username_canonical' => null,
        ]);

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionMissing('sensitive_marker');

        $this->assertGuest();
        $this->assertNotSame($previousToken, session()->token());
    }

    public function test_unknown_permission_is_denied(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(Gate::forUser($user)->allows('permission', 'permission.does.not.exist'));
        $this->assertFalse(Gate::allows('permission', 'synthetic.resource.view'));
    }

    public function test_user_without_a_role_is_denied(): void
    {
        $user = User::factory()->create();
        Permission::create(['code' => 'synthetic.resource.view', 'name' => 'Synthetic view']);

        $this->assertFalse(Gate::forUser($user)->allows('permission', 'synthetic.resource.view'));
    }

    public function test_user_without_the_requested_permission_is_denied(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['code' => 'SYNTHETIC_READER', 'name' => 'Synthetic reader']);
        $user->roles()->attach($role);

        $this->assertFalse(Gate::forUser($user)->allows('permission', 'synthetic.resource.view'));
    }

    public function test_synthetic_role_permission_grants_the_gate_and_http_route(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['code' => 'SYNTHETIC_READER', 'name' => 'Synthetic reader']);
        $permission = Permission::create(['code' => 'synthetic.resource.view', 'name' => 'Synthetic view']);
        $user->roles()->attach($role);
        $role->permissions()->attach($permission);

        $this->assertTrue(Gate::forUser($user)->allows('permission', 'synthetic.resource.view'));
        $this->actingAs($user)->get('/_tests/authorization-probe')->assertOk()->assertSeeText('authorized');
    }

    public function test_permission_revocation_applies_to_an_existing_session_on_the_next_request(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['code' => 'SYNTHETIC_READER', 'name' => 'Synthetic reader']);
        $permission = Permission::create(['code' => 'synthetic.resource.view', 'name' => 'Synthetic view']);
        $user->roles()->attach($role);
        $role->permissions()->attach($permission);

        $this->actingAs($user)->get('/_tests/authorization-probe')->assertOk();
        $role->permissions()->detach($permission);

        $this->get('/_tests/authorization-probe')->assertForbidden();
        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_is_denied_by_the_gate_even_before_route_middleware(): void
    {
        $user = User::factory()->create(['status' => 'INACTIVE']);

        $this->assertFalse(Gate::forUser($user)->allows('permission', 'synthetic.resource.view'));
    }

    public function test_null_username_user_is_denied_by_the_gate(): void
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update([
            'username' => null,
            'username_canonical' => null,
        ]);

        $this->assertFalse(Gate::forUser($user)->allows('permission', 'synthetic.resource.view'));
    }

    public function test_explicit_policy_denial_cannot_be_overridden_by_matching_rbac_permission(): void
    {
        $user = User::factory()->create();
        $target = new SyntheticTarget(['name' => 'Forbidden target']);
        $this->grantTargetPermission($user);

        $this->assertTrue(Gate::forUser($user)->allows('permission', 'synthetic.target.view'));
        $this->assertFalse(Gate::forUser($user)->allows('view', $target));
    }

    public function test_permission_and_target_policy_must_both_allow_access(): void
    {
        $user = User::factory()->create();
        $target = new SyntheticTarget(['name' => 'Allowed target']);
        $this->grantTargetPermission($user);

        $this->assertTrue(Gate::forUser($user)->allows('view', $target));
    }

    public function test_direct_http_access_without_permission_returns_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/_tests/authorization-probe')
            ->assertForbidden();
    }

    private function grantTargetPermission(User $user): void
    {
        $role = Role::create(['code' => 'SYNTHETIC_TARGET_READER', 'name' => 'Synthetic target reader']);
        $permission = Permission::create(['code' => 'synthetic.target.view', 'name' => 'Synthetic target view']);
        $user->roles()->attach($role);
        $role->permissions()->attach($permission);
    }
}

class SyntheticTarget extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}

class SyntheticTargetPolicy
{
    public function view(User $user, SyntheticTarget $target): bool
    {
        return Gate::forUser($user)->allows('permission', 'synthetic.target.view')
            && $target->name === 'Allowed target';
    }
}
