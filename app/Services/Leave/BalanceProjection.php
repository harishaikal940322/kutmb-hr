<?php

namespace App\Services\Leave;

use InvalidArgumentException;
use OverflowException;

/** Pure half-day-unit projection; callers must establish authorization and reservation provenance. */
class BalanceProjection
{
    /**
     * Pending values are already allocated, nonnegative holds for the relevant accounts.
     *
     * @return array{annual_available: int, tabung_available: int, combined_available: int, projected_annual: int, projected_tabung: int}
     */
    public function project(int $annual, int $tabung, int $pendingAnnual, int $pendingTabung, int $requested): array
    {
        if ($tabung < 0 || $pendingAnnual < 0 || $pendingTabung < 0 || $requested < 0 || $pendingTabung > $tabung) {
            throw new InvalidArgumentException('Balances and reservations are inconsistent.');
        }
        $annualAvailable = $this->subtract($annual, $pendingAnnual);
        $tabungAvailable = $tabung - $pendingTabung;
        if ($annualAvailable > PHP_INT_MAX - $tabungAvailable) {
            throw new OverflowException('Projection exceeds integer range.');
        }
        $combined = $annualAvailable + $tabungAvailable;
        $annualUsed = min(max($annualAvailable, 0), $requested);
        $remaining = $requested - $annualUsed;
        $tabungUsed = min($tabungAvailable, $remaining);
        $remaining -= $tabungUsed;
        $projectedAnnual = $this->subtract($this->subtract($annualAvailable, $annualUsed), $remaining);

        return [
            'annual_available' => $annualAvailable,
            'tabung_available' => $tabungAvailable,
            'combined_available' => $combined,
            'projected_annual' => $projectedAnnual,
            'projected_tabung' => $tabungAvailable - $tabungUsed,
        ];
    }

    private function subtract(int $balance, int $units): int
    {
        if ($balance < PHP_INT_MIN + $units) {
            throw new OverflowException('Projection exceeds integer range.');
        }

        return $balance - $units;
    }
}
