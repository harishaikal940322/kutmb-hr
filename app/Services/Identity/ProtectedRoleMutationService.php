<?php

namespace App\Services\Identity;

use App\Models\AuditEvent;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Serializes and audits mutations that can remove protected-role eligibility. */
class ProtectedRoleMutationService
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  callable(): void  $mutation
     */
    public function execute(
        int $actorId,
        string $targetRef,
        string $protectedRoleCode,
        string $actionCode,
        array $metadata,
        callable $mutation,
    ): void {
        if (preg_match('/\A[a-z][a-z0-9_.-]{0,119}\z/D', $actionCode) !== 1
            || preg_match('/\Auser:[1-9][0-9]{0,18}\z/D', $targetRef) !== 1) {
            throw new RuntimeException('Protected role mutation is unavailable.');
        }

        $this->assertSafeMetadata($metadata);

        DB::transaction(function () use ($actorId, $targetRef, $protectedRoleCode, $actionCode, $metadata, $mutation): void {
            $guard = DB::table('iam_guard')->where('id', 1)->lockForUpdate()->first();

            if ($guard === null) {
                throw new RuntimeException('Protected role mutation is unavailable.');
            }

            $role = Role::query()->where('code', $protectedRoleCode)->first();

            if ($role === null) {
                throw new RuntimeException('Protected role mutation is unavailable.');
            }

            $mutation();

            $eligibleCount = DB::table('user_roles')
                ->join('users', 'users.id', '=', 'user_roles.user_id')
                ->where('user_roles.role_id', $role->id)
                ->where('users.status', 'ACTIVE')
                ->whereNotNull('users.username')
                ->count();

            if ($eligibleCount < 1) {
                throw new RuntimeException('Operation would remove the last eligible protected administrator.');
            }

            AuditEvent::query()->create([
                'occurred_at' => now(),
                'actor_id' => $actorId,
                'action_code' => $actionCode,
                'target_ref' => $targetRef,
                'outcome' => 'SUCCESS',
                'metadata' => $metadata,
            ]);
        }, 3);
    }

    /** @param array<string, mixed> $metadata */
    private function assertSafeMetadata(array $metadata): void
    {
        foreach (['source', 'reason_code'] as $key) {
            if (isset($metadata[$key]) && (! is_string($metadata[$key])
                || preg_match('/\A[a-z][a-z0-9_.-]{0,39}\z/D', $metadata[$key]) !== 1)) {
                throw new RuntimeException('Protected role mutation is unavailable.');
            }
        }

        if (isset($metadata['correlation_id']) && (! is_string($metadata['correlation_id'])
            || preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/iD', $metadata['correlation_id']) !== 1)) {
            throw new RuntimeException('Protected role mutation is unavailable.');
        }

        if (array_diff(array_keys($metadata), ['source', 'reason_code', 'correlation_id', 'changed_fields']) !== []) {
            throw new RuntimeException('Protected role mutation is unavailable.');
        }
    }
}
