<?php

namespace App\Services\Leave;

use App\Models\AuditEvent;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use OverflowException;
use RuntimeException;
use stdClass;

/** Local-only internal transaction primitives; route callers must enforce leave permissions and workflow state. */
class LeaveBalanceTransactionService
{
    public function __construct(
        private readonly LedgerPostedTotals $totals,
        private readonly BalanceProjection $projection,
        private readonly ConfirmedVersionLookup $versions,
        private readonly WorkingSessionCounter $sessionCounter,
    ) {}

    /** @param list<array{date:string,session:string}> $requestedSlots
     * @return array{reservation_id:int,status:string,leave_year:int,annual_units:int,tabung_units:int}
     */
    public function reserve(int $actorId, string $requestKey, int $revision, int $annualAccountId, int $tabungAccountId, array $requestedSlots, string $operationKey): array
    {
        $this->assertLocal();
        $this->validateKeys($requestKey, $operationKey);
        if ($revision < 1 || $annualAccountId < 1 || $tabungAccountId < 1) {
            throw new DomainException('A positive revision and account IDs are required.');
        }
        $slots = $this->normalizeSlots($requestedSlots);
        $requestedUnits = count($slots);

        /** @var array{reservation_id:int,status:string,leave_year:int,annual_units:int,tabung_units:int} $result */
        $result = DB::transaction(function () use ($actorId, $requestKey, $revision, $annualAccountId, $tabungAccountId, $requestedUnits, $slots, $operationKey): array {
            $this->lockRequestImpactGuards();
            $annualCandidate = DB::table('leave_annual_accounts')->where('id', $annualAccountId)->first(['employee_id']);
            if ($annualCandidate === null) {
                throw new DomainException('Annual and Tabung accounts must exist and belong to the same employee.');
            }
            $employee = DB::table('employees')->where('id', $annualCandidate->employee_id)->lockForUpdate()->first();
            $annual = DB::table('leave_annual_accounts')->where('id', $annualAccountId)->lockForUpdate()->first();
            $tabung = DB::table('leave_tabung_accounts')->where('id', $tabungAccountId)->lockForUpdate()->first();
            if ($annual === null || $tabung === null || (int) $annual->employee_id !== (int) $tabung->employee_id
                || $employee === null || (int) $employee->id !== (int) $annual->employee_id) {
                throw new DomainException('Annual and Tabung accounts must exist and belong to the same employee.');
            }
            $this->assertActiveActor($actorId);
            if ((int) $employee->user_id !== $actorId) {
                throw new DomainException('Only the linked active employee can reserve leave for themselves.');
            }
            if (count(array_filter($slots, fn (array $slot): bool => (int) substr($slot['date'], 0, 4) !== (int) $annual->leave_year)) > 0) {
                throw new DomainException('This transaction slice accepts dates from one calendar year matching the Annual Leave account.');
            }

            $payload = [
                'actor_id' => $actorId,
                'annual_account_id' => $annualAccountId,
                'employee_id' => (int) $employee->id,
                'request_key' => $requestKey,
                'requested_slots' => $slots,
                'revision' => $revision,
                'tabung_account_id' => $tabungAccountId,
                'leave_year' => (int) $annual->leave_year,
            ];
            $operation = $this->startOperation($operationKey, 'RESERVE', $payload, (int) $employee->id, $actorId);
            if ($operation['replay']) {
                return $this->reservationResult((int) $operation['event']->reservation_id);
            }
            if (DB::table('leave_reservations')->where('request_key', $requestKey)->exists()) {
                throw new DomainException('This request already has a balance reservation.');
            }
            $this->assertNoActiveSlotConflicts((int) $employee->id, $slots);

            $historyMarkerId = $this->recordUsageSnapshots((int) $employee->id, $actorId, $requestKey, $revision, $slots);
            $posted = $this->totals->forAccounts($annualAccountId, $tabungAccountId);
            $pendingAnnual = $this->sumFor('annual_units', 'annual_account_id', $annualAccountId);
            $pendingTabung = $this->sumFor('tabung_units', 'tabung_account_id', $tabungAccountId);
            $before = $this->projection->project($posted['annual_half_day_units'], $posted['tabung_half_day_units'], $pendingAnnual, $pendingTabung, 0);
            $after = $this->projection->project($posted['annual_half_day_units'], $posted['tabung_half_day_units'], $pendingAnnual, $pendingTabung, $requestedUnits);
            $annualUnits = $before['projected_annual'] - $after['projected_annual'];
            $tabungUnits = $before['projected_tabung'] - $after['projected_tabung'];
            $tabungSourceYear = $tabungUnits > 0 ? $this->singleAvailableTabungSourceYear($tabungAccountId, $tabungUnits) : null;

            $reservationId = DB::table('leave_reservations')->insertGetId([
                'employee_id' => $employee->id,
                'history_marker_id' => $historyMarkerId,
                'request_key' => $requestKey,
                'revision' => $revision,
                'leave_year' => $annual->leave_year,
                'annual_account_id' => $annualAccountId,
                'tabung_account_id' => $tabungAccountId,
                'requested_units' => $requestedUnits,
                'annual_units' => $annualUnits,
                'tabung_units' => $tabungUnits,
                'tabung_source_year' => $tabungSourceYear,
                'status' => 'ACTIVE',
                'active_request_key' => $requestKey,
                'created_by' => $actorId,
                'created_at' => now(),
            ]);
            $this->recordActiveSlots($reservationId, (int) $employee->id, $slots);
            $this->recordEvent($reservationId, $operation['id'], 'RESERVED', $actorId);
            $this->audit('hr.leave.balance_reserved', $reservationId, $operationKey);

            return $this->reservationResult($reservationId);
        }, 3);

        return $result;
    }

