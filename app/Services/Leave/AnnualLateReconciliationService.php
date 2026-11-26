<?php

namespace App\Services\Leave;

use App\Models\AuditEvent;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Internal CT-DEC-039 primitive. Caller must hold employee/account locks and prove newly eligible units in the same transaction. */
class AnnualLateReconciliationService
{
    public function __construct(private readonly LedgerPostedTotals $totals, private readonly TabungCapacity $capacity) {}

    public function reconcile(int $actor, int $employee, int $annualId, int $tabungId, int $units, int $operation,
        string $key, string $sourceKind, int $sourceId): void
    {
        if (! app()->environment(['local', 'testing']) || DB::transactionLevel() < 1 || $units < 1
            || ! in_array($sourceKind, ['RELEASE', 'ATTENDANCE', 'HR_ADJUSTMENT'], true)) {
            throw new DomainException('Rekonsiliasi memerlukan transaksi sumber yang sah.');
        }
        $annual = DB::table('leave_annual_accounts')->where('id', $annualId)->first();
        $tabung = DB::table('leave_tabung_accounts')->where('id', $tabungId)->first();
        $op = DB::table('leave_balance_operations')->where('id', $operation)->first();
        $close = DB::table('leave_annual_year_closes')->where('annual_account_id', $annualId)->first();
        $current = (int) now('Asia/Kuala_Lumpur')->format('Y');
        if ($annual === null || $tabung === null || $close === null || $op === null
            || (int) $annual->employee_id !== $employee || (int) $tabung->employee_id !== $employee
            || (int) $close->employee_id !== $employee || (int) $close->tabung_account_id !== $tabungId
            || (int) $op->employee_id !== $employee || (int) $op->actor_id !== $actor || $op->operation_key !== $key
            || (int) $annual->leave_year >= $current) {
            throw new DomainException('Akaun tahun tertutup dan operasi sumber yang sepadan diperlukan.');
        }
        $year = (int) $annual->leave_year;
        $rate = $this->capacity->confirmedRateFor($employee, $current);
        $posted = $this->totals->forAccounts($annualId, $tabungId);
        $pending = $this->totals->pendingAnnualUnits($annualId);
        $before = $posted['tabung_half_day_units'];
        if ($rate === null || $posted['annual_half_day_units'] - $pending < $units || $before < 0
            || $before > (int) $rate->annual_rate_units) {
            throw new DomainException('Baki bebas tahun lama, tempahan atau kadar penuh Tabung semasa memerlukan semakan HR.');
        }
        if (DB::table('leave_late_reconciliations')->where('source_kind', $sourceKind)->where('source_id', $sourceId)
            ->where('annual_account_id', $annualId)->exists()) {
            throw new DomainException('Sumber ini telah direkonsiliasi.');
        }
        $cap = (int) $rate->annual_rate_units;
        $transfer = min($units, $cap - $before);
        $burn = $units - $transfer;
        $entry = function (string $account, int $id, int $signed, string $kind, ?int $sourceYear, string $suffix) use ($operation, $actor, $key, $year): void {
            DB::table('leave_ledger_entries')->insert([
                $account => $id, 'balance_operation_id' => $operation, 'signed_half_day_units' => $signed,
                'kind' => $kind, 'source_year' => $sourceYear, 'operation_key' => $key.':'.$suffix.':'.$year, 'actor_id' => $actor,
            ]);
        };
        if ($transfer > 0) {
            $entry('annual_account_id', $annualId, -$transfer, 'annual_reconcile_transfer_out', null, 'ro');
            $entry('tabung_account_id', $tabungId, $transfer, 'annual_reconcile_transfer_in', $year, 'ri');
        }
        if ($burn > 0) {
            $entry('annual_account_id', $annualId, -$burn, 'annual_reconcile_burn', null, 'rb');
        }
        $id = DB::table('leave_late_reconciliations')->insertGetId([
            'source_kind' => $sourceKind, 'source_id' => $sourceId, 'balance_operation_id' => $operation,
            'annual_account_id' => $annualId, 'tabung_account_id' => $tabungId, 'source_year' => $year,
            'cap_units' => $cap, 'cap_effective_from' => $rate->effective_from,
            'annual_before' => $posted['annual_half_day_units'], 'annual_pending' => $pending,
            'tabung_before' => $before, 'eligible_units' => $units, 'transfer_units' => $transfer,
            'burn_units' => $burn, 'actor_id' => $actor, 'created_at' => now(),
        ]);
        AuditEvent::query()->create([
            'occurred_at' => now(), 'actor_id' => $actor, 'action_code' => 'hr.leave.late_reconciled',
            'target_ref' => 'leave_late_reconciliation:'.$id, 'outcome' => 'SUCCESS',
            'metadata' => ['source' => 'annual_late_reconciliation', 'reason_code' => $sourceKind, 'correlation_id' => $key],
        ]);
    }

