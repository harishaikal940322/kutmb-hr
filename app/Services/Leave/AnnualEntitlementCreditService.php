<?php

namespace App\Services\Leave;

use App\Models\AuditEvent;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Local/testing-only confirmation and once-only Jan 1 credit; does not calculate entitlement rates. */
class AnnualEntitlementCreditService
{
    /** @return array{confirmation_id:int,annual_account_id:int,leave_year:int,confirmed_half_day_units:int,replayed:bool} */
    public function confirmAnnualEntitlement(int $actorId, int $annualAccountId, int $confirmedHalfDayUnits, string $confirmationReference, string $confirmationKey): array
    {
        $this->assertLocal();
        $confirmationReference = trim($confirmationReference);
        if ($actorId < 1 || $annualAccountId < 1 || $confirmedHalfDayUnits < 1 || $confirmationReference === ''
            || strlen($confirmationReference) > 120 || preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:-]{0,79}\z/D', $confirmationKey) !== 1) {
            throw new DomainException('An explicit positive HR-confirmed entitlement, source reference and idempotency key are required.');
        }

        return DB::transaction(function () use ($actorId, $annualAccountId, $confirmedHalfDayUnits, $confirmationReference, $confirmationKey): array {
            $candidate = DB::table('leave_annual_accounts')->where('id', $annualAccountId)->first(['employee_id', 'leave_year']);
            if ($candidate === null) {
                throw new DomainException('The Annual Leave account does not exist.');
            }
            $employee = DB::table('employees')->where('id', $candidate->employee_id)->lockForUpdate()->first(['id']);
            $annual = DB::table('leave_annual_accounts')->where('id', $annualAccountId)->lockForUpdate()->first();
            if ($employee === null || $annual === null || (int) $annual->employee_id !== (int) $employee->id
                || (int) $annual->leave_year < (int) now('Asia/Kuala_Lumpur')->format('Y')) {
                throw new DomainException('A current or future Annual Leave account is required for entitlement confirmation.');
            }
            $this->assertAuthorizedActor($actorId, 'leave.entitlement.confirm');

            $payloadHash = $this->hash([
                'actor_id' => $actorId,
                'annual_account_id' => $annualAccountId,
                'confirmation_reference' => $confirmationReference,
                'confirmed_half_day_units' => $confirmedHalfDayUnits,
                'employee_id' => (int) $employee->id,
                'leave_year' => (int) $annual->leave_year,
            ]);
            $existing = DB::table('leave_annual_entitlement_confirmations')->where('confirmation_key', $confirmationKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if ((int) $existing->annual_account_id !== $annualAccountId || (int) $existing->employee_id !== (int) $employee->id
                    || (int) $existing->leave_year !== (int) $annual->leave_year || (int) $existing->confirmed_by !== $actorId
                    || (int) $existing->confirmed_half_day_units !== $confirmedHalfDayUnits
                    || $existing->confirmation_reference !== $confirmationReference || ! hash_equals($existing->payload_hash, $payloadHash)) {
                    throw new DomainException('The entitlement confirmation key was already used for different input.');
                }

                return [
                    'confirmation_id' => (int) $existing->id,
                    'annual_account_id' => (int) $existing->annual_account_id,
                    'leave_year' => (int) $existing->leave_year,
                    'confirmed_half_day_units' => (int) $existing->confirmed_half_day_units,
                    'replayed' => true,
                ];
            }
            if (DB::table('leave_annual_entitlement_confirmations')->where('annual_account_id', $annualAccountId)->exists()) {
                throw new DomainException('This annual account already has an immutable confirmed entitlement. Use the separate authorized adjustment process for corrections.');
            }

            $confirmationId = DB::table('leave_annual_entitlement_confirmations')->insertGetId([
                'annual_account_id' => $annualAccountId,
                'employee_id' => $employee->id,
                'leave_year' => $annual->leave_year,
                'confirmed_half_day_units' => $confirmedHalfDayUnits,
                'confirmation_reference' => $confirmationReference,
                'confirmed_by' => $actorId,
                'confirmation_key' => $confirmationKey,
                'payload_hash' => $payloadHash,
                'created_at' => now(),
            ]);
            $this->audit('hr.leave.annual_entitlement_confirmed', $annualAccountId, $actorId, $confirmationKey);

            return [
                'confirmation_id' => $confirmationId,
                'annual_account_id' => $annualAccountId,
                'leave_year' => (int) $annual->leave_year,
                'confirmed_half_day_units' => $confirmedHalfDayUnits,
                'replayed' => false,
            ];
        }, 3);
    }

    /** @return array{annual_account_id:int,leave_year:int,credited_half_day_units:int,ledger_entry_id:int,replayed:bool} */
    public function creditConfirmedEntitlementOnJanuaryFirst(int $actorId, int $confirmationId): array
    {
        return $this->postConfirmedEntitlement($actorId, $confirmationId, false);
    }

    /** CLI-only opening balance for the explicitly seeded synthetic development fixtures. */
    public function creditDevelopmentFixture(int $actorId, int $confirmationId): array
    {
        if (! app()->environment('local') || ! app()->runningInConsole()
            || config('database.default') !== 'mysql' || DB::connection()->getDatabaseName() !== 'kutmb_hr_dev') {
            throw new DomainException('Development opening balances require the local fixture seeder.');
        }

        return $this->postConfirmedEntitlement($actorId, $confirmationId, true);
    }

    private function postConfirmedEntitlement(int $actorId, int $confirmationId, bool $fixture): array
    {
        $this->assertLocal();
        if ($actorId < 1 || $confirmationId < 1) {
            throw new DomainException('An authorized actor and confirmed annual entitlement are required.');
        }

        return DB::transaction(function () use ($actorId, $confirmationId, $fixture): array {
            $candidate = DB::table('leave_annual_entitlement_confirmations')->where('id', $confirmationId)
                ->first(['annual_account_id', 'employee_id', 'leave_year']);
            if ($candidate === null) {
                throw new DomainException('The confirmed annual entitlement does not exist.');
            }
            $employee = DB::table('employees')->where('id', $candidate->employee_id)->lockForUpdate()->first(['id']);
            $annual = DB::table('leave_annual_accounts')->where('id', $candidate->annual_account_id)->lockForUpdate()->first();
            $confirmation = DB::table('leave_annual_entitlement_confirmations')->where('id', $confirmationId)->lockForUpdate()->first();
            if ($employee === null || $annual === null || $confirmation === null
                || (int) $annual->employee_id !== (int) $employee->id
                || (int) $confirmation->employee_id !== (int) $employee->id
                || (int) $confirmation->annual_account_id !== (int) $annual->id
                || (int) $confirmation->leave_year !== (int) $annual->leave_year) {
                throw new DomainException('The confirmed entitlement and annual account scope are inconsistent.');
            }
            $this->assertAuthorizedActor($actorId, 'leave.entitlement.credit');

            if ($fixture) {
                $target = DB::table('employees as employee')->join('users as user', 'user.id', '=', 'employee.user_id')
                    ->where('employee.id', $employee->id)->first(['employee.employee_no', 'user.id as user_id', 'user.username_canonical']);
                $fixtureAccounts = [
                    'DEV-001' => 'dev-staff', 'DEV-002' => 'dev-unit-manager', 'DEV-003' => 'dev-general-manager',
                    'DEV-004' => 'dev-hr-executive', 'DEV-005' => 'dev-hr-manager', 'DEV-006' => 'dev-it-admin',
                ];
                $actor = User::query()->findOrFail($actorId);
                $recordedUser = static fn (int $id): bool => AuditEvent::query()->where('action_code', 'iam.development_user.seeded')
                    ->where('target_ref', 'user:'.$id)->exists();
                if ($target === null || ($fixtureAccounts[$target->employee_no] ?? null) !== $target->username_canonical
                    || $actor->username_canonical !== 'dev-hr-manager' || ! $actor->hasRole('hr-manager')
                    || ! $recordedUser($actorId) || ! $recordedUser((int) $target->user_id)
                    || ! AuditEvent::query()->where('action_code', 'hr.employee.development_seeded')->where('target_ref', 'employee:'.$employee->id)->exists()
                    || (int) $annual->leave_year !== (int) now('Asia/Kuala_Lumpur')->format('Y')
                    || $confirmation->confirmation_reference !== 'DEV-FIXTURE-OPENING-'.$annual->leave_year
                    || $confirmation->confirmation_key !== 'dev-fixture-entitlement:'.$annual->id.':'.$annual->leave_year
                    || (int) $confirmation->confirmed_by !== $actorId) {
                    throw new DomainException('Only explicitly seeded current-year synthetic employee confirmations can receive development opening credit.');
                }
            }

            $operationKey = 'annual-credit:'.$annual->id.':'.$annual->leave_year;
            $payloadHash = $this->hash([
                'actor_id' => $actorId,
                'annual_account_id' => (int) $annual->id,
                'confirmation_id' => $confirmationId,
                'confirmed_half_day_units' => (int) $confirmation->confirmed_half_day_units,
                'employee_id' => (int) $employee->id,
                'leave_year' => (int) $annual->leave_year,
            ]);
            $existingOperation = DB::table('leave_balance_operations')->where('operation_key', $operationKey)->lockForUpdate()->first();
            if ($existingOperation !== null) {
                if ((int) $existingOperation->employee_id !== (int) $employee->id || (int) $existingOperation->actor_id !== $actorId
                    || $existingOperation->operation_type !== 'ANNUAL_CREDIT'
                    || ! hash_equals($existingOperation->payload_hash, $payloadHash)) {
                    throw new DomainException('The annual credit operation key was already used for a different payload.');
                }
                $existingEntry = DB::table('leave_ledger_entries')->where('balance_operation_id', $existingOperation->id)
                    ->where('operation_key', $operationKey)->where('kind', 'annual_entitlement_credit')->first();
                if ($existingEntry === null || (int) $existingEntry->annual_account_id !== (int) $annual->id
                    || (int) $existingEntry->signed_half_day_units !== (int) $confirmation->confirmed_half_day_units
                    || $existingEntry->tabung_account_id !== null || $existingEntry->source_year !== null
                    || $existingEntry->reverses_entry_id !== null || (int) $existingEntry->actor_id !== (int) $existingOperation->actor_id) {
                    throw new RuntimeException('The persisted annual credit operation does not match its immutable ledger entry.');
                }

                return [
                    'annual_account_id' => (int) $annual->id,
                    'leave_year' => (int) $annual->leave_year,
                    'credited_half_day_units' => (int) $confirmation->confirmed_half_day_units,
                    'ledger_entry_id' => (int) $existingEntry->id,
                    'replayed' => true,
                ];
            }

            $today = now('Asia/Kuala_Lumpur')->toDateString();
            if (! $fixture && $today !== sprintf('%04d-01-01', (int) $annual->leave_year)) {
                throw new DomainException('An annual entitlement can be credited only on January 1; missed-job recovery is not configured.');
            }

            $operationId = DB::table('leave_balance_operations')->insertGetId([
                'employee_id' => $employee->id,
                'actor_id' => $actorId,
                'operation_key' => $operationKey,
                'operation_type' => 'ANNUAL_CREDIT',
                'payload_hash' => $payloadHash,
                'created_at' => now(),
            ]);
            $ledgerEntryId = DB::table('leave_ledger_entries')->insertGetId([
                'annual_account_id' => $annual->id,
                'balance_operation_id' => $operationId,
                'signed_half_day_units' => $confirmation->confirmed_half_day_units,
                'kind' => 'annual_entitlement_credit',
                'source_year' => null,
                'operation_key' => $operationKey,
                'actor_id' => $actorId,
            ]);
            // Convert every still-approved pre-credit commitment in the same transaction.
            $commitments = DB::table('leave_annual_commitments')->where('annual_account_id', $annual->id)
                ->where('status', 'COMMITTED')->orderBy('id')->lockForUpdate()->get();
            if ((int) $commitments->sum('units') > (int) $confirmation->confirmed_half_day_units) {
                throw new DomainException('Approved commitments exceed confirmed entitlement.');
            }
            foreach ($commitments as $commitment) {
                $reservation = DB::table('leave_reservations')->where('id', $commitment->reservation_id)->lockForUpdate()->first();
                $approval = DB::table('leave_balance_operations')->where('id', $commitment->approval_operation_id)->first();
                if ($reservation === null || $reservation->status !== 'COMMITTED' || $approval === null
                    || (int) $reservation->annual_account_id !== (int) $annual->id
                    || (int) $reservation->employee_id !== (int) $employee->id
                    || (int) $reservation->annual_units !== (int) $commitment->units
                    || (int) $reservation->tabung_units !== 0
                    || $approval->operation_type !== 'COMMIT' || (int) $approval->actor_id !== (int) $commitment->approved_by
                    || (int) $approval->employee_id !== (int) $employee->id) {
                    throw new DomainException('Approved commitment history is inconsistent.');
                }
                $usageId = DB::table('leave_ledger_entries')->insertGetId([
                    'annual_account_id' => $annual->id, 'balance_operation_id' => $approval->id,
                    'signed_half_day_units' => -(int) $commitment->units, 'kind' => 'approved_leave_use',
                    'source_year' => null, 'operation_key' => $approval->operation_key.':commitment',
                    'actor_id' => $commitment->approved_by,
                ]);
                DB::table('leave_annual_commitments')->where('id', $commitment->id)->update([
                    'status' => 'SETTLED', 'settled_ledger_entry_id' => $usageId, 'resolved_at' => now(),
                ]);
                $this->audit('hr.leave.future_commitment_settled', (int) $annual->id, $actorId, 'commitment:'.$commitment->id);
            }

            if ($fixture) {
                AuditEvent::query()->create([
                    'occurred_at' => now(), 'actor_id' => null,
                    'action_code' => 'hr.leave.development_opening_credited',
                    'target_ref' => 'leave_annual_account:'.$annual->id, 'outcome' => 'SUCCESS',
                    'metadata' => ['source' => 'development_seeder', 'correlation_id' => $operationKey],
                ]);
            } else {
                $this->audit('hr.leave.annual_entitlement_credited', (int) $annual->id, $actorId, $operationKey);
            }

            return [
                'annual_account_id' => (int) $annual->id,
                'leave_year' => (int) $annual->leave_year,
                'credited_half_day_units' => (int) $confirmation->confirmed_half_day_units,
                'ledger_entry_id' => $ledgerEntryId,
                'replayed' => false,
            ];
        }, 3);
    }

    private function assertAuthorizedActor(int $actorId, string $permission): void
    {
        if (! DB::table('permissions')->where('name', $permission)->where('guard_name', 'web')->exists()) {
            throw new DomainException('The local-only annual entitlement permission is not configured.');
        }
        $lockedActor = DB::table('users')->where('id', $actorId)->lockForUpdate()->first(['status', 'username']);
        $actor = User::query()->find($actorId);
        if ($lockedActor === null || $lockedActor->status !== 'ACTIVE' || $lockedActor->username === null
            || $actor === null || $actor->status !== 'ACTIVE' || $actor->username === null
            || ! $actor->hasPermissionTo($permission, 'web')) {
            throw new DomainException('An active account with explicit annual entitlement authority is required.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function hash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function audit(string $actionCode, int $annualAccountId, int $actorId, string $correlationId): void
    {
        AuditEvent::query()->create([
            'occurred_at' => now(),
            'actor_id' => $actorId,
            'action_code' => $actionCode,
            'target_ref' => 'leave_annual_account:'.$annualAccountId,
            'outcome' => 'SUCCESS',
            'metadata' => ['source' => 'annual_entitlement_credit', 'correlation_id' => $correlationId],
        ]);
    }

    private function assertLocal(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw ValidationException::withMessages(['leave' => 'Annual entitlement operations are unavailable outside local development and testing.']);
        }
    }
}