    /** @return array{reservation_id:int,status:string,leave_year:int,annual_units:int,tabung_units:int} */
    public function commitUsage(int $actorId, int $reservationId, int $finalAssignmentId, string $operationKey): array
    {
        $this->assertLocal();
        $this->validateKeys((string) $reservationId, $operationKey);

        /** @var array{reservation_id:int,status:string,leave_year:int,annual_units:int,tabung_units:int} $result */
        $result = DB::transaction(function () use ($actorId, $reservationId, $finalAssignmentId, $operationKey): array {
            $candidate = DB::table('leave_reservations')->where('id', $reservationId)->first();
            if ($candidate === null) {
                throw new DomainException('The reservation does not exist.');
            }
            $employee = DB::table('employees')->where('id', $candidate->employee_id)->lockForUpdate()->first();
            $annual = DB::table('leave_annual_accounts')->where('id', $candidate->annual_account_id)->lockForUpdate()->first();
            $tabung = DB::table('leave_tabung_accounts')->where('id', $candidate->tabung_account_id)->lockForUpdate()->first();
            $reservation = DB::table('leave_reservations')->where('id', $reservationId)->lockForUpdate()->first();
            if ($annual === null || $tabung === null || $employee === null || $reservation === null
                || (int) $annual->employee_id !== (int) $employee->id || (int) $tabung->employee_id !== (int) $employee->id
                || (int) $annual->leave_year !== (int) $reservation->leave_year) {
                throw new DomainException('The reservation account scope is inconsistent.');
            }
            $this->assertFinalApprover($actorId, $employee, $finalAssignmentId);
            $payload = ['actor_id' => $actorId, 'assignment_id' => $finalAssignmentId, 'reservation_id' => $reservationId];
            $operation = $this->startOperation($operationKey, 'COMMIT', $payload, (int) $employee->id, $actorId);
            if ($operation['replay']) {
                return $this->reservationResult((int) $operation['event']->reservation_id);
            }
            if ($reservation->status !== 'ACTIVE') {
                throw new DomainException('Only an active pending reservation can be committed.');
            }

            $this->appendUsageEntry($reservation, $operation['id'], $actorId, $operationKey, 'annual_account_id', 'annual_units', null);
            $this->appendUsageEntry($reservation, $operation['id'], $actorId, $operationKey, 'tabung_account_id', 'tabung_units', $reservation->tabung_source_year);
            $this->resolveReservation($reservationId, 'COMMITTED');
            $this->recordEvent($reservationId, $operation['id'], 'COMMITTED', $actorId);
            $this->audit('hr.leave.balance_committed', $reservationId, $operationKey);

            return $this->reservationResult($reservationId);
        }, 3);

        return $result;
    }