    /** Called only after an immutable HR finding explicitly confirms every original session was not used. */
    public function reconcileAttendance(int $reviewId, int $actor, string $key): void
    {
        $review = DB::table('leave_attendance_reviews as review')
            ->join('leave_request_revisions as revision', 'revision.id', '=', 'review.revision_id')
            ->join('leave_requests as request', 'request.id', '=', 'revision.request_id')
            ->join('leave_attendance_review_outcomes as outcome', 'outcome.review_id', '=', 'review.id')
            ->where('review.id', $reviewId)->where('review.review_code', 'LATE_AL_REJECTION')
            ->where('outcome.finding', 'NO_ABSENCE_CONFIRMED')->where('outcome.reviewer_id', $actor)
            ->where('outcome.operation_key', $key)->where('request.status', 'REJECTED')
            ->whereColumn('request.current_revision', 'revision.revision')
            ->first(['revision.reservation_id', 'request.employee_id']);
        if ($review === null) {
            throw new DomainException('Pengesahan HR tentang semua sesi tidak digunakan diperlukan.');
        }
        $employee = DB::table('employees')->where('id', $review->employee_id)->lockForUpdate()->first();
        $claims = DB::table('leave_late_release_claims')->where('reservation_id', $review->reservation_id)
            ->where('source_year', '<', (int) now('Asia/Kuala_Lumpur')->format('Y'))->orderBy('annual_account_id')->lockForUpdate()->get();
        if ($employee === null) {
            throw new DomainException('Rekod pekerja tidak ditemui.');
        }
        if ($claims->isEmpty()) {
            $claims = $this->recoverLegacyAttendanceClaim((int) $review->reservation_id, (int) $employee->id);
        }
        // If not yet closed, the ordinary close will include these newly free units.
        $closed = $claims->filter(fn ($claim): bool => DB::table('leave_annual_year_closes')->where('annual_account_id', $claim->annual_account_id)->exists());
        if ($closed->isEmpty()) {
            return;
        }
        $accounts = DB::table('leave_annual_accounts')->whereIn('id', $closed->pluck('annual_account_id'))->orderBy('id')->lockForUpdate()->get();
        $tabungId = (int) $closed->first()->tabung_account_id;
        $tabung = DB::table('leave_tabung_accounts')->where('id', $tabungId)->lockForUpdate()->first();
        if ($accounts->count() !== $closed->count() || $tabung === null || $closed->contains(fn ($claim): bool => (int) $claim->tabung_account_id !== $tabungId)) {
            throw new DomainException('Pemetaan sumber rekonsiliasi tidak lengkap.');
        }
        $operationKey = 'review-reconcile:'.$key;
        $operation = DB::table('leave_balance_operations')->insertGetId([
            'employee_id' => $employee->id, 'actor_id' => $actor, 'operation_key' => $operationKey,
            'operation_type' => 'LATE_RECONCILE', 'payload_hash' => hash('sha256', json_encode([$reviewId, $actor, $key], JSON_THROW_ON_ERROR)),
            'created_at' => now(),
        ]);
        foreach ($closed as $claim) {
            $this->reconcile($actor, (int) $employee->id, (int) $claim->annual_account_id, $tabungId,
                (int) $claim->units, $operation, $operationKey, 'ATTENDANCE', (int) $claim->id);
        }
    }

