<?php

namespace App\Services\Leave;

use App\Models\AuditEvent;
use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** CT-DEC-004/033: full-year confirmed rate, independent of prorated AL awards. */
class TabungCapacity
{
    public function referenceDate(int $year): string
    {
        $today = now('Asia/Kuala_Lumpur')->toDateString();
        $current = (int) substr($today, 0, 4);

        return $year === $current ? $today : ($year > $current ? sprintf('%04d-01-01', $year) : sprintf('%04d-12-31', $year));
    }

    public function confirmedCapFor(int $employeeId, int $year): ?int
    {
        $row = $this->confirmedRateFor($employeeId, $year);

        return $row === null ? null : (int) $row->annual_rate_units;
    }

    public function confirmedRateFor(int $employeeId, int $year): ?\stdClass
    {
        return DB::table('leave_tabung_cap_confirmations as rate')
            ->join('leave_annual_accounts as annual', 'annual.id', '=', 'rate.annual_account_id')
            ->where('annual.employee_id', $employeeId)->where('annual.leave_year', $year)
            ->where('rate.effective_from', '<=', $this->referenceDate($year))
            ->orderByDesc('rate.effective_from')->first(['rate.annual_rate_units', 'rate.effective_from']);
    }

    public function confirmRate(int $actorId, int $annualAccountId, string $effectiveFrom, int $units, string $reference, string $key): void
    {
        $reference = trim($reference);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $effectiveFrom, new DateTimeZone('Asia/Kuala_Lumpur'));
        if (! app()->environment(['local', 'testing']) || $units < 1 || $units > 100000 || $reference === ''
            || strlen($reference) > 120 || $date === false || $date->format('Y-m-d') !== $effectiveFrom
            || preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:-]{0,95}\z/D', $key) !== 1) {
            throw new DomainException('Kadar penuh, tarikh kuat kuasa dan rujukan pengesahan yang sah diperlukan.');
        }

        DB::transaction(function () use ($actorId, $annualAccountId, $effectiveFrom, $units, $reference, $key): void {
            $candidate = DB::table('leave_annual_accounts')->where('id', $annualAccountId)->first();
            if ($candidate === null) {
                throw new DomainException('Akaun AL tidak ditemui.');
            }
            $employee = DB::table('employees')->where('id', $candidate->employee_id)->lockForUpdate()->first();
            $annual = DB::table('leave_annual_accounts')->where('id', $annualAccountId)->lockForUpdate()->first();
            $actor = User::query()->whereKey($actorId)->lockForUpdate()->first();
            $year = (int) substr($effectiveFrom, 0, 4);
            $current = (int) now('Asia/Kuala_Lumpur')->format('Y');
            if ($employee === null || $annual === null || $actor === null || $actor->status !== 'ACTIVE'
                || $actor->username === null || ! $actor->can('leave.entitlement.confirm')
                || (int) $annual->employee_id !== (int) $employee->id || (int) $annual->leave_year !== $year
                || $year < $current || $year > $current + 1
                || $effectiveFrom > $this->referenceDate($year)
                || ! DB::table('leave_annual_entitlement_confirmations')->where('annual_account_id', $annualAccountId)->exists()) {
                throw new DomainException('Pengesahan HR dan kelayakan AL tahun semasa atau tahun hadapan diperlukan.');
            }
            $existing = DB::table('leave_tabung_cap_confirmations')->where('operation_key', $key)->lockForUpdate()->first();
            if ($existing !== null) {
                if ((int) $existing->annual_account_id === $annualAccountId && (int) $existing->confirmed_by === $actorId
                    && $existing->effective_from === $effectiveFrom && (int) $existing->annual_rate_units === $units && $existing->reference === $reference) {
                    return;
                }
                throw new DomainException('Rujukan operasi kadar telah digunakan.');
            }
            $latest = DB::table('leave_tabung_cap_confirmations')->where('annual_account_id', $annualAccountId)
                ->orderByDesc('effective_from')->lockForUpdate()->first();
            if ($latest !== null && ($effectiveFrom <= $latest->effective_from || $units < (int) $latest->annual_rate_units)) {
                throw new DomainException('Kadar baharu mesti berkuat kuasa selepas rekod terakhir. Penurunan had memerlukan proses pelupusan berasingan.');
            }
            // Do not silently create a retrospective cap change on an already closed year.
            if (DB::table('leave_annual_year_closes')->where('annual_account_id', $annualAccountId)->exists()) {
                throw new DomainException('Tahun ditutup memerlukan rekonsiliasi berasingan.');
            }
            $id = DB::table('leave_tabung_cap_confirmations')->insertGetId([
                'annual_account_id' => $annualAccountId, 'effective_from' => $effectiveFrom,
                'annual_rate_units' => $units, 'confirmed_by' => $actorId, 'reference' => $reference,
                'operation_key' => $key,
            ]);
            AuditEvent::query()->create([
                'occurred_at' => now(), 'actor_id' => $actorId, 'action_code' => 'hr.leave.tabung_cap_confirmed',
                'target_ref' => 'tabung_cap:'.$id, 'outcome' => 'SUCCESS',
                'metadata' => ['source' => 'tabung_cap_confirmation', 'correlation_id' => $key],
            ]);
        }, 3);
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