    /** @return array{reservation_id:int,status:string,leave_year:int,annual_units:int,tabung_units:int} */
    public function release(int $actorId, int $reservationId, string $reason, string $operationKey, ?int $finalAssignmentId = null): array
    {
        $this->assertLocal();
        $this->validateKeys((string) $reservationId, $operationKey);
        if (! in_array($reason, ['FINAL_REJECTION', 'WITHDRAWAL'], true)) {
            throw new DomainException('Only final rejection or employee withdrawal releases a pending reservation.');
        }

        /** @var array{reservation_id:int,status:string,leave_year:int,annual_units:int,tabung_units:int} $result */
        $result = DB::transaction(function () use ($actorId, $reservationId, $reason, $operationKey, $finalAssignmentId): array {
            $candidate = DB::table('leave_reservations')->where('id', $reservationId)->first();
            if ($candidate === null) {
                throw new DomainException('The reservation does not exist.');
            }
            $employee = DB::table('employees')->where('id', $candidate->employee_id)->lockForUpdate()->first();
            $annual = DB::table('leave_annual_accounts')->where('id', $candidate->annual_account_id)->lockForUpdate()->first();
            $tabung = DB::table('leave_tabung_accounts')->where('id', $candidate->tabung_account_id)->lockForUpdate()->first();
            $reservation = DB::table('leave_reservations')->where('id', $reservationId)->lockForUpdate()->first();
            if ($annual === null || $tabung === null || $employee === null || $reservation === null
                || (int) $annual->employee_id !== (int) $employee->id || (int) $tabung->employee_id !== (int) $employee->id) {
                throw new DomainException('The reservation account scope is inconsistent.');
            }
            $this->assertActiveActor($actorId);
            if ($reason === 'WITHDRAWAL') {
                if ((int) $employee->user_id !== $actorId) {
                    throw new DomainException('Only the linked employee can withdraw their own pending request.');
                }
            } else {
                $this->assertFinalApprover($actorId, $employee, $finalAssignmentId);
            }
            $payload = [
                'actor_id' => $actorId,
                'assignment_id' => $finalAssignmentId,
                'reason' => $reason,
                'reservation_id' => $reservationId,
            ];
            $operation = $this->startOperation($operationKey, 'RELEASE', $payload, (int) $employee->id, $actorId);
            if ($operation['replay']) {
                return $this->reservationResult((int) $operation['event']->reservation_id);
            }
            if ($reservation->status !== 'ACTIVE') {
                throw new DomainException('Only an active pending reservation can be released.');
            }

            $this->resolveReservation($reservationId, 'RELEASED');
            $this->recordEvent($reservationId, $operation['id'], 'RELEASED', $actorId);
            $this->audit('hr.leave.balance_released', $reservationId, $operationKey);

            return $this->reservationResult($reservationId);
        }, 3);

        return $result;
    }

    /** @param array<string, mixed> $payload
     * @return array{id:int,event:?stdClass,replay:bool}
     */
    private function startOperation(string $key, string $type, array $payload, int $employeeId, int $actorId): array
    {
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $inserted = DB::table('leave_balance_operations')->insertOrIgnore([
            'employee_id' => $employeeId,
            'actor_id' => $actorId,
            'operation_key' => $key,
            'operation_type' => $type,
            'payload_hash' => $hash,
            'created_at' => now(),
        ]);
        $operation = DB::table('leave_balance_operations')->where('operation_key', $key)->lockForUpdate()->first();
        if ($operation === null || (int) $operation->employee_id !== $employeeId || (int) $operation->actor_id !== $actorId
            || $operation->operation_type !== $type || ! hash_equals($operation->payload_hash, $hash)) {
            throw new DomainException('The idempotency key was already used for a different balance operation.');
        }

        if ($inserted === 0) {
            $event = DB::table('leave_reservation_events')->where('balance_operation_id', $operation->id)->first();
            if ($event === null) {
                throw new RuntimeException('A completed balance operation has no reservation event.');
            }

            return ['id' => (int) $operation->id, 'event' => $event, 'replay' => true];
        }

        return ['id' => (int) $operation->id, 'event' => null, 'replay' => false];
    }

