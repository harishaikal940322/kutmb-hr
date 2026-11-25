<?php

namespace App\Services\Leave;

use InvalidArgumentException;
use OverflowException;
use RuntimeException;

/** Local/testing-only arithmetic preview; it never closes accounts or posts ledger entries. */
class AnnualLeaveYearEndPreview
{
    /**
     * @return array{operation_key:string,posted_annual_half_day_units:int,pending_annual_hold_half_day_units:int,free_annual_half_day_units:int,eligible_carry_half_day_units:int,tabung_capacity_half_day_units:int,transfer_half_day_units:int,untransferred_half_day_units:int,requires_hr_disposition:bool}
     */
    public function previewCloseTransfer(
        int $employeeId,
        int $leaveYear,
        int $postedAnnualHalfDayUnits,
        int $pendingAnnualHoldHalfDayUnits,
        int $tabungPostedHalfDayUnits,
        int $confirmedAnnualCapHalfDayUnits,
    ): array {
        $this->assertLocal();
        if ($employeeId < 1 || $leaveYear < 1 || $leaveYear > 9999 || $pendingAnnualHoldHalfDayUnits < 0
            || $tabungPostedHalfDayUnits < 0 || $confirmedAnnualCapHalfDayUnits < 0) {
            throw new InvalidArgumentException('Year-close preview requires explicit, nonnegative hold, Tabung and HR-confirmed cap inputs.');
        }
        if ($postedAnnualHalfDayUnits < PHP_INT_MIN + $pendingAnnualHoldHalfDayUnits) {
            throw new OverflowException('Year-close annual balance exceeds the supported integer range.');
        }

        $freeAnnual = $postedAnnualHalfDayUnits - $pendingAnnualHoldHalfDayUnits;
        $eligibleCarry = max(0, $freeAnnual);
        $capacity = max(0, $confirmedAnnualCapHalfDayUnits - $tabungPostedHalfDayUnits);
        $transfer = min($eligibleCarry, $capacity);
        $untransferred = $eligibleCarry - $transfer;

        return [
            'operation_key' => 'annual-close:'.$employeeId.':'.$leaveYear,
            'posted_annual_half_day_units' => $postedAnnualHalfDayUnits,
            'pending_annual_hold_half_day_units' => $pendingAnnualHoldHalfDayUnits,
            'free_annual_half_day_units' => $freeAnnual,
            'eligible_carry_half_day_units' => $eligibleCarry,
            'tabung_capacity_half_day_units' => $capacity,
            'transfer_half_day_units' => $transfer,
            'untransferred_half_day_units' => $untransferred,
            'requires_hr_disposition' => $untransferred > 0,
        ];
    }

    /**
     * The eligible amount must already exclude units transferred or lawfully disposed in prior close/reconciliation operations.
     *
     * @return array{operation_key:string,source_year:int,eligible_untransferred_half_day_units:int,tabung_capacity_half_day_units:int,additional_transfer_half_day_units:int,untransferred_half_day_units:int,requires_hr_disposition:bool}
     */
    public function previewLateReconciliation(
        int $employeeId,
        int $sourceYear,
        int $eligibleUntransferredHalfDayUnits,
        int $tabungPostedHalfDayUnits,
        int $confirmedAnnualCapHalfDayUnits,
        string $sourceReference,
    ): array {
        $this->assertLocal();
        $sourceReference = trim($sourceReference);
        if ($employeeId < 1 || $sourceYear < 1 || $sourceYear > 9999 || $eligibleUntransferredHalfDayUnits < 0
            || $tabungPostedHalfDayUnits < 0 || $confirmedAnnualCapHalfDayUnits < 0
            || preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:-]{0,31}\z/D', $sourceReference) !== 1) {
            throw new InvalidArgumentException('Late reconciliation requires explicit eligible units, a confirmed cap and an opaque source reference.');
        }

        $capacity = max(0, $confirmedAnnualCapHalfDayUnits - $tabungPostedHalfDayUnits);
        $transfer = min($eligibleUntransferredHalfDayUnits, $capacity);
        $untransferred = $eligibleUntransferredHalfDayUnits - $transfer;

        return [
            'operation_key' => 'annual-reconcile:'.$employeeId.':'.$sourceYear.':'.$sourceReference,
            'source_year' => $sourceYear,
            'eligible_untransferred_half_day_units' => $eligibleUntransferredHalfDayUnits,
            'tabung_capacity_half_day_units' => $capacity,
            'additional_transfer_half_day_units' => $transfer,
            'untransferred_half_day_units' => $untransferred,
            'requires_hr_disposition' => $untransferred > 0,
        ];
    }

    private function assertLocal(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Annual year-end previews are unavailable outside local development and testing.');
        }
    }
}
