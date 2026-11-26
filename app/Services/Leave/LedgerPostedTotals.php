<?php

namespace App\Services\Leave;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use OverflowException;

/** Reads posted totals only; callers must authorize, and future writers must use the same account-lock order. */
class LedgerPostedTotals
{
    /** @return array{annual_half_day_units:int,tabung_half_day_units:int} */
    public function forAccounts(int $annualAccountId, int $tabungAccountId): array
    {
        if ($annualAccountId < 1 || $tabungAccountId < 1) {
            throw new InvalidArgumentException('Explicit Annual and Tabung account IDs are required.');
        }

        return DB::transaction(function () use ($annualAccountId, $tabungAccountId): array {
            $annual = DB::table('leave_annual_accounts')->where('id', $annualAccountId)->lockForUpdate()->first(['employee_id']);
            $tabung = DB::table('leave_tabung_accounts')->where('id', $tabungAccountId)->lockForUpdate()->first(['employee_id']);

            if ($annual === null || $tabung === null || $annual->employee_id !== $tabung->employee_id) {
                throw new InvalidArgumentException('The requested accounts must exist and belong to the same employee.');
            }

            return [
                'annual_half_day_units' => $this->totalFor('annual_account_id', $annualAccountId),
                'tabung_half_day_units' => $this->totalFor('tabung_account_id', $tabungAccountId),
            ];
        });
    }

    /** Active holds include each per-date annual account, not only the reservation's first year. */
    public function pendingAnnualUnits(int $annualAccountId): int
    {
        $legacy = (int) DB::table('leave_reservations as reservation')->where('reservation.annual_account_id', $annualAccountId)
            ->where('reservation.status', 'ACTIVE')->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('leave_reservation_slot_allocations as allocation')->whereColumn('allocation.reservation_id', 'reservation.id'))
            ->sum('reservation.annual_units');
        $allocated = (int) DB::table('leave_reservation_slot_allocations as allocation')
            ->join('leave_reservations as reservation', 'reservation.id', '=', 'allocation.reservation_id')
            ->where('allocation.annual_account_id', $annualAccountId)->where('allocation.allocation_bucket', 'ANNUAL')
            ->where('reservation.status', 'ACTIVE')->sum('allocation.units');

