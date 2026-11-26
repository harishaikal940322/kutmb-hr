<?php

namespace App\Services\Leave;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** A cap is derived from an immutable HR-confirmed entitlement, never a rate suggestion. */
class TabungCapacity
{
    public function confirmedCapFor(int $employeeId, int $year): ?int
    {
        $units = DB::table('leave_annual_entitlement_confirmations as confirmation')
            ->join('leave_annual_accounts as annual', function ($join): void {
                $join->on('annual.id', '=', 'confirmation.annual_account_id')
                    ->on('annual.employee_id', '=', 'confirmation.employee_id')
                    ->on('annual.leave_year', '=', 'confirmation.leave_year');
            })
            ->where('annual.employee_id', $employeeId)->where('annual.leave_year', $year)
            ->value('confirmation.confirmed_half_day_units');

        return $units === null ? null : (int) $units;
    }

    /** @return array{cap_units:?int,posted_units:?int,remaining_units:?int,excess_units:?int} */
    public function snapshot(?int $confirmedCap, ?int $posted): array
    {
        if (($confirmedCap !== null && $confirmedCap < 0) || ($posted !== null && $posted < 0)) {
            throw new InvalidArgumentException('Tabung capacity requires nonnegative confirmed cap and posted balance.');
        }

        return [
            'cap_units' => $confirmedCap,
            'posted_units' => $posted,
            'remaining_units' => $confirmedCap === null || $posted === null ? null : max(0, $confirmedCap - $posted),
            'excess_units' => $confirmedCap === null || $posted === null ? null : max(0, $posted - $confirmedCap),
        ];
    }
}