    /**
     * @param  array<array-key, mixed>  $requestedSlots
     * @return list<array{date:string,session:string}>
     */
    private function normalizeSlots(array $requestedSlots): array
    {
        if (! array_is_list($requestedSlots) || $requestedSlots === []) {
            throw new DomainException('At least one requested half-day session is required.');
        }
        $normalized = [];
        $seen = [];
        foreach ($requestedSlots as $slot) {
            if (! is_array($slot) || ! isset($slot['date'], $slot['session']) || ! is_string($slot['date']) || ! is_string($slot['session'])) {
                throw new DomainException('Each requested session must contain a date and session code.');
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $slot['date'], new DateTimeZone('Asia/Kuala_Lumpur'));
            if ($date === false || $date->format('Y-m-d') !== $slot['date'] || ! in_array($slot['session'], ['AM', 'PM'], true)) {
                throw new DomainException('A valid calendar date and AM/PM session are required.');
            }
            $key = $slot['date'].':'.$slot['session'];
            if (isset($seen[$key])) {
                throw new DomainException('A requested half-day session cannot be duplicated.');
            }
            $seen[$key] = true;
            $normalized[] = ['date' => $slot['date'], 'session' => $slot['session']];
        }
        usort($normalized, fn (array $left, array $right): int => [$left['date'], $left['session']] <=> [$right['date'], $right['session']]);

        return $normalized;
    }

    private function lockRequestImpactGuards(): void
    {
        foreach (['leave_calendar_confirmation_guard', 'leave_working_schedule_guard'] as $table) {
            if (! Schema::hasTable($table) || DB::table($table)->where('id', 1)->lockForUpdate()->first() === null) {
                throw new RuntimeException('Leave calendar and schedule impact guards must be available before reservation.');
            }
        }
        if (! Schema::hasTable('leave_calendar_usage_snapshots') || ! Schema::hasTable('leave_employee_history_markers')) {
            throw new RuntimeException('Leave impact snapshot and employee history tables must be available before reservation.');
        }
    }

    /** @param list<array{date:string,session:string}> $slots */
    private function assertNoActiveSlotConflicts(int $employeeId, array $slots): void
    {
        foreach ($slots as $slot) {
            if (DB::table('leave_active_slots')->where('employee_id', $employeeId)
                ->where('leave_date', $slot['date'])->where('session_code', $slot['session'])
                ->where('active_slot_key', 1)->exists()) {
                throw new DomainException('The requested session overlaps active or approved leave.');
            }
        }
    }

    /** @param list<array{date:string,session:string}> $slots */
    private function recordActiveSlots(int $reservationId, int $employeeId, array $slots): void
    {
        $now = now();
        DB::table('leave_active_slots')->insert(array_map(fn (array $slot): array => [
            'reservation_id' => $reservationId,
            'employee_id' => $employeeId,
            'leave_date' => $slot['date'],
            'session_code' => $slot['session'],
            'active_slot_key' => 1,
            'status' => 'ACTIVE',
            'created_at' => $now,
        ], $slots));
    }

    /** @param list<array{date:string,session:string}> $slots */
    private function recordUsageSnapshots(int $employeeId, int $actorId, string $requestKey, int $revision, array $slots): int
    {
        $markerId = DB::table('leave_employee_history_markers')->insertGetId([
            'employee_id' => $employeeId,
            'request_key' => $requestKey,
            'revision' => $revision,
            'actor_id' => $actorId,
            'created_at' => now(),
        ]);
        $byDate = [];
        foreach ($slots as $slot) {
            $byDate[$slot['date']][] = $slot;
        }
        foreach ($byDate as $date => $dateSlots) {
            $schedule = $this->versions->workingScheduleFor($date);
            $calendar = $this->versions->calendarFor($date);
            $eligibleUnits = $this->sessionCounter->countUnits($dateSlots, $schedule->working_weekdays, $calendar['holidays']);
            if ($eligibleUnits !== count($dateSlots)) {
                throw new DomainException('Requested sessions must fall on configured working days outside the confirmed holiday calendar.');
            }
            DB::table('leave_calendar_usage_snapshots')->insert([
                'history_marker_id' => $markerId,
                'employee_id' => $employeeId,
                'leave_date' => $date,
                'leave_units' => count($dateSlots),
                'calendar_version_id' => $calendar['version']->id,
                'policy_version_id' => $schedule->id,
                'created_at' => now(),
            ]);
        }

        return $markerId;
    }

