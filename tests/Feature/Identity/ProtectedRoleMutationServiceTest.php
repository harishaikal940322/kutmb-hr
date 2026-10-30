<?php

namespace Tests\Feature\Identity;

use App\Models\AuditEvent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Identity\ProtectedRoleMutationService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ProtectedRoleMutationServiceTest extends TestCase
{
    use DatabaseMigrations;

    public function test_last_eligible_user_cannot_be_deactivated_or_lose_protected_role(): void
    {
        [$user, $role] = $this->protectedUser();

        foreach (['deactivate', 'revoke'] as $operation) {
            try {
                $this->mutate($user, $role, $operation);
                $this->fail('Expected last eligible administrator operation to fail.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('last eligible', $exception->getMessage());
            }
        }

        $this->assertSame('ACTIVE', $user->fresh()->status);
        $this->assertDatabaseHas('user_roles', ['user_id' => $user->id, 'role_id' => $role->id]);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_missing_guard_and_undefined_role_fail_closed(): void
    {
        [$user, $role] = $this->protectedUser();
        DB::table('iam_guard')->delete();

        try {
            $this->mutate($user, $role, 'deactivate');
            $this->fail('Missing guard row was accepted.');
        } catch (RuntimeException) {
            $this->assertSame('ACTIVE', $user->fresh()->status);
            $this->assertDatabaseCount('audit_events', 0);
        }
    }

    public function test_undefined_protected_role_fails_before_mutation(): void
    {
        [$user, $role] = $this->protectedUser();

        try {
            $this->mutate($user, $role, 'deactivate', 'SYNTHETIC_UNDEFINED');
            $this->fail('Undefined protected role was accepted.');
        } catch (RuntimeException) {
            $this->assertSame('ACTIVE', $user->fresh()->status);
            $this->assertDatabaseCount('audit_events', 0);
        }
    }

    public function test_success_commits_mutation_and_allowlisted_audit_together(): void
    {
        [$actor, $role] = $this->protectedUser();
        $target = $this->additionalProtectedUser($role);

        $this->mutate($target, $role, 'deactivate', actorId: $actor->id);

        $this->assertSame('INACTIVE', $target->fresh()->status);
        $event = AuditEvent::query()->sole();
        $this->assertSame($actor->id, $event->actor_id);
        $this->assertSame('synthetic.user.deactivate', $event->action_code);
        $this->assertSame('user:'.$target->id, $event->target_ref);
        $this->assertSame('SUCCESS', $event->outcome);
        $this->assertSame(['source' => 'service_test', 'changed_fields' => ['status']], $event->metadata->getArrayCopy());
    }

    public function test_audit_failure_rolls_back_the_protected_mutation(): void
    {
        [$actor, $role] = $this->protectedUser();
        $target = $this->additionalProtectedUser($role);

        try {
            app(ProtectedRoleMutationService::class)->execute(
                $actor->id,
                'user:'.$target->id,
                $role->code,
                'synthetic.user.deactivate',
                ['changed_fields' => ['password']],
                fn () => DB::table('users')->where('id', $target->id)->update(['status' => 'INACTIVE']),
            );
            $this->fail('Unsafe audit metadata should fail.');
        } catch (RuntimeException|\InvalidArgumentException) {
            $this->assertSame('ACTIVE', $target->fresh()->status);
            $this->assertDatabaseCount('audit_events', 0);
        }
    }

    public function test_revoked_role_is_denied_to_an_existing_session_on_the_next_request(): void
    {
        [$user, $role] = $this->protectedUser();
        $reader = Role::create(['code' => 'SYNTHETIC_READER', 'name' => 'Synthetic reader']);
        $permission = Permission::create(['code' => 'synthetic.protected.read', 'name' => 'Synthetic read']);
        $reader->permissions()->attach($permission);
        $user->roles()->attach($reader);
        Route::middleware(['protected', 'permission:synthetic.protected.read'])->get('/_tests/protected-read', fn () => 'ok');

        $this->actingAs($user)->get('/_tests/protected-read')->assertOk();
        $this->mutate($user, $reader, 'revoke', protectedCode: $role->code, actorId: $user->id);
        $this->get('/_tests/protected-read')->assertForbidden();
    }

    private function mutate(User $target, Role $role, string $operation, ?string $protectedCode = null, ?int $actorId = null): void
    {
        app(ProtectedRoleMutationService::class)->execute(
            $actorId ?? $target->id,
            'user:'.$target->id,
            $protectedCode ?? $role->code,
            'synthetic.user.'.$operation,
            ['source' => 'service_test', 'changed_fields' => [$operation === 'revoke' ? 'role' : 'status']],
            static function () use ($target, $role, $operation): void {
                if ($operation === 'revoke') {
                    DB::table('user_roles')->where('user_id', $target->id)->where('role_id', $role->id)->delete();
                } else {
                    DB::table('users')->where('id', $target->id)->update(['status' => 'INACTIVE']);
                }
            },
        );
    }

    /** @return array{User, Role} */
    private function protectedUser(): array
    {
        $user = User::factory()->create(['username' => 'synthetic.admin']);
        $role = Role::create(['code' => 'SYNTHETIC_PROTECTED', 'name' => 'Synthetic protected role']);
        $user->roles()->attach($role);

        return [$user, $role];
    }

    private function additionalProtectedUser(Role $role): User
    {
        $user = User::factory()->create(['username' => 'synthetic.admin2']);
        $user->roles()->attach($role);

        return $user;
    }
}
