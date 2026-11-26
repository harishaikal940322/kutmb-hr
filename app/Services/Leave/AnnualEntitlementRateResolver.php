<?php

namespace App\Services\Leave;

use DateTimeImmutable;
use DateTimeZone;

/** CT-DEC-005: synthetic category mapping and read-only seniority rate snapshot. */
class AnnualEntitlementRateResolver
{
    /**
     * Only the explicitly documented strict >2 and >5 bands are resolved.
     * A snapshot is not an annual award, an anniversary top-up or a posting.
     *
     * @return array{status:string,as_of:string,completed_years:?int,annual_half_day_units:?int,probation_half_day_units:?int,band:?string,message:string}
     */
    public function resolve(string $serviceStart, ?string $confirmationDate, ?string $serviceEnd, ?string $categoryCode, string $asOf): array
    {
        $result = [
            'status' => 'REVIEW',
            'as_of' => $asOf,
            'completed_years' => null,
            'annual_half_day_units' => null,
            'probation_half_day_units' => null,
            'band' => null,
            'message' => '',
        ];

        if ($serviceStart > $asOf || ($serviceEnd !== null && $serviceEnd < $asOf)) {
            return [...$result, 'status' => 'NOT_IN_SERVICE', 'message' => 'Tiada perkhidmatan aktif pada tarikh rujukan.'];
        }

        $zone = new DateTimeZone('Asia/Kuala_Lumpur');
        $start = new DateTimeImmutable($serviceStart, $zone);
        $date = new DateTimeImmutable($asOf, $zone);
        $result['completed_years'] = $start->diff($date)->y;

        if (! in_array($categoryCode, ['DEV-PENGURUS', 'DEV-PEKERJA'], true)) {
            return [...$result, 'message' => 'Pemetaan kategori pekerja belum disahkan; kadar automatik tidak ditetapkan.'];
        }
        if ($confirmationDate === null || $confirmationDate > $asOf) {
            return [...$result, 'status' => 'PROBATION', 'probation_half_day_units' => 12,
                'message' => 'Belum disahkan jawatan menurut rekod. Kredit probation 6 hari ialah transaksi berasingan, belum dipos secara automatik.'];
        }

        $two = $start->modify('+2 years')->format('Y-m-d');
        $five = $start->modify('+5 years')->format('Y-m-d');
        // Leap-day anniversaries have no approved non-leap-year boundary.
        if ($start->format('m-d') === '02-29'
            && (in_array($asOf, [$two, $five, substr($two, 0, 4).'-02-28', substr($five, 0, 4).'-02-28'], true))) {
            return [...$result, 'message' => 'Sempadan ulang tahun 29 Februari memerlukan keputusan HR.'];
        }
        if ($asOf === $two || $asOf === $five || $asOf < $two) {
            return [...$result, 'message' => 'Kadar sebelum dua tahun atau tepat pada sempadan dua/lima tahun memerlukan keputusan HR.'];
        }

        $manager = $categoryCode === 'DEV-PENGURUS';
        $higher = $asOf > $five;
        $units = $higher ? ($manager ? 60 : 50) : ($manager ? 50 : 40);

        return [...$result, 'status' => 'SUGGESTED', 'annual_half_day_units' => $units,
            'band' => $higher ? '>5 tahun' : '>2 hingga <5 tahun',
            'message' => 'Kadar cadangan pada tarikh rujukan untuk kategori demo. HR perlu mengesahkan kadar yang terpakai bagi tahun berkenaan; tiada penambahan baki automatik.'];
    }
}
