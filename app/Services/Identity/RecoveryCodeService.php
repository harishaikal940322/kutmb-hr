<?php

namespace App\Services\Identity;

use App\Models\AuditEvent;
use App\Models\User;
use App\Models\UserRecoveryCode;
use Illuminate\Support\Facades\DB;

class RecoveryCodeService
{
    /** @return list<string> */
    public function issue(User $user): array
    {
        return DB::transaction(function () use ($user): array {
            UserRecoveryCode::query()->where('user_id', $user->getKey())->delete();

            $codes = [];

            for ($index = 0; $index < 10; $index++) {
                $code = strtoupper(bin2hex(random_bytes(12)));
                $codes[] = $code;

                UserRecoveryCode::query()->create([
                    'user_id' => $user->getKey(),
                    'code_hash' => hash('sha256', $code),
                ]);
            }

            return $codes;
        }, 3);
    }

    public function consume(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code): bool {
            $candidates = UserRecoveryCode::query()
                ->where('user_id', $user->getKey())
                ->whereNull('consumed_at')
                ->lockForUpdate()
                ->get();

            $submittedHash = hash('sha256', strtoupper(trim($code)));
            $match = $candidates->first(fn (UserRecoveryCode $candidate): bool => hash_equals($candidate->code_hash, $submittedHash));

            if ($match === null) {
                return false;
            }

            $match->forceFill(['consumed_at' => now()])->save();

            AuditEvent::query()->create([
                'occurred_at' => now(),
                'actor_id' => $user->getKey(),
                'action_code' => 'iam.mfa.recovery_code_used',
                'target_ref' => 'user:'.$user->getKey(),
                'outcome' => 'SUCCESS',
                'metadata' => ['source' => 'mfa_recovery_code'],
            ]);

            return true;
        }, 3);
    }
}