        return $legacy + $allocated + $this->pendingHistoricalReviewUnits($annualAccountId);
    }

    /** Late rejected AL from a historical year stays protected until HR confirms actual non-use. */
    public function pendingHistoricalReviewUnits(int $annualAccountId): int
    {
        $recorded = (int) DB::table('leave_late_release_claims as claim')
            ->where('claim.annual_account_id', $annualAccountId)
            ->where('claim.source_year', '<', (int) now('Asia/Kuala_Lumpur')->format('Y'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('leave_request_revisions as revision')
                ->join('leave_attendance_reviews as review', 'review.revision_id', '=', 'revision.id')
                ->join('leave_attendance_review_outcomes as outcome', 'outcome.review_id', '=', 'review.id')
                ->whereColumn('revision.reservation_id', 'claim.reservation_id')
                ->where('review.review_code', 'LATE_AL_REJECTION')->where('outcome.finding', 'NO_ABSENCE_CONFIRMED'))
            ->sum('claim.units');

        // Partial outcomes release only individually verified original sessions.
        $partial = (int) DB::table('leave_attendance_non_use_slots as slot')
            ->join('leave_late_release_claims as claim', 'claim.id', '=', 'slot.claim_id')
            ->join('leave_attendance_review_outcomes as outcome', 'outcome.id', '=', 'slot.outcome_id')
            ->where('claim.annual_account_id', $annualAccountId)
            ->where('claim.source_year', '<', (int) now('Asia/Kuala_Lumpur')->format('Y'))
            ->where('outcome.finding', 'PARTIAL_NON_USE_CONFIRMED')->count();
        $recorded -= $partial;
        if ($recorded < 0) {
            throw new \DomainException('Invalid historical attendance hold.');
        }

        // Protect unresolved legacy historical releases even without a 000057 claim.
        $account = DB::table('leave_annual_accounts')->where('id', $annualAccountId)->first(['leave_year']);
        if ($account === null || (int) $account->leave_year >= (int) now('Asia/Kuala_Lumpur')->format('Y')) {
            return $recorded;
        }
        $legacy = DB::table('leave_reservations as reservation')
            ->join('leave_request_revisions as revision', 'revision.reservation_id', '=', 'reservation.id')
            ->join('leave_requests as request', 'request.id', '=', 'revision.request_id')
            ->join('leave_attendance_reviews as review', 'review.revision_id', '=', 'revision.id')
            ->where('request.leave_type', 'AL')->where('request.status', 'REJECTED')
            ->whereColumn('request.current_revision', 'revision.revision')
            ->where('reservation.status', 'RELEASED')->where('review.review_code', 'LATE_AL_REJECTION')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('leave_late_release_claims as claim')
                ->whereColumn('claim.reservation_id', 'reservation.id'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('leave_attendance_review_outcomes as outcome')
                ->whereColumn('outcome.review_id', 'review.id')->where('outcome.finding', 'NO_ABSENCE_CONFIRMED'));
        $header = (int) (clone $legacy)->where('reservation.annual_account_id', $annualAccountId)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('leave_reservation_slot_allocations as allocation')
                ->whereColumn('allocation.reservation_id', 'reservation.id'))->sum('reservation.annual_units');
        $mapped = (int) (clone $legacy)->join('leave_reservation_slot_allocations as allocation', 'allocation.reservation_id', '=', 'reservation.id')
            ->where('allocation.annual_account_id', $annualAccountId)->where('allocation.allocation_bucket', 'ANNUAL')->sum('allocation.units');

        return $recorded + $header + $mapped;
    }

    /** Pending amounts by actual source; legacy header used only without per-slot allocations. */
    public function pendingTabungBySource(int $tabungAccountId): array
    {
        $legacy = DB::table('leave_reservations as reservation')
            ->where('reservation.tabung_account_id', $tabungAccountId)->where('reservation.status', 'ACTIVE')
            ->where('reservation.tabung_units', '>', 0)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('leave_reservation_slot_allocations as allocation')
                ->whereColumn('allocation.reservation_id', 'reservation.id'))
            ->selectRaw('reservation.tabung_source_year AS source_year, SUM(reservation.tabung_units) AS units')
            ->groupBy('reservation.tabung_source_year')->get();
        $allocated = DB::table('leave_reservation_slot_allocations as allocation')
            ->join('leave_reservations as reservation', 'reservation.id', '=', 'allocation.reservation_id')
            ->where('allocation.tabung_account_id', $tabungAccountId)->where('allocation.allocation_bucket', 'TABUNG')
            ->where('reservation.status', 'ACTIVE')->selectRaw('allocation.source_year, SUM(allocation.units) AS units')
            ->groupBy('allocation.source_year')->get();
        $result = [];
        foreach ($legacy->concat($allocated) as $row) {
            if ($row->source_year === null) {
                throw new \DomainException('Tabung hold has no source year.');
            }
            $year = (int) $row->source_year;
            $result[$year] = ($result[$year] ?? 0) + (int) $row->units;
        }

        return $result;
    }

    /** Call with the employee and Tabung account locked; FIFO on free posted units only. */
    public function allocateTabungFifo(int $tabungAccountId, int $units): array
    {
        if ($units < 0) {
            throw new \DomainException('Tabung allocation must be nonnegative.');
        }
        $pending = $this->pendingTabungBySource($tabungAccountId);
        $cohorts = DB::table('leave_ledger_entries')->where('tabung_account_id', $tabungAccountId)
            ->selectRaw('source_year, SUM(signed_half_day_units) AS units')->groupBy('source_year')->orderBy('source_year')->get();
        $allocation = [];
        $remaining = $units;
        foreach ($cohorts as $cohort) {
            $year = (int) $cohort->source_year;
            $balance = (int) $cohort->units;
            $hold = $pending[$year] ?? 0;
            unset($pending[$year]);
            if ($cohort->source_year === null || $balance < $hold || $hold < 0) {
                throw new \DomainException('Invalid Tabung source balance or hold.');
            }
            $take = min($balance - $hold, $remaining);
            if ($take > 0) {
                $allocation[$year] = $take;
                $remaining -= $take;
            }
        }
        if (array_sum($pending) !== 0 || $remaining !== 0) {
            throw new \DomainException('Insufficient free Tabung source balance.');
        }

        return $allocation;
    }

    private function totalFor(string $accountColumn, int $accountId): int
    {
        $total = DB::table('leave_ledger_entries')
            ->where($accountColumn, $accountId)
            ->selectRaw('CAST(COALESCE(SUM(signed_half_day_units), 0) AS CHAR) AS total')
            ->value('total');

        $integer = filter_var((string) $total, FILTER_VALIDATE_INT);

        if ($integer === false) {
            throw new OverflowException('The posted ledger total exceeds the supported integer range.');
        }

        return $integer;
    }
}
