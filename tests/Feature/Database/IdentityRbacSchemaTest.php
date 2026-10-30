<?php

namespace Tests\Feature\Database;

use App\Models\AuditEvent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Identity\Username;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class IdentityRbacSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_starter_user_and_session_migrations_remain_compatible(): void
    {
        $user = User::factory()->create(['username' => null]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => null,
            'username_canonical' => null,
            'status' => 'ACTIVE',
        ]);
        $this->assertTrue(Schema::hasColumns('sessions', ['id', 'user_id', 'payload', 'last_activity']));
    }

    public function test_canonical_username_is_ascii_lowercase_and_database_unique(): void
    {
        $user = User::factory()->create(['username' => 'Case.User-1']);

        $this->assertSame('case.user-1', $user->fresh()->username_canonical);

        $this->expectException(QueryException::class);
        User::factory()->create(['username' => 'case.user-1']);
    }

    public function test_username_length_and_permitted_characters_are_explicit(): void
    {
        $this->assertSame(str_repeat('a', 64), Username::canonicalize(str_repeat('A', 64)));

        foreach ([str_repeat('a', 65), ' leading', 'trailing ', 'éclair', 'a/b', '.leading'] as $invalid) {
            try {
                Username::canonicalize($invalid);
                $this->fail('Expected invalid username to be rejected.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_role_and_permission_codes_are_database_unique(): void
    {
        Role::create(['code' => 'TEST_ROLE', 'name' => 'Test Role']);
        Permission::create(['code' => 'test.read', 'name' => 'Test Read']);

        try {
            Role::create(['code' => 'TEST_ROLE', 'name' => 'Duplicate']);
            $this->fail('Duplicate role code was accepted.');
        } catch (QueryException) {
            $this->assertDatabaseCount('roles', 1);
        }

        $this->expectException(QueryException::class);
        Permission::create(['code' => 'test.read', 'name' => 'Duplicate']);
    }

    public function test_foreign_keys_reject_missing_related_rows_and_restrict_deletion(): void
    {
        try {
            DB::table('user_roles')->insert(['user_id' => 987654, 'role_id' => 987654]);
            $this->fail('Invalid role assignment was accepted.');
        } catch (QueryException) {
            $this->assertDatabaseCount('user_roles', 0);
        }

        $user = User::factory()->create();
        $role = Role::create(['code' => 'TEST_ROLE', 'name' => 'Test Role']);
        $user->roles()->attach($role);

        $this->expectException(QueryException::class);
        $role->delete();
    }

    public function test_duplicate_pivot_relationship_is_rejected_by_composite_key(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['code' => 'TEST_ROLE', 'name' => 'Test Role']);
        $user->roles()->attach($role);

        $this->expectException(QueryException::class);
        $user->roles()->attach($role);
    }

    public function test_invalid_role_permission_relationship_is_rejected(): void
    {
        $role = Role::create(['code' => 'TEST_ROLE', 'name' => 'Test Role']);

        $this->expectException(QueryException::class);
        $role->permissions()->attach(987654);
    }

    public function test_transaction_rollback_discards_identity_and_rbac_changes(): void
    {
        try {
            DB::transaction(function (): void {
                Role::create(['code' => 'ROLLBACK_ROLE', 'name' => 'Rollback']);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('rollback', $exception->getMessage());
        }

        $this->assertDatabaseMissing('roles', ['code' => 'ROLLBACK_ROLE']);
    }

    public function test_iam_guard_is_a_singleton_and_supports_mysql_transaction_locking(): void
    {
        $this->assertDatabaseCount('iam_guard', 1);
        $this->assertDatabaseHas('iam_guard', ['id' => 1, 'revision' => 0]);

        try {
            DB::table('iam_guard')->insert(['id' => 2, 'revision' => 0]);
            $this->fail('A second guard row was accepted.');
        } catch (QueryException) {
            $this->assertDatabaseCount('iam_guard', 1);
        }

        DB::transaction(function (): void {
            $guard = DB::table('iam_guard')->where('id', 1)->lockForUpdate()->first();
            $this->assertNotNull($guard);
            DB::table('iam_guard')->where('id', 1)->increment('revision');
        });

        $this->assertDatabaseHas('iam_guard', ['id' => 1, 'revision' => 1]);
    }

    public function test_audit_event_persists_only_allowlisted_metadata(): void
    {
        $actor = User::factory()->create();
        $event = AuditEvent::create([
            'actor_id' => $actor->id,
            'action_code' => 'test.schema_check',
            'target_ref' => 'synthetic:1',
            'outcome' => 'SUCCESS',
            'metadata' => ['source' => 'automated_test', 'changed_fields' => ['status']],
        ]);

        $this->assertDatabaseHas('audit_events', ['id' => $event->id, 'actor_id' => $actor->id]);
        $this->assertSame('automated_test', $event->fresh()->metadata['source']);

        $this->expectException(InvalidArgumentException::class);
        AuditEvent::create([
            'action_code' => 'test.secret_rejected',
            'outcome' => 'DENIED',
            'metadata' => ['changed_fields' => ['credential_hash']],
        ]);
    }

    public function test_audit_actor_can_become_null_without_losing_event(): void
    {
        $actor = User::factory()->create();
        $event = AuditEvent::create(['actor_id' => $actor->id, 'action_code' => 'test.actor', 'outcome' => 'SUCCESS']);
        $actor->delete();

        $this->assertDatabaseHas('audit_events', ['id' => $event->id, 'actor_id' => null]);
    }

    public function test_schema_has_no_employee_tables_or_default_privileged_users(): void
    {
        $tables = Schema::getTableListing();

        $this->assertFalse(collect($tables)->contains(fn (string $table): bool => str_contains(strtolower($table), 'employee')));
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('roles', 0);
        $this->assertDatabaseCount('permissions', 0);
    }
}
