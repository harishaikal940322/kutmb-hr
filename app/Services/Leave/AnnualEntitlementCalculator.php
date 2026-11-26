<?php

namespace App\Services\Leave;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;

/** Exact, read-only calculation from an explicitly HR-supplied annual rate. */
class AnnualEntitlementCalculator
{
    /**
     * CT-DEC-003/036: a new hire's start month counts in full.
     * Termination is supported only on a month end; partial-month and exact
     * rounding-tie policy remain unresolved. No rate-band selection or posting.
     *
     * @return array{calculation_type:string,eligible_months:int,annual_half_day_units:int,prorated_half_day_units:int,period_start:string,period_end:string,can_use_for_confirmation:bool}
     */
    public function calculate(string $serviceStart, ?string $serviceEnd, int $year, int $annualUnits): array
    {
        if ($year < 1900 || $year > 9999 || $annualUnits < 1 || $annualUnits > 100000) {
            throw new DomainException('Tahun atau kadar tahunan tidak sah.');
        }

        $start = $this->date($serviceStart);
        $end = $serviceEnd === null ? null : $this->date($serviceEnd);
        $first = $this->date(sprintf('%04d-01-01', $year));
        $last = $this->date(sprintf('%04d-12-31', $year));

        if ($start > $last || ($end !== null && ($end < $first || $end < $start))) {
            throw new DomainException('Tiada tempoh perkhidmatan yang layak dalam tahun dipilih.');
        }

        $newHire = $start >= $first;
        $termination = $end !== null && $end <= $last;
        if ($termination && $end->format('j') !== $end->format('t')) {
            throw new DomainException('Penamatan pada pertengahan bulan memerlukan keputusan kaedah prorata HR.');
        }

        $periodStart = $newHire ? $start : $first;
        $periodEnd = $termination ? $end : $last;
        $months = (int) $periodEnd->format('n') - (int) $periodStart->format('n') + 1;

        // All arithmetic is in half-day units. Reject an exact tie rather than
        // silently choosing a rounding policy that HR has not confirmed.
        $numerator = $annualUnits * $months;
        if ($numerator % 12 === 6) {
            throw new DomainException('Hasil tepat di antara dua unit separuh hari memerlukan keputusan pembundaran HR.');
        }

        $units = intdiv($numerator + 6, 12);

        return [
            'calculation_type' => $termination ? ($newHire ? 'NEW_HIRE_TERMINATION' : 'TERMINATION') : ($newHire ? 'NEW_HIRE' : 'FULL_YEAR'),
            'eligible_months' => $months,
            'annual_half_day_units' => $annualUnits,
            'prorated_half_day_units' => $units,
            'period_start' => $periodStart->format('Y-m-d'),
            'period_end' => $periodEnd->format('Y-m-d'),
            // Exit results are for HR review, not a replacement annual credit.
            'can_use_for_confirmation' => ! $termination && $units > 0,
        ];
    }

    private function date(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Kuala_Lumpur'));
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new DomainException('Tarikh perkhidmatan tidak sah.');
        }

        return $date;
    }
}