    private function singleAvailableTabungSourceYear(int $tabungAccountId, int $unitsNeeded): int
    {
        $cohorts = DB::table('leave_ledger_entries')->where('tabung_account_id', $tabungAccountId)
            ->select('source_year')->selectRaw('CAST(COALESCE(SUM(signed_half_day_units), 0) AS CHAR) AS units')
            ->groupBy('source_year')->get();
        $pending = DB::table('leave_reservations')->where('tabung_account_id', $tabungAccountId)->where('status', 'ACTIVE')
            ->select('tabung_source_year')->selectRaw('CAST(COALESCE(SUM(tabung_units), 0) AS CHAR) AS units')
            ->groupBy('tabung_source_year')->get()->keyBy(fn (stdClass $row): string => $row->tabung_source_year === null ? 'null' : (string) $row->tabung_source_year);

        $available = [];
        foreach ($cohorts as $cohort) {
            $yearKey = $cohort->source_year === null ? 'null' : (string) $cohort->source_year;
            $balance = $this->integerTotal($cohort->units);
            $reserved = $this->integerTotal($pending[$yearKey]->units ?? '0');
            if ($balance < 0 || $reserved < 0 || $reserved > $balance) {
                throw new DomainException('Tabung source-year balance or reservation is inconsistent.');
            }
            $free = $balance - $reserved;
            if ($free > 0) {
                if ($cohort->source_year === null) {
                    throw new DomainException('Tabung source-year provenance is required before allocation.');
                }
                $available[(int) $cohort->source_year] = $free;
            }
        }
        foreach ($pending as $yearKey => $row) {
            if (! $cohorts->contains(fn (stdClass $cohort): bool => ($cohort->source_year === null ? 'null' : (string) $cohort->source_year) === $yearKey)
                && $this->integerTotal($row->units) > 0) {
                throw new DomainException('A Tabung reservation has no matching source-year entry.');
            }
        }
        if (count($available) !== 1) {
            throw new DomainException('Tabung use is deferred while more than one source year is available.');
        }
        $sourceYear = (int) array_key_first($available);
        if ($available[$sourceYear] < $unitsNeeded) {
            throw new DomainException('The selected Tabung source year cannot cover the requested allocation.');
        }

        return $sourceYear;
    }

    private function appendUsageEntry(stdClass $reservation, int $operationId, int $actorId, string $operationKey, string $accountColumn, string $unitsColumn, ?int $sourceYear): void
    {
        $units = (int) $reservation->{$unitsColumn};
        if ($units === 0) {
            return;
        }
        $suffix = $accountColumn === 'annual_account_id' ? 'annual' : 'tabung';
        DB::table('leave_ledger_entries')->insert([
            $accountColumn => $reservation->{$accountColumn},
            'balance_operation_id' => $operationId,
            'signed_half_day_units' => -$units,
            'kind' => 'approved_leave_use',
            'source_year' => $sourceYear,
            'operation_key' => $operationKey.':'.$suffix,
            'actor_id' => $actorId,
        ]);
    }

    private function assertFinalApprover(int $actorId, stdClass $employee, ?int $assignmentId): void
    {
        $this->assertActiveActor($actorId);
        if ($assignmentId === null || (int) $employee->user_id === $actorId) {
            throw new DomainException('A distinct, assigned final approver is required.');
        }
        $today = now()->toDateString();
        $assignment = DB::table('approval_assignments')->where('id', $assignmentId)->where('user_id', $actorId)
            ->whereIn('office_code', ['PB', 'SECRETARY_ALK'])->where('effective_from', '<=', $today)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
            ->lockForUpdate()->first();
        if ($assignment === null) {
            throw new DomainException('A current final-approval office assignment is required.');
        }
    }