    /** Recover only a provable historical annual-only release, within the HR outcome transaction. */
    private function recoverLegacyAttendanceClaim(int $reservationId, int $employeeId): \Illuminate\Support\Collection
    {
        $reservation = DB::table('leave_reservations')->where('id', $reservationId)->lockForUpdate()->first();
        if ($reservation === null || (int) $reservation->employee_id !== $employeeId || $reservation->status !== 'RELEASED') {
            throw new DomainException('Rekod pelepasan asal tidak sah.');
        }
        $current = (int) now('Asia/Kuala_Lumpur')->format('Y');
        if ((int) $reservation->annual_units === 0 || (int) $reservation->leave_year >= $current
            || DB::table('leave_late_release_claims')->where('reservation_id', $reservationId)->exists()) {
            return collect();
        }
        $units = (int) $reservation->annual_units;
        $annual = DB::table('leave_annual_accounts')->where('id', $reservation->annual_account_id)->lockForUpdate()->first();
        $tabung = DB::table('leave_tabung_accounts')->where('id', $reservation->tabung_account_id)->lockForUpdate()->first();
        $slots = DB::table('leave_reservation_slot_allocations')->where('reservation_id', $reservationId)->lockForUpdate()->get();
        $release = DB::table('leave_reservation_events as event')
            ->join('leave_balance_operations as operation', 'operation.id', '=', 'event.balance_operation_id')
            ->where('event.reservation_id', $reservationId)->where('event.event_code', 'RELEASED')
            ->where('operation.operation_type', 'RELEASE')->where('operation.employee_id', $employeeId)
            ->get(['event.balance_operation_id']);
        $reserved = DB::table('leave_reservation_events')->where('reservation_id', $reservationId)
            ->where('event_code', 'RESERVED')->get(['balance_operation_id']);
        if ($annual === null || $tabung === null || (int) $annual->employee_id !== $employeeId
            || (int) $tabung->employee_id !== $employeeId || (int) $annual->leave_year !== (int) $reservation->leave_year
            || (int) $reservation->tabung_units !== 0 || (int) $reservation->requested_units !== $units
            || $units < 1 || $slots->count() !== $units || $release->count() !== 1 || $reserved->count() !== 1
            || $slots->contains(fn ($slot): bool => $slot->allocation_bucket !== 'ANNUAL'
                || (int) $slot->annual_account_id !== (int) $annual->id || (int) $slot->leave_year !== (int) $annual->leave_year
                || (int) substr($slot->leave_date, 0, 4) !== (int) $annual->leave_year
                || ! in_array($slot->session_code, ['AM', 'PM'], true) || (int) $slot->units !== 1
                || $slot->tabung_account_id !== null || $slot->source_year !== null)) {
            throw new DomainException('Sumber sesi legacy tidak dapat dibuktikan sepenuhnya; rujuk HR untuk rekonsiliasi manual.');
        }
        $releaseId = (int) $release->first()->balance_operation_id;
        $reserveId = (int) $reserved->first()->balance_operation_id;
        if ($releaseId <= $reserveId || DB::table('leave_ledger_entries')->where('balance_operation_id', $releaseId)->exists()
            || DB::table('leave_late_reconciliations')->where('source_kind', 'RELEASE')->where('source_id', $reservationId)->exists()) {
            throw new DomainException('Pelepasan legacy mungkin telah diproses; tiada pindahan automatik.');
        }
        $close = DB::table('leave_annual_year_closes')->where('annual_account_id', $annual->id)->first();
        $posted = $this->totals->forAccounts((int) $annual->id, (int) $tabung->id);
        $pending = $this->totals->pendingAnnualUnits((int) $annual->id);
        $free = $posted['annual_half_day_units'] - $pending;
        if ($close !== null) {
            // Prove held at close, released afterwards, and no intervening annual ledger movement.
            $closeOp = (int) $close->balance_operation_id;
            if ($reserveId >= $closeOp || $releaseId <= $closeOp
                || (int) $close->employee_id !== $employeeId || (int) $close->tabung_account_id !== (int) $tabung->id
                || (int) $close->annual_before - (int) $close->transfer_units - (int) $close->burn_units !== (int) $close->annual_pending
                || $units > (int) $close->annual_pending || $free !== $units
                || $posted['annual_half_day_units'] !== (int) $close->annual_pending
                || DB::table('leave_ledger_entries')->where('annual_account_id', $annual->id)->where('balance_operation_id', '>', $closeOp)->exists()) {
                throw new DomainException('Sejarah tutup tahun legacy tidak membuktikan baki baru yang tepat; tiada pindahan automatik.');
            }
        } elseif ($free < $units) {
            throw new DomainException('Baki bebas legacy tidak mencukupi untuk rekonsiliasi.');
        }
        $id = DB::table('leave_late_release_claims')->insertGetId([
            'reservation_id' => $reservationId, 'release_operation_id' => $releaseId,
            'annual_account_id' => $annual->id, 'tabung_account_id' => $tabung->id,
            'source_year' => $annual->leave_year, 'units' => $units, 'created_at' => now(),
        ]);
        return DB::table('leave_late_release_claims')->where('id', $id)->get();
    }
}
