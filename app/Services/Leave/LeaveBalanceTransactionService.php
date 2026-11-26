<?php

namespace App\Services\Leave;

use App\Models\AuditEvent;
use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Illuminate\Support\Collection;
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
     * @param  null|array{request_id:int,applicant_user_id:int,submission_key:string,payload_hash:string}  $onBehalfSubmission
     * @return array{reservation_id:int,status:string,leave_year:int,annual_units:int,tabung_units:int}
     */
    public function reserve(int $actorId, string $requestKey, int $revision, int $annualAccountId, int $tabungAccountId, array $requestedSlots, string $operationKey, ?array $onBehalfSubmission = null): array
    {
        $this->assertLocal();
        $this->validateKeys($requestKey, $operationKey);
        if ($revision < 1 || $annualAccountId < 1 || $tabungAccountId < 1) {
            throw new DomainException('A positive revision and account IDs are required.');
        }
        $slots = $this->normalizeSlots($requestedSlots);
        $requestedUnits = count($slots);

        /** @var array{reservation_id:int,status:string,leave_year:int,annual_units:int,tabung_units:int} $result */
        $result = DB::transaction(function () use ($actorId, $requestKey, $revision, $annualAccountId, $tabungAccountId, $requestedUnits, $slots, $operationKey, $onBehalfSubmission): array {
            $this->lockRequestImpactGuards();
            $annualCandidate = DB::table('leave_annual_accounts')->where('id', $annualAccountId)->first(['employee_id']);
            if ($annualCandidate === null) {
                throw new DomainException('Annual and Tabung accounts must exist and belong to the same employee.');
            }
            $employee = DB::table('employees')->where('id', $annualCandidate->employee_id)->lockForUpdate()->first();
            $slotYears = array_values(array_unique(array_map(fn (array $slot): int => (int) substr($slot['date'], 0, 4), $slots)));
            sort($slotYears, SORT_NUMERIC);
            $annualAccounts = DB::table('leave_annual_accounts')->where('employee_id', $annualCandidate->employee_id)
                ->whereIn('leave_year', $slotYears)->orderBy('id')->lockForUpdate()->get()->keyBy('leave_year');
            $annual = $annualAccounts->firstWhere('id', $annualAccountId);
            $tabung = DB::table('leave_tabung_accounts')->where('id', $tabungAccountId)->lockForUpdate()->first();
            if ($annual === null || $tabung === null || (int) $annual->employee_id !== (int) $tabung->employee_id
                || $employee === null || (int) $employee->id !== (int) $annual->employee_id) {
                throw new DomainException('Annual and Tabung accounts must exist and belong to the same employee.');
            }
            $this->assertActiveActor($actorId);
            if ((int) $employee->user_id === $actorId) {
                if ($onBehalfSubmission !== null) {
                    throw new DomainException('An employee self-submission cannot include an HR-assisted consent record.');
                }
            } else {
                $this->assertAuthorizedHrRecorder($actorId);
                if ($onBehalfSubmission === null || (int) $onBehalfSubmission['applicant_user_id'] !== (int) $employee->user_id) {
                    throw new DomainException('Only the linked applicant or an explicitly authorized HR recorder with recorded staff confirmation can reserve leave.');
                }
                $request = DB::table('leave_requests')->where('id', $onBehalfSubmission['request_id'])
                    ->where('request_key', $requestKey)->where('requester_user_id', $onBehalfSubmission['applicant_user_id'])->first();
                $consent = DB::table('leave_request_on_behalf_submissions')->where('request_id', $onBehalfSubmission['request_id'])
                    ->where('applicant_user_id', $onBehalfSubmission['applicant_user_id'])->where('hr_actor_user_id', $actorId)
                    ->where('operation_key', $onBehalfSubmission['submission_key'])->first();
                if ($request === null || $consent === null || ! hash_equals($request->payload_hash, $onBehalfSubmission['payload_hash'])
                    || ! hash_equals($consent->payload_hash, $onBehalfSubmission['payload_hash'])) {
                    throw new DomainException('A matching immutable applicant consent record is required before creating the balance reservation.');
                }
            }
            $currentYear = (int) now('Asia/Kuala_Lumpur')->format('Y');
            $crossYear = count($slotYears) > 1;
            if ($annualAccounts->count() !== count($slotYears) || (int) $annual->leave_year !== $slotYears[0]
                || ($crossYear && ($slotYears !== [$currentYear, $currentYear + 1] || ! Schema::hasTable('leave_reservation_slot_allocations')))) {
                throw new DomainException('A cross-year request needs separate current and next-year annual accounts.');
            }
            if ((int) $annual->leave_year > $currentYear + 1) {
                throw new DomainException('Only the current or immediately following year is supported by the documented future-year policy.');
            }
            $registeredEntitlement = DB::table('leave_annual_entitlement_confirmations')
                ->where('annual_account_id', $annualAccountId)
                ->where('employee_id', $employee->id)
                ->where('leave_year', $annual->leave_year)
                ->lockForUpdate()
                ->first(['confirmed_half_day_units']);
            if ((int) $annual->leave_year > $currentYear && $registeredEntitlement === null) {
                throw new DomainException('A future-year Annual Leave entitlement must be registered and HR-confirmed before submission.');
            }

            $payload = [
                'actor_id' => $actorId,
                'annual_account_id' => $annualAccountId,
                'employee_id' => (int) $employee->id,
                ...($onBehalfSubmission === null ? [] : ['on_behalf_submission' => [
                    'applicant_user_id' => $onBehalfSubmission['applicant_user_id'],
                    'payload_hash' => $onBehalfSubmission['payload_hash'],
                    'request_id' => $onBehalfSubmission['request_id'],
                    'submission_key' => $onBehalfSubmission['submission_key'],
                ]]),
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
            if (DB::table('leave_reservations')->where('request_key', $requestKey)->where('revision', $revision)->exists()) {
                throw new DomainException('This request revision already has a balance reservation.');
            }
            $this->assertNoActiveSlotConflicts((int) $employee->id, $slots);

            $historyMarkerId = $this->recordUsageSnapshots((int) $employee->id, $actorId, $requestKey, $revision, $slots);
            if ($crossYear) {
                // CT-DEC-034: reserve each working session from its calendar-year account.
                // Shortage/Tabung/negative carry branches stay fail-closed, not guessed.
                $annualUnits = $requestedUnits;
                $tabungUnits = 0;
                $tabungSourceYear = null;
                foreach ($slotYears as $slotYear) {
                    $account = $annualAccounts->get($slotYear);
                    $confirmed = DB::table('leave_annual_entitlement_confirmations')->where('annual_account_id', $account->id)->lockForUpdate()->first();
                    if ($confirmed === null) {
                        throw new DomainException('Both calendar years require registered and confirmed entitlement.');
                    }
                    $creditEntry = DB::table('leave_ledger_entries')->where('annual_account_id', $account->id)
                        ->where('kind', 'annual_entitlement_credit')->first(['signed_half_day_units']);
                    $postedForYear = $this->totals->forAccounts($account->id, $tabungAccountId)['annual_half_day_units'];
                    if ($slotYear === $currentYear) {
                        if ($creditEntry === null || (int) $creditEntry->signed_half_day_units !== (int) $confirmed->confirmed_half_day_units) {
                            throw new DomainException('Current-year entitlement must be officially credited.');
                        }
                    } else {
                        if ($creditEntry !== null || DB::table('leave_ledger_entries')->where('annual_account_id', $account->id)->exists()) {
                            throw new DomainException('Next-year opening ledger must remain unposted until official credit.');
                        }
                        $postedForYear = (int) $confirmed->confirmed_half_day_units - (int) DB::table('leave_annual_commitments')
                            ->where('annual_account_id', $account->id)->where('status', 'COMMITTED')->sum('units');
                    }
                    $needed = count(array_filter($slots, fn (array $slot): bool => (int) substr($slot['date'], 0, 4) === $slotYear));
                    if ($postedForYear - $this->sumFor('annual_units', 'annual_account_id', (int) $account->id) < $needed) {
                        throw new DomainException('Cross-year AL requires sufficient annual balance in each year. Tabung and deficit transitions await HR reconciliation rules.');
                    }
                }
                $allocationRows = [];
                foreach ($slots as $slot) {
                    $slotYear = (int) substr($slot['date'], 0, 4);
                    $allocationRows[] = [
                        'leave_year' => $slotYear, 'leave_date' => $slot['date'], 'session_code' => $slot['session'],
                        'allocation_bucket' => 'ANNUAL', 'annual_account_id' => $annualAccounts->get($slotYear)->id,
                        'tabung_account_id' => null, 'source_year' => null, 'units' => 1, 'created_at' => now(),
                    ];
                }
            } else {
                $posted = $this->totals->forAccounts($annualAccountId, $tabungAccountId);
                if ($registeredEntitlement !== null && (int) $annual->leave_year >= $currentYear) {
                    $creditEntry = DB::table('leave_ledger_entries')->where('annual_account_id', $annualAccountId)
                        ->where('kind', 'annual_entitlement_credit')->first(['signed_half_day_units']);
                    if ($creditEntry === null) {
                        if ((int) $annual->leave_year === $currentYear) {
                            throw new DomainException('The current-year confirmed entitlement must be officially credited before use.');
                        }
                        $confirmedUnits = (int) $registeredEntitlement->confirmed_half_day_units;
                        if ($posted['annual_half_day_units'] > PHP_INT_MAX - $confirmedUnits) {
                            throw new OverflowException('The confirmed future-year entitlement exceeds the supported integer range.');
                        }
                        $commitments = (int) DB::table('leave_annual_commitments')->where('annual_account_id', $annualAccountId)
                            ->where('status', 'COMMITTED')->sum('units');
                        $posted['annual_half_day_units'] += $confirmedUnits - $commitments;
                    } elseif ((int) $creditEntry->signed_half_day_units !== (int) $registeredEntitlement->confirmed_half_day_units) {
                        throw new DomainException('The posted annual credit does not match its registered future-year entitlement.');
                    }
                }
                $pendingAnnual = $this->sumFor('annual_units', 'annual_account_id', $annualAccountId);
                $pendingTabung = $this->sumFor('tabung_units', 'tabung_account_id', $tabungAccountId);
                $before = $this->projection->project($posted['annual_half_day_units'], $posted['tabung_half_day_units'], $pendingAnnual, $pendingTabung, 0);
                $after = $this->projection->project($posted['annual_half_day_units'], $posted['tabung_half_day_units'], $pendingAnnual, $pendingTabung, $requestedUnits);
                $annualUnits = $before['projected_annual'] - $after['projected_annual'];
                $tabungUnits = $before['projected_tabung'] - $after['projected_tabung'];
                if ((int) $annual->leave_year > $currentYear && ($tabungUnits > 0 || $after['projected_annual'] < 0)) {
                    throw new DomainException('Pre-credit reservations requiring future Tabung or a negative balance await the year-transition transaction contract.');
                }
                $tabungSourceYear = $tabungUnits > 0 ? $this->singleAvailableTabungSourceYear($tabungAccountId, $tabungUnits) : null;
                $annualPositiveUnits = min(max($before['annual_available'], 0), $requestedUnits);
                $allocationRows = [];
                foreach ($slots as $index => $slot) {
                    $isTabung = $index >= $annualPositiveUnits && $index < $annualPositiveUnits + $tabungUnits;
                    $allocationRows[] = [
                        'leave_year' => (int) $annual->leave_year,
                        'leave_date' => $slot['date'],
                        'session_code' => $slot['session'],
                        'allocation_bucket' => $isTabung ? 'TABUNG' : 'ANNUAL',
                        'annual_account_id' => $isTabung ? null : $annualAccountId,
                        'tabung_account_id' => $isTabung ? $tabungAccountId : null,
                        'source_year' => $isTabung ? $tabungSourceYear : null,
                        'units' => 1,
                        'created_at' => now(),
                    ];
                }

            }

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
            if (Schema::hasTable('leave_reservation_slot_allocations')) {
                DB::table('leave_reservation_slot_allocations')->insert(array_map(
                    fn (array $row): array => ['reservation_id' => $reservationId, ...$row],
                    $allocationRows,
                ));
            }
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
            if ($employee === null) {
                throw new DomainException('A consistent employee reservation is required.');
            }
            $lockedAccounts = $this->lockReservationAccounts($candidate, $employee);
            $annual = $lockedAccounts['annual_accounts']->get((int) $candidate->annual_account_id);
            $tabung = $lockedAccounts['tabung'];
            $reservation = DB::table('leave_reservations')->where('id', $reservationId)->lockForUpdate()->first();
            if ($reservation === null
                || (int) $annual->employee_id !== (int) $employee->id || (int) $tabung->employee_id !== (int) $employee->id
                || (int) $annual->leave_year !== (int) $reservation->leave_year) {
                throw new DomainException('The reservation account scope is inconsistent.');
            }
            $allocations = Schema::hasTable('leave_reservation_slot_allocations')
                ? DB::table('leave_reservation_slot_allocations')->where('reservation_id', $reservationId)->orderBy('leave_year')->orderBy('leave_date')->orderBy('session_code')->lockForUpdate()->get()
                : collect();
            $annualUnitsByAccount = $this->assertSlotAllocationConsistency($reservation, $employee, $tabung, $lockedAccounts['annual_accounts'], $allocations);
            $this->assertFinalApprover($actorId, $employee, $finalAssignmentId);
            $payload = ['actor_id' => $actorId, 'assignment_id' => $finalAssignmentId, 'reservation_id' => $reservationId];
            $operation = $this->startOperation($operationKey, 'COMMIT', $payload, (int) $employee->id, $actorId);
            if ($operation['replay']) {
                return $this->reservationResult((int) $operation['event']->reservation_id);
            }
            if ($reservation->status !== 'ACTIVE') {
                throw new DomainException('Only an active pending reservation can be committed.');
            }
            $currentYear = (int) now('Asia/Kuala_Lumpur')->format('Y');
            $hasCommitment = false;
            foreach ($annualUnitsByAccount as $accountId => $units) {
                $account = $lockedAccounts['annual_accounts']->get((int) $accountId);
                $accountYear = (int) $account->leave_year;
                $confirmation = DB::table('leave_annual_entitlement_confirmations')->where('annual_account_id', $accountId)->lockForUpdate()->first();
                $credit = DB::table('leave_ledger_entries')->where('annual_account_id', $accountId)->where('kind', 'annual_entitlement_credit')->first();
                if ($accountYear === $currentYear + 1 && $credit === null) {
                    $committed = (int) DB::table('leave_annual_commitments')->where('annual_account_id', $accountId)->where('status', 'COMMITTED')->sum('units');
                    $pending = $this->sumFor('annual_units', 'annual_account_id', (int) $accountId);
                    if ($confirmation === null || (int) $reservation->tabung_units !== 0 || $hasCommitment
                        || $committed + $pending > (int) $confirmation->confirmed_half_day_units
                        || DB::table('leave_ledger_entries')->where('annual_account_id', $accountId)->exists()) {
                        throw new DomainException('A confirmed next-year annual-only reservation within entitlement is required.');
                    }
                    DB::table('leave_annual_commitments')->insert([
                        'reservation_id' => $reservationId, 'annual_account_id' => $accountId,
                        'approval_operation_id' => $operation['id'], 'approved_by' => $actorId,
                        'units' => $units, 'status' => 'COMMITTED',
                    ]);
                    $hasCommitment = true;
                    continue;
                }
                if ($accountYear > $currentYear || ($confirmation !== null && ($credit === null
                    || (int) $credit->signed_half_day_units !== (int) $confirmation->confirmed_half_day_units))) {
                    throw new DomainException('The annual entitlement has not been officially credited.');
                }

                $entryReservation = (object) ['annual_account_id' => $accountId, 'annual_units' => $units];
                $entryKey = $allocations->isEmpty() ? $operationKey : $operationKey.':year-'.$accountYear;
                $this->appendUsageEntry($entryReservation, $operation['id'], $actorId, $entryKey, 'annual_account_id', 'annual_units', null);
            }
            $this->appendUsageEntry($reservation, $operation['id'], $actorId, $operationKey, 'tabung_account_id', 'tabung_units', $reservation->tabung_source_year);
            $this->resolveReservation($reservationId, 'COMMITTED');
            $this->recordEvent($reservationId, $operation['id'], 'COMMITTED', $actorId);
            $this->audit($hasCommitment ? 'hr.leave.future_year_committed' : 'hr.leave.balance_committed', $reservationId, $operationKey);

            return $this->reservationResult($reservationId);
        }, 3);

        return $result;
    }

    /** The caller must first authorize the final rejection of a retrospective EL request. */
    /** @return array{reservation_id:int,status:string,leave_year:int,annual_units:int,tabung_units:int} */
    public function debitRetrospectiveElRejection(int $actorId, int $reservationId, int $finalAssignmentId, string $operationKey): array
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
            if ($employee === null) {
                throw new DomainException('A consistent employee reservation is required.');
            }
            $lockedAccounts = $this->lockReservationAccounts($candidate, $employee);
            $annual = $lockedAccounts['annual_accounts']->get((int) $candidate->annual_account_id);
            $tabung = $lockedAccounts['tabung'];
            $reservation = DB::table('leave_reservations')->where('id', $reservationId)->lockForUpdate()->first();
            if ((int) $annual->employee_id !== (int) $employee->id || (int) $tabung->employee_id !== (int) $employee->id
                || (int) $annual->leave_year !== (int) $reservation->leave_year) {
                throw new DomainException('The reservation account scope is inconsistent.');
            }
            $this->assertFinalApprover($actorId, $employee, $finalAssignmentId);
            $payload = ['actor_id' => $actorId, 'assignment_id' => $finalAssignmentId, 'reservation_id' => $reservationId];
            $operation = $this->startOperation($operationKey, 'EL_REJECTION_DEBIT', $payload, (int) $employee->id, $actorId);
            if ($operation['replay']) {
                return $this->reservationResult((int) $operation['event']->reservation_id);
            }
            if ($reservation->status !== 'ACTIVE') {
                throw new DomainException('Only an active pending reservation can settle a retrospective EL rejection.');
            }

            $posted = $this->totals->forAccounts((int) $annual->id, (int) $tabung->id);
            if (($reservation->annual_units > 0 && $posted['annual_half_day_units'] < $reservation->annual_units)
                || ($reservation->tabung_units > 0 && $posted['tabung_half_day_units'] < $reservation->tabung_units)) {
                throw new DomainException('Baki bagi penolakan EL tidak mencukupi. Kes ini memerlukan semakan HR.');
            }

            $this->appendUsageEntry($reservation, $operation['id'], $actorId, $operationKey, 'annual_account_id', 'annual_units', null, 'rejected_el_absence_use');
            $this->appendUsageEntry($reservation, $operation['id'], $actorId, $operationKey, 'tabung_account_id', 'tabung_units', $reservation->tabung_source_year, 'rejected_el_absence_use');
            $this->resolveReservation($reservationId, 'COMMITTED');
            $this->recordEvent($reservationId, $operation['id'], 'EL_REJECTED', $actorId);
            $this->audit('hr.leave.retrospective_el_rejection_debited', $reservationId, $operationKey);

            return $this->reservationResult($reservationId);
        }, 3);

        return $result;
    }

    /** @return array{reservation_id:int,status:string,leave_year:int,annual_units:int,tabung_units:int} */
    public function release(int $actorId, int $reservationId, string $reason, string $operationKey, ?int $finalAssignmentId = null): array
    {
        $this->assertLocal();
        $this->validateKeys((string) $reservationId, $operationKey);
        if (! in_array($reason, ['FINAL_REJECTION', 'WITHDRAWAL', 'CORRECTION_RETURN'], true)) {
            throw new DomainException('Only final rejection, employee withdrawal, or authorized correction return releases a pending reservation.');
        }

        /** @var array{reservation_id:int,status:string,leave_year:int,annual_units:int,tabung_units:int} $result */
        $result = DB::transaction(function () use ($actorId, $reservationId, $reason, $operationKey, $finalAssignmentId): array {
            $candidate = DB::table('leave_reservations')->where('id', $reservationId)->first();
            if ($candidate === null) {
                throw new DomainException('The reservation does not exist.');
            }
            $employee = DB::table('employees')->where('id', $candidate->employee_id)->lockForUpdate()->first();
            if ($employee === null) {
                throw new DomainException('A consistent employee reservation is required.');
            }
            $lockedAccounts = $this->lockReservationAccounts($candidate, $employee);
            $annual = $lockedAccounts['annual_accounts']->get((int) $candidate->annual_account_id);
            $tabung = $lockedAccounts['tabung'];
            $reservation = DB::table('leave_reservations')->where('id', $reservationId)->lockForUpdate()->first();
            if ($reservation === null
                || (int) $annual->employee_id !== (int) $employee->id || (int) $tabung->employee_id !== (int) $employee->id) {
                throw new DomainException('The reservation account scope is inconsistent.');
            }
            $allocations = Schema::hasTable('leave_reservation_slot_allocations')
                ? DB::table('leave_reservation_slot_allocations')->where('reservation_id', $reservationId)->orderBy('leave_year')->orderBy('leave_date')->orderBy('session_code')->lockForUpdate()->get()
                : collect();
            $this->assertSlotAllocationConsistency($reservation, $employee, $tabung, $lockedAccounts['annual_accounts'], $allocations);
            $this->assertActiveActor($actorId);
            if ($reason === 'WITHDRAWAL') {
                if ((int) $employee->user_id !== $actorId) {
                    throw new DomainException('Only the linked employee can withdraw their own pending request.');
                }
            } elseif ($reason === 'CORRECTION_RETURN') {
                $this->assertAssignedReviewer($actorId, $employee, $finalAssignmentId);
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

    /**
     * Reverse an approved AL reservation only after an immutable General Manager confirmation exists.
     * CT-DEC-016: restore original single-cohort Tabung usage and enforce the
     * confirmed full-year cap. Ambiguous multi-cohort disposal remains gated.
     *
     * @return array{reservation_id:int,status:string,leave_year:int,annual_units:int,tabung_units:int}
     */
    public function cancelApprovedAnnualLeave(int $actorId, int $reservationId, int $cancellationActionId, int $assignmentId, string $operationKey): array
    {
        $this->assertLocal();
        $this->validateKeys((string) $reservationId, $operationKey);
        if ($cancellationActionId < 1 || $assignmentId < 1) {
            throw new DomainException('A confirmed cancellation action and current General Manager assignment are required.');
        }

        return DB::transaction(function () use ($actorId, $reservationId, $cancellationActionId, $assignmentId, $operationKey): array {
            foreach (['leave_calendar_confirmation_guard', 'leave_working_schedule_guard', 'office_assignment_guard'] as $guard) {
                if (DB::table($guard)->where('id', 1)->lockForUpdate()->first() === null) {
                    throw new RuntimeException('Leave calendar, schedule, and assignment guards must be available for cancellation.');
                }
            }
            $candidate = DB::table('leave_reservations')->where('id', $reservationId)->first();
            if ($candidate === null) {
                throw new DomainException('The reservation does not exist.');
            }
            $employee = DB::table('employees')->where('id', $candidate->employee_id)->lockForUpdate()->first();
            if ($employee === null) {
                throw new DomainException('A consistent employee reservation is required.');
            }
            $lockedAccounts = $this->lockReservationAccounts($candidate, $employee);
            $annual = $lockedAccounts['annual_accounts']->get((int) $candidate->annual_account_id);
            $tabung = $lockedAccounts['tabung'];
            $reservation = DB::table('leave_reservations')->where('id', $reservationId)->lockForUpdate()->first();
            $action = DB::table('leave_request_cancellation_actions')->where('id', $cancellationActionId)->lockForUpdate()->first();
            $requestRevision = DB::table('leave_request_revisions as revision')
                ->join('leave_requests as request', 'request.id', '=', 'revision.request_id')
                ->where('revision.reservation_id', $reservationId)
                ->where('revision.id', $action?->revision_id)
                ->lockForUpdate()
                ->first([
                    'request.id as request_id', 'request.leave_type', 'request.status', 'request.current_revision',
                    'request.requester_user_id', 'revision.revision',
                ]);
            if ($reservation === null
                || (int) $annual->leave_year !== (int) $reservation->leave_year
                || $action === null || $action->action_code !== 'CONFIRMED' || (int) $action->actor_id !== $actorId
                || (int) $action->assignment_id !== $assignmentId || $requestRevision === null
                || $requestRevision->leave_type !== 'AL' || $requestRevision->status !== 'WAITING_CANCELLATION'
                || (int) $requestRevision->current_revision !== (int) $requestRevision->revision) {
                throw new DomainException('A current confirmed Annual Leave cancellation is required for this reservation.');
            }
            $allocations = Schema::hasTable('leave_reservation_slot_allocations')
                ? DB::table('leave_reservation_slot_allocations')->where('reservation_id', $reservationId)->orderBy('leave_year')->orderBy('leave_date')->orderBy('session_code')->lockForUpdate()->get()
                : collect();
            $annualUnitsByAccount = $this->assertSlotAllocationConsistency($reservation, $employee, $tabung, $lockedAccounts['annual_accounts'], $allocations);
            $this->assertGeneralManagerAssignment($actorId, $assignmentId, $employee);
            $latestCycle = (int) DB::table('leave_request_cancellation_actions')->where('request_id', $requestRevision->request_id)->max('cycle');
            $hasRequestAction = DB::table('leave_request_cancellation_actions')->where('request_id', $requestRevision->request_id)
                ->where('revision_id', $action->revision_id)->where('cycle', $action->cycle)->where('action_code', 'REQUESTED')->exists();
            if ((int) $action->cycle !== $latestCycle || ! $hasRequestAction) {
                throw new DomainException('The General Manager confirmation must match the current employee cancellation request.');
            }

            $payload = [
                'actor_id' => $actorId,
                'assignment_id' => $assignmentId,
                'cancellation_action_id' => $cancellationActionId,
                'reservation_id' => $reservationId,
            ];
            $operation = $this->startOperation($operationKey, 'CANCEL_APPROVED_AL', $payload, (int) $employee->id, $actorId);
            if ($operation['replay']) {
                return $this->reservationResult((int) $operation['event']->reservation_id);
            }
            if ($reservation->status !== 'COMMITTED') {
                throw new DomainException('Only a committed approved Annual Leave reservation can be cancelled.');
            }
            $tabungRefundUnits = (int) $reservation->tabung_units;
            if ((int) $reservation->annual_units + $tabungRefundUnits < 1) {
                throw new DomainException('This cancellation has no source units to restore.');
            }
            $tabungExcess = 0;
            if ($tabungRefundUnits > 0) {
                $currentYear = (int) now('Asia/Kuala_Lumpur')->format('Y');
                $cap = (new TabungCapacity)->confirmedCapFor((int) $employee->id, $currentYear);
                if ($cap === null || (int) $reservation->leave_year !== $currentYear
                    || $reservation->tabung_source_year === null) {
                    throw new DomainException('Sahkan kadar penuh Tabung tahun semasa; pemulangan tahun lain memerlukan rekonsiliasi.');
                }
                $posted = $this->totals->forAccounts((int) $annual->id, (int) $tabung->id)['tabung_half_day_units'];
                if ($posted < 0 || $posted > $cap || $tabungRefundUnits > PHP_INT_MAX - $posted) {
                    throw new DomainException('Baki Tabung sedia ada memerlukan semakan sebelum pemulangan.');
                }
                $tabungExcess = max(0, $posted + $tabungRefundUnits - $cap);
                if ($tabungExcess > 0) {
                    // Do not choose a disposal priority between multiple sources.
                    // If the original refund cohort is the only positive cohort
                    // after restoration, the disposal source is unambiguous.
                    $cohorts = DB::table('leave_ledger_entries')->where('tabung_account_id', $tabung->id)
                        ->select('source_year')->selectRaw('SUM(signed_half_day_units) AS units')->groupBy('source_year')->get();
                    foreach ($cohorts as $cohort) {
                        $units = $this->integerTotal((string) $cohort->units);
                        if ($units < 0 || ($units > 0 && ($cohort->source_year === null
                            || (int) $cohort->source_year !== (int) $reservation->tabung_source_year))) {
                            throw new DomainException('Pelupusan lebihan beberapa tahun sumber memerlukan kaedah yang disahkan HR.');
                        }
                    }
                }
            }

            $committedEvent = DB::table('leave_reservation_events')->where('reservation_id', $reservationId)
                ->where('event_code', 'COMMITTED')->lockForUpdate()->first();
            if ($committedEvent === null) {
                throw new RuntimeException('The approved reservation has no committed balance event.');
            }
            $commitment = DB::table('leave_annual_commitments')->where('reservation_id', $reservationId)->lockForUpdate()->first();
            $postedUnitsByAccount = $annualUnitsByAccount;
            if ($commitment !== null && $commitment->status === 'COMMITTED') {
                $accountId = (int) $commitment->annual_account_id;
                if (! isset($postedUnitsByAccount[$accountId]) || $postedUnitsByAccount[$accountId] !== (int) $commitment->units
                    || (int) $commitment->approval_operation_id !== (int) $committedEvent->balance_operation_id
                    || DB::table('leave_ledger_entries')->where('annual_account_id', $accountId)->where('kind', 'annual_entitlement_credit')->exists()) {
                    throw new DomainException('The unposted commitment is inconsistent.');
                }
                DB::table('leave_annual_commitments')->where('id', $commitment->id)->update([
                    'status' => 'CANCELLED', 'cancel_operation_id' => $operation['id'], 'resolved_at' => now(),
                ]);
                unset($postedUnitsByAccount[$accountId]);
            }
            $usageEntries = DB::table('leave_ledger_entries')->where('balance_operation_id', $committedEvent->balance_operation_id)
                ->where('kind', 'approved_leave_use')->lockForUpdate()->get();
            $annualUsage = $usageEntries->whereNotNull('annual_account_id');
            $tabungUsage = $usageEntries->whereNotNull('tabung_account_id');
            $usageEntriesByAnnualAccount = $annualUsage->keyBy('annual_account_id');
            if ($usageEntries->count() !== count($postedUnitsByAccount) + ($tabungRefundUnits > 0 ? 1 : 0)
                || $annualUsage->count() !== count($postedUnitsByAccount)
                || $usageEntriesByAnnualAccount->count() !== count($postedUnitsByAccount)
                || $tabungUsage->count() !== ($tabungRefundUnits > 0 ? 1 : 0)) {
                throw new DomainException('The original annual usage entries do not match the per-date source allocation.');
            }
            foreach ($postedUnitsByAccount as $accountId => $units) {
                $usage = $usageEntriesByAnnualAccount->get($accountId);
                if ($usage === null || (int) $usage->annual_account_id !== (int) $accountId
                    || $usage->tabung_account_id !== null || (int) $usage->signed_half_day_units !== -$units
                    || $usage->source_year !== null || $usage->reverses_entry_id !== null
                    || DB::table('leave_ledger_entries')->where('reverses_entry_id', $usage->id)->exists()) {
                    throw new DomainException('An original Annual Leave usage entry is inconsistent or already reversed.');
                }
                $accountYear = (int) $lockedAccounts['annual_accounts']->get($accountId)->leave_year;
                DB::table('leave_ledger_entries')->insert([
                    'annual_account_id' => $accountId, 'balance_operation_id' => $operation['id'],
                    'signed_half_day_units' => $units, 'kind' => 'approved_leave_cancellation', 'source_year' => null,
                    'operation_key' => $allocations->isEmpty() ? $operationKey.':annual' : $operationKey.':annual:'.$accountYear,
                    'reverses_entry_id' => $usage->id, 'actor_id' => $actorId,
                ]);
            }

            if ($tabungRefundUnits > 0) {
                $usage = $tabungUsage->first();
                if ($usage->annual_account_id !== null || (int) $usage->tabung_account_id !== (int) $tabung->id
                    || (int) $usage->signed_half_day_units !== -$tabungRefundUnits
                    || (int) $usage->source_year !== (int) $reservation->tabung_source_year
                    || $usage->reverses_entry_id !== null
                    || DB::table('leave_ledger_entries')->where('reverses_entry_id', $usage->id)->exists()) {
                    throw new DomainException('An original Tabung usage entry is inconsistent or already reversed.');
                }
                DB::table('leave_ledger_entries')->insert([
                    'tabung_account_id' => $tabung->id, 'balance_operation_id' => $operation['id'],
                    'signed_half_day_units' => $tabungRefundUnits, 'kind' => 'approved_leave_cancellation',
                    'source_year' => $reservation->tabung_source_year, 'operation_key' => $operationKey.':tabung',
                    'reverses_entry_id' => $usage->id, 'actor_id' => $actorId,
                ]);
                if ($tabungExcess > 0) {
                    DB::table('leave_ledger_entries')->insert([
                        'tabung_account_id' => $tabung->id, 'balance_operation_id' => $operation['id'],
                        'signed_half_day_units' => -$tabungExcess, 'kind' => 'tabung_cancellation_excess_disposal',
                        'source_year' => $reservation->tabung_source_year, 'operation_key' => $operationKey.':excess',
                        'actor_id' => $actorId,
                    ]);
                    $this->audit('hr.leave.tabung_excess_disposed', $reservationId, $operationKey);
                }
            }

            $updated = DB::table('leave_reservations')->where('id', $reservationId)->where('status', 'COMMITTED')
                ->update(['status' => 'CANCELLED', 'resolved_at' => now()]);
            if ($updated !== 1) {
                throw new RuntimeException('The committed reservation did not transition to cancelled exactly once.');
            }
            $slotUpdates = DB::table('leave_active_slots')->where('reservation_id', $reservationId)->where('status', 'COMMITTED')
                ->update(['status' => 'RELEASED', 'active_slot_key' => null, 'resolved_at' => now()]);
            if ($slotUpdates !== (int) $reservation->requested_units) {
                throw new RuntimeException('The committed slot claims did not release atomically.');
            }
            $this->recordEvent($reservationId, $operation['id'], 'CANCELLED', $actorId);
            $this->audit('hr.leave.balance_cancelled', $reservationId, $operationKey);

            return $this->reservationResult($reservationId);
        }, 3);
    }

    /**
     * @return array{reservation_id:int,status:string,leave_year:int,annual_units:int,tabung_units:int,refunded_units:int}
     */
    public function refundPartiallyCancelledAnnualLeave(int $actorId, int $reservationId, int $partialCancellationId, int $assignmentId, string $operationKey): array
    {
        $this->assertLocal();
        $this->validateKeys((string) $reservationId, $operationKey);
        if ($partialCancellationId < 1 || $assignmentId < 1) {
            throw new DomainException('A verified partial cancellation and current General Manager assignment are required.');
        }

        return DB::transaction(function () use ($actorId, $reservationId, $partialCancellationId, $assignmentId, $operationKey): array {
            foreach (['leave_calendar_confirmation_guard', 'leave_working_schedule_guard', 'office_assignment_guard'] as $guard) {
                if (DB::table($guard)->where('id', 1)->lockForUpdate()->first() === null) {
                    throw new RuntimeException('Leave calendar, schedule, and assignment guards must be available for partial cancellation.');
                }
            }
            $candidate = DB::table('leave_reservations')->where('id', $reservationId)->first();
            if ($candidate === null) {
                throw new DomainException('The reservation does not exist.');
            }
            $employee = DB::table('employees')->where('id', $candidate->employee_id)->lockForUpdate()->first();
            if ($employee === null) {
                throw new DomainException('A consistent employee reservation is required.');
            }
            $lockedAccounts = $this->lockReservationAccounts($candidate, $employee);
            $annual = $lockedAccounts['annual_accounts']->get((int) $candidate->annual_account_id);
            $tabung = $lockedAccounts['tabung'];
            $reservation = DB::table('leave_reservations')->where('id', $reservationId)->lockForUpdate()->first();
            $partialCancellation = DB::table('leave_request_partial_cancellations')->where('id', $partialCancellationId)->lockForUpdate()->first();
            $requestRevision = DB::table('leave_request_revisions as revision')
                ->join('leave_requests as request', 'request.id', '=', 'revision.request_id')
                ->where('revision.reservation_id', $reservationId)
                ->where('revision.id', $partialCancellation?->revision_id)
                ->lockForUpdate()
                ->first([
                    'request.id as request_id', 'request.leave_type', 'request.status', 'request.current_revision',
                    'request.requester_user_id', 'revision.revision',
                ]);
            if ($reservation === null || (int) $annual->leave_year !== (int) $reservation->leave_year
                || $partialCancellation === null || $partialCancellation->status !== 'CONFIRMED'
                || (int) $partialCancellation->general_manager_user_id !== $actorId
                || (int) $partialCancellation->general_manager_assignment_id !== $assignmentId
                || $requestRevision === null || $requestRevision->leave_type !== 'AL' || $requestRevision->status !== 'APPROVED'
                || (int) $requestRevision->current_revision !== (int) $requestRevision->revision
                || (int) $partialCancellation->requested_by !== (int) $requestRevision->requester_user_id
                || $reservation->status !== 'COMMITTED' || (int) $reservation->tabung_units !== 0
                || (int) $partialCancellation->unused_half_day_units < 1) {
                throw new DomainException('A current, verified, Annual-funded partial cancellation confirmed by the General Manager is required.');
            }
            $allocations = Schema::hasTable('leave_reservation_slot_allocations')
                ? DB::table('leave_reservation_slot_allocations')->where('reservation_id', $reservationId)->orderBy('leave_year')->orderBy('leave_date')->orderBy('session_code')->lockForUpdate()->get()
                : collect();
            $annualUnitsByAccount = $this->assertSlotAllocationConsistency($reservation, $employee, $tabung, $lockedAccounts['annual_accounts'], $allocations);
            $this->assertGeneralManagerAssignment($actorId, $assignmentId, $employee);

            $unusedUnits = (int) $partialCancellation->unused_half_day_units;
            $payload = [
                'actor_id' => $actorId,
                'assignment_id' => $assignmentId,
                'partial_cancellation_id' => $partialCancellationId,
                'reservation_id' => $reservationId,
                'unused_half_day_units' => $unusedUnits,
            ];
            $operation = $this->startOperation($operationKey, 'PARTIAL_CANCEL_AL', $payload, (int) $employee->id, $actorId);
            if ($operation['replay']) {
                return [...$this->reservationResult((int) $operation['event']->reservation_id), 'refunded_units' => $unusedUnits];
            }
            if ((int) $reservation->annual_units !== (int) $reservation->requested_units) {
                throw new DomainException('Partial restoration is deferred until the original source allocation can be matched exactly.');
            }
            $unusedUnitsByAccount = [];
            if ($allocations->isEmpty()) {
                $unusedUnitsByAccount[(int) $reservation->annual_account_id] = $unusedUnits;
            } else {
                foreach ($allocations as $allocation) {
                    if ($allocation->allocation_bucket !== 'ANNUAL') {
                        throw new DomainException('Partial restoration requires a per-date Annual Leave-only source map.');
                    }
                    if ($allocation->leave_date > $partialCancellation->actual_return_date) {
                        $accountId = (int) $allocation->annual_account_id;
                        $unusedUnitsByAccount[$accountId] = ($unusedUnitsByAccount[$accountId] ?? 0) + (int) $allocation->units;
                    }
                }
                if (array_sum($unusedUnitsByAccount) !== $unusedUnits) {
                    throw new DomainException('The confirmed unused sessions do not match the per-date Annual Leave source map.');
                }
            }

            $committedEvent = DB::table('leave_reservation_events')->where('reservation_id', $reservationId)
                ->where('event_code', 'COMMITTED')->lockForUpdate()->first();
            if ($committedEvent === null) {
                throw new RuntimeException('The approved reservation has no committed balance event.');
            }
            $usageEntries = DB::table('leave_ledger_entries')->where('balance_operation_id', $committedEvent->balance_operation_id)
                ->where('kind', 'approved_leave_use')->lockForUpdate()->get();
            $usageEntriesByAnnualAccount = $usageEntries->keyBy('annual_account_id');
            if ($usageEntries->count() !== count($annualUnitsByAccount) || $usageEntriesByAnnualAccount->count() !== count($annualUnitsByAccount)) {
                throw new DomainException('The original annual usage entries do not match the per-date source allocation.');
            }
            foreach ($annualUnitsByAccount as $accountId => $originalUnits) {
                $usage = $usageEntriesByAnnualAccount->get($accountId);
                if ($usage === null || $usage->tabung_account_id !== null || (int) $usage->signed_half_day_units !== -$originalUnits
                    || $usage->source_year !== null || $usage->reverses_entry_id !== null) {
                    throw new DomainException('An original Annual Leave usage entry does not match its source allocation.');
                }
                $refundUnits = $unusedUnitsByAccount[$accountId] ?? 0;
                if ($refundUnits < 1) {
                    continue;
                }
                $alreadyReversed = $this->integerTotal((string) DB::table('leave_ledger_entries')->where('reverses_entry_id', $usage->id)
                    ->selectRaw('CAST(COALESCE(SUM(signed_half_day_units), 0) AS CHAR) AS units')->value('units'));
                if ($alreadyReversed < 0 || $alreadyReversed + $refundUnits > abs((int) $usage->signed_half_day_units)) {
                    throw new DomainException('The partial refund would exceed the original Annual Leave usage.');
                }
                $accountYear = (int) $lockedAccounts['annual_accounts']->get($accountId)->leave_year;
                DB::table('leave_ledger_entries')->insert([
                    'annual_account_id' => $accountId,
                    'balance_operation_id' => $operation['id'],
                    'signed_half_day_units' => $refundUnits,
                    'kind' => 'approved_leave_partial_cancellation',
                    'source_year' => null,
                    'operation_key' => $allocations->isEmpty() ? $operationKey.':annual' : $operationKey.':annual:'.$accountYear,
                    'reverses_entry_id' => $usage->id,
                    'actor_id' => $actorId,
                ]);
            }
            $releasedSlots = DB::table('leave_active_slots')->where('reservation_id', $reservationId)->where('status', 'COMMITTED')
                ->where('leave_date', '>', $partialCancellation->actual_return_date)
                ->update(['status' => 'RELEASED', 'active_slot_key' => null, 'resolved_at' => now()]);
            if ($releasedSlots !== $unusedUnits) {
                throw new RuntimeException('The confirmed unused sessions and released slot claims do not match.');
            }
            $this->recordEvent($reservationId, $operation['id'], 'PARTIAL_REFUND', $actorId);
            $this->audit('hr.leave.partial_cancellation_refunded', $reservationId, $operationKey);

            return [...$this->reservationResult($reservationId), 'refunded_units' => $unusedUnits];
        }, 3);
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

    private function appendUsageEntry(stdClass $reservation, int $operationId, int $actorId, string $operationKey, string $accountColumn, string $unitsColumn, ?int $sourceYear, string $kind = 'approved_leave_use'): void
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
            'kind' => $kind,
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

    private function assertGeneralManagerAssignment(int $actorId, int $assignmentId, stdClass $employee): void
    {
        $this->assertActiveActor($actorId);
        if ((int) $employee->user_id === $actorId) {
            throw new DomainException('A General Manager cannot confirm cancellation of their own leave request.');
        }
        $today = now('Asia/Kuala_Lumpur')->toDateString();
        $activeGeneralManagers = DB::table('approval_assignments')->where('office_code', 'PB')
            ->where('effective_from', '<=', $today)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
            ->lockForUpdate()->get(['id', 'user_id']);
        $assignment = $activeGeneralManagers->firstWhere('id', $assignmentId);
        if ($activeGeneralManagers->count() !== 1 || $assignment === null || (int) $assignment->user_id !== $actorId) {
            throw new DomainException('Exactly one current General Manager assignment is required to confirm cancellation.');
        }
    }

    private function assertAssignedReviewer(int $actorId, stdClass $employee, ?int $assignmentId): void
    {
        $this->assertActiveActor($actorId);
        if ($assignmentId === null || (int) $employee->user_id === $actorId) {
            throw new DomainException('A distinct, assigned reviewer is required to return leave for correction.');
        }
        $today = now('Asia/Kuala_Lumpur')->toDateString();
        $assignment = DB::table('approval_assignments')->where('id', $assignmentId)->where('user_id', $actorId)
            ->whereIn('office_code', ['PU', 'PB', 'SECRETARY_ALK'])->where('effective_from', '<=', $today)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
            ->lockForUpdate()->first();
        if ($assignment === null) {
            throw new DomainException('The current assigned reviewer is required to return leave for correction.');
        }
    }

    private function assertActiveActor(int $actorId): void
    {
        $actor = DB::table('users')->where('id', $actorId)->first(['status', 'username']);
        if ($actor === null || $actor->status !== 'ACTIVE' || $actor->username === null) {
            throw new DomainException('An active account is required for leave balance operations.');
        }
    }

    private function assertAuthorizedHrRecorder(int $actorId): void
    {
        if (! DB::table('permissions')->where('name', 'leave.request.record-on-behalf')->where('guard_name', 'web')->exists()) {
            throw new DomainException('The local-only HR-assisted submission permission is not configured.');
        }
        $lockedActor = DB::table('users')->where('id', $actorId)->lockForUpdate()->first(['status', 'username']);
        $actor = User::query()->find($actorId);
        if ($lockedActor === null || $lockedActor->status !== 'ACTIVE' || $lockedActor->username === null
            || $actor === null || $actor->status !== 'ACTIVE' || $actor->username === null
            || ! $actor->hasPermissionTo('leave.request.record-on-behalf', 'web')) {
            throw new DomainException('An active account with explicit HR-assisted submission authority is required.');
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

    /**
     * @return array{annual_accounts:Collection<int, stdClass>,tabung:stdClass}
     */
    private function lockReservationAccounts(stdClass $candidate, stdClass $employee): array
    {
        $annualAccountIds = [(int) $candidate->annual_account_id];
        if (Schema::hasTable('leave_reservation_slot_allocations')) {
            $annualAccountIds = array_merge($annualAccountIds, DB::table('leave_reservation_slot_allocations')
                ->where('reservation_id', $candidate->id)->where('allocation_bucket', 'ANNUAL')
                ->pluck('annual_account_id')->map(fn (mixed $id): int => (int) $id)->all());
        }
        $annualAccountIds = array_values(array_unique($annualAccountIds));
        sort($annualAccountIds, SORT_NUMERIC);
        $annualAccounts = DB::table('leave_annual_accounts')->whereIn('id', $annualAccountIds)
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($annualAccounts->count() !== count($annualAccountIds)) {
            throw new DomainException('A reservation references a missing Annual Leave account.');
        }
        foreach ($annualAccounts as $account) {
            if ((int) $account->employee_id !== (int) $employee->id) {
                throw new DomainException('All reservation Annual Leave accounts must belong to the same employee.');
            }
        }
        $tabung = DB::table('leave_tabung_accounts')->where('id', $candidate->tabung_account_id)->lockForUpdate()->first();
        if ($tabung === null || (int) $tabung->employee_id !== (int) $employee->id) {
            throw new DomainException('The reservation Tabung account must belong to the same employee.');
        }

        return ['annual_accounts' => $annualAccounts, 'tabung' => $tabung];
    }

    /**
     * @param  Collection<int, stdClass>  $annualAccounts
     * @param  Collection<int, stdClass>  $allocations
     * @return array<int, int>
     */
    private function assertSlotAllocationConsistency(stdClass $reservation, stdClass $employee, stdClass $tabung, Collection $annualAccounts, Collection $allocations): array
    {
        if ($allocations->isEmpty()) {
            return (int) $reservation->annual_units > 0 ? [(int) $reservation->annual_account_id => (int) $reservation->annual_units] : [];
        }
        if ($allocations->count() !== (int) $reservation->requested_units) {
            throw new DomainException('The per-date source map must cover every reserved slot exactly once.');
        }

        $annualUnitsByAccount = [];
        $annualUnits = 0;
        $tabungUnits = 0;
        $tabungSourceYears = [];
        foreach ($allocations as $allocation) {
            if ((int) $allocation->units !== 1 || (int) $allocation->leave_year !== (int) substr($allocation->leave_date, 0, 4)
                || ! in_array($allocation->session_code, ['AM', 'PM'], true)) {
                throw new DomainException('A per-date source allocation has an invalid date, session or unit count.');
            }
            if ($allocation->allocation_bucket === 'ANNUAL') {
                $account = $annualAccounts->get((int) $allocation->annual_account_id);
                if ($account === null || (int) $account->employee_id !== (int) $employee->id
                    || (int) $account->leave_year !== (int) $allocation->leave_year
                    || $allocation->tabung_account_id !== null || $allocation->source_year !== null) {
                    throw new DomainException('An Annual Leave source allocation does not match its employee and calendar year.');
                }
                $annualUnitsByAccount[(int) $account->id] = ($annualUnitsByAccount[(int) $account->id] ?? 0) + 1;
                $annualUnits++;
            } elseif ($allocation->allocation_bucket === 'TABUNG') {
                if ($allocation->annual_account_id !== null || (int) $allocation->tabung_account_id !== (int) $tabung->id
                    || (int) $tabung->employee_id !== (int) $employee->id || $allocation->source_year === null) {
                    throw new DomainException('A Tabung source allocation does not match its account and provenance.');
                }
                $tabungSourceYears[(int) $allocation->source_year] = true;
                $tabungUnits++;
            } else {
                throw new DomainException('A per-date source allocation has an unsupported bucket.');
            }
        }

        if ($annualUnits !== (int) $reservation->annual_units || $tabungUnits !== (int) $reservation->tabung_units
            || count($tabungSourceYears) > 1
            || ($tabungUnits === 0 && $reservation->tabung_source_year !== null)
            || ($tabungUnits > 0 && (count($tabungSourceYears) !== 1 || (int) array_key_first($tabungSourceYears) !== (int) $reservation->tabung_source_year))) {
            throw new DomainException('The per-date source map does not match the reservation balance totals and Tabung source year.');
        }

        return $annualUnitsByAccount;
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
        $query = DB::table('leave_reservations')->where($accountColumn, $accountId)->where('status', 'ACTIVE');
        if ($sumColumn === 'annual_units' && Schema::hasTable('leave_reservation_slot_allocations')) {
            $query->whereNotExists(function ($subquery): void {
                $subquery->selectRaw('1')->from('leave_reservation_slot_allocations as allocations')
                    ->whereColumn('allocations.reservation_id', 'leave_reservations.id');
            });
        }
        $legacyTotal = $this->integerTotal($query->selectRaw($expression)->value('units'));
        if ($sumColumn !== 'annual_units' || ! Schema::hasTable('leave_reservation_slot_allocations')) {
            return $legacyTotal;
        }

        $allocatedTotal = $this->integerTotal(DB::table('leave_reservation_slot_allocations as allocations')
            ->join('leave_reservations as reservations', 'reservations.id', '=', 'allocations.reservation_id')
            ->where('allocations.allocation_bucket', 'ANNUAL')->where('allocations.annual_account_id', $accountId)
            ->where('reservations.status', 'ACTIVE')->selectRaw('CAST(COALESCE(SUM(allocations.units), 0) AS CHAR) AS units')->value('units'));
        if ($legacyTotal > PHP_INT_MAX - $allocatedTotal) {
            throw new OverflowException('The active Annual Leave reservation total exceeds the supported integer range.');
        }

        return $legacyTotal + $allocatedTotal;
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
