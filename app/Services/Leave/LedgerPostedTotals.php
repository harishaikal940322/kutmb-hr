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