    private function assertActiveActor(int $actorId): void
    {
        $actor = DB::table('users')->where('id', $actorId)->first(['status', 'username']);
        if ($actor === null || $actor->status !== 'ACTIVE' || $actor->username === null) {
            throw new DomainException('An active account is required for leave balance operations.');
        }
    }

    private function resolveReservation(int $reservationId, string $status): void
    {
        $reservation = DB::table('leave_reservations')->where('id', $reservationId)->first(['requested_units']);
        if ($reservation === null) {
            throw new RuntimeException('The balance reservation is missing during its terminal transition.');
        }
        $updated = DB::table('leave_reservations')->where('id', $reservationId)->update([
            'status' => $status,
            'active_request_key' => null,
            'resolved_at' => now(),
        ]);
        if ($updated !== 1) {
            throw new RuntimeException('The balance reservation did not transition exactly once.');
        }
        $slotUpdates = DB::table('leave_active_slots')->where('reservation_id', $reservationId)->where('status', 'ACTIVE')
            ->update([
                'status' => $status,
                'active_slot_key' => $status === 'RELEASED' ? null : 1,
                'resolved_at' => now(),
            ]);
        if ($slotUpdates !== (int) $reservation->requested_units) {
            throw new RuntimeException('The reservation slot claims did not transition atomically.');
        }
    }

    private function recordEvent(int $reservationId, int $operationId, string $eventCode, int $actorId): void
    {
        DB::table('leave_reservation_events')->insert([
            'reservation_id' => $reservationId,
            'balance_operation_id' => $operationId,
            'event_code' => $eventCode,
            'actor_id' => $actorId,
            'created_at' => now(),
        ]);
    }

    /** @return array{reservation_id:int,status:string,leave_year:int,annual_units:int,tabung_units:int} */
    private function reservationResult(int $reservationId): array
    {
        $reservation = DB::table('leave_reservations')->where('id', $reservationId)->first();
        if ($reservation === null) {
            throw new RuntimeException('The balance operation refers to a missing reservation.');
        }

        return [
            'reservation_id' => (int) $reservation->id,
            'status' => $reservation->status,
            'leave_year' => (int) $reservation->leave_year,
            'annual_units' => (int) $reservation->annual_units,
            'tabung_units' => (int) $reservation->tabung_units,
        ];
    }

    private function sumFor(string $sumColumn, string $accountColumn, int $accountId): int
    {
        $expression = match ($sumColumn) {
            'annual_units' => 'CAST(COALESCE(SUM(annual_units), 0) AS CHAR) AS units',
            'tabung_units' => 'CAST(COALESCE(SUM(tabung_units), 0) AS CHAR) AS units',
            default => throw new DomainException('The requested reservation total is unsupported.'),
        };
        $total = DB::table('leave_reservations')->where($accountColumn, $accountId)->where('status', 'ACTIVE')
            ->selectRaw($expression)->value('units');

        return $this->integerTotal($total);
    }

    private function integerTotal(mixed $value): int
    {
        $integer = filter_var((string) $value, FILTER_VALIDATE_INT);
        if ($integer === false) {
            throw new OverflowException('Leave balance totals exceed the supported integer range.');
        }

        return $integer;
    }

    private function audit(string $actionCode, int $reservationId, string $operationKey): void
    {
        $operation = DB::table('leave_balance_operations')->where('operation_key', $operationKey)->first(['actor_id']);
        if ($operation === null) {
            throw new RuntimeException('The balance operation audit actor is missing.');
        }
        AuditEvent::query()->create([
            'occurred_at' => now(),
            'actor_id' => $operation->actor_id,
            'action_code' => $actionCode,
            'target_ref' => 'leave_reservation:'.$reservationId,
            'outcome' => 'SUCCESS',
            'metadata' => ['source' => 'leave_balance_transaction', 'correlation_id' => $operationKey],
        ]);
    }

    private function validateKeys(string $requestKey, string $operationKey): void
    {
        if (preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:-]{0,119}\z/D', $requestKey) !== 1
            || preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:-]{0,87}\z/D', $operationKey) !== 1) {
            throw new DomainException('Request and operation keys have an invalid format or length.');
        }
    }

    private function assertLocal(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw ValidationException::withMessages(['leave' => 'Leave balance operations are unavailable outside local development and testing.']);
        }
    }
}
