<?php

namespace App\Services\Leave;

use App\Models\AuditEvent;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Explicit local-only close for the unambiguous positive carry subset; no scheduled expiry or negative settlement. */
class AnnualLeaveYearCloseService
{
    public function __construct(private readonly LedgerPostedTotals $totals, private readonly AnnualLeaveYearEndPreview $preview) {}

    public function eligibility(int $employeeId, int $year, int $cap): array
    {
        return DB::transaction(function () use ($employeeId, $year, $cap): array {
            try {
                $data = $this->lockedContext($employeeId, $year, $cap);

                return ['can_post' => true, 'reason' => null, 'snapshot' => $data['snapshot']];
            } catch (DomainException $e) {
                return ['can_post' => false, 'reason' => $e->getMessage(), 'snapshot' => null];
            }
        }, 3);
    }

    public function close(int $actorId, int $employeeId, int $year, int $cap, array $expected, string $reason): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new DomainException('Year-end posting is available for local development only.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Sebab pengesahan diperlukan.');
        }

        DB::transaction(function () use ($actorId, $employeeId, $year, $cap, $expected, $reason): void {
            $employee = DB::table('employees')->where('id', $employeeId)->lockForUpdate()->first();
            $actor = User::query()->whereKey($actorId)->lockForUpdate()->first();
            if ($employee === null || $actor === null || $actor->status !== 'ACTIVE' || $actor->username === null
                || ! $actor->can('leave.year-end.post') || (int) $employee->user_id === $actorId) {
                throw new DomainException('Pegawai HR berkuasa yang berbeza daripada pekerja diperlukan.');
            }

            $key = 'annual-close:'.$employeeId.':'.$year;
            $existing = DB::table('leave_annual_year_closes')->where('operation_key', $key)->lockForUpdate()->first();
            if ($existing !== null) {
                if ((int) $existing->actor_id === $actorId && (int) $existing->cap_units === $cap && $existing->reason === $reason
                    && (int) $existing->annual_before === $expected['annual'] && (int) $existing->annual_pending === $expected['pending']
                    && (int) $existing->tabung_before === $expected['tabung'] && (int) $existing->transfer_units === $expected['transfer']) {
                    return;
                }
                throw new DomainException('Tutup tahun sudah direkodkan. Gunakan semakan rekonsiliasi berasingan.');
            }

            $context = $this->lockedContext($employeeId, $year, $cap);
            $snapshot = $context['snapshot'];
            if ($snapshot !== $expected) {
                throw new DomainException('Baki berubah selepas pratonton. Kira semula sebelum mengesahkan.');
            }
            $annual = $context['annual'];
            $next = $context['next'];
            $tabung = $context['tabung'];
            $transfer = $snapshot['transfer'];
            $operation = DB::table('leave_balance_operations')->insertGetId([
                'employee_id' => $employeeId, 'actor_id' => $actorId, 'operation_key' => $key,
                'operation_type' => 'YEAR_CLOSE',
                'payload_hash' => hash('sha256', json_encode([$employeeId, $year, $cap, $snapshot, $actorId, $reason], JSON_THROW_ON_ERROR)),
                'created_at' => now(),
            ]);
            $out = null;
            $in = null;
            if ($transfer > 0) {
                $out = DB::table('leave_ledger_entries')->insertGetId([
                    'annual_account_id' => $annual->id, 'balance_operation_id' => $operation,
                    'signed_half_day_units' => -$transfer, 'kind' => 'annual_year_close_transfer_out',
                    'source_year' => null, 'operation_key' => $key.':out', 'actor_id' => $actorId,
                ]);
                $in = DB::table('leave_ledger_entries')->insertGetId([
                    'tabung_account_id' => $tabung->id, 'balance_operation_id' => $operation,
                    'signed_half_day_units' => $transfer, 'kind' => 'annual_year_close_transfer_in',
                    'source_year' => $year, 'operation_key' => $key.':in', 'actor_id' => $actorId,
                ]);
            }
            $id = DB::table('leave_annual_year_closes')->insertGetId([
                'employee_id' => $employeeId, 'annual_account_id' => $annual->id, 'next_annual_account_id' => $next->id,
                'tabung_account_id' => $tabung->id, 'leave_year' => $year, 'actor_id' => $actorId,
                'balance_operation_id' => $operation, 'operation_key' => $key, 'reason' => $reason,
                'cap_units' => $cap, 'annual_before' => $snapshot['annual'], 'annual_pending' => $snapshot['pending'],
                'tabung_before' => $snapshot['tabung'], 'transfer_units' => $transfer, 'annual_entry_id' => $out, 'tabung_entry_id' => $in,
            ]);
            AuditEvent::query()->create([
                'occurred_at' => now(), 'actor_id' => $actorId, 'action_code' => 'hr.leave.year_close_posted',
                'target_ref' => 'leave_year_close:'.$id, 'outcome' => 'SUCCESS',
                'metadata' => ['source' => 'annual_year_close', 'correlation_id' => $key],
            ]);
        }, 3);
    }

    private function lockedContext(int $employeeId, int $year, int $cap): array
    {
        if (! app()->environment(['local', 'testing']) || $year !== (int) now('Asia/Kuala_Lumpur')->format('Y') - 1) {
            throw new DomainException('Pindahan hanya untuk tahun sebelumnya selepas 31 Disember.');
        }
        $employee = DB::table('employees')->where('id', $employeeId)->lockForUpdate()->first();
        $accounts = DB::table('leave_annual_accounts')->where('employee_id', $employeeId)->whereIn('leave_year', [$year, $year + 1])
            ->orderBy('id')->lockForUpdate()->get()->keyBy('leave_year');
        $annual = $accounts->get($year);
        $next = $accounts->get($year + 1);
        $tabung = DB::table('leave_tabung_accounts')->where('employee_id', $employeeId)->lockForUpdate()->first();
        if ($employee === null || $annual === null || $next === null || $tabung === null) {
            throw new DomainException('Akaun kedua-dua tahun dan Tabung diperlukan.');
        }
        if (DB::table('leave_annual_year_closes')->where('annual_account_id', $annual->id)->exists()) {
            throw new DomainException('Tutup tahun sudah direkodkan.');
        }
        $confirmedCaps = [];
        foreach ([$annual, $next] as $account) {
            $confirmed = DB::table('leave_annual_entitlement_confirmations')->where('annual_account_id', $account->id)->first();
            if ($confirmed === null || ! DB::table('leave_ledger_entries')->where('annual_account_id', $account->id)
                ->where('kind', 'annual_entitlement_credit')->where('signed_half_day_units', $confirmed->confirmed_half_day_units)->exists()) {
                throw new DomainException('Kelayakan kedua-dua tahun mesti disahkan dan dikreditkan.');
            }
            $confirmedCaps[] = (int) $confirmed->confirmed_half_day_units;
        }
        if ($cap !== $confirmedCaps[1]) {
            throw new DomainException('Cap mesti sepadan dengan kelayakan disahkan tahun penerima.');
        }
        if ($confirmedCaps[1] < $confirmedCaps[0]) {
            throw new DomainException('Penurunan cap memerlukan semakan HR; tiada pelupusan automatik.');
        }
        if (DB::table('leave_ledger_entries')->where('tabung_account_id', $tabung->id)->where('source_year', $year)->exists()) {
            throw new DomainException('Sumber tahun ini sudah mempunyai sejarah Tabung dan memerlukan rekonsiliasi berasingan.');
        }
        $posted = $this->totals->forAccounts((int) $annual->id, (int) $tabung->id);
        $hold = $this->totals->pendingAnnualUnits((int) $annual->id);
        $result = $this->preview->previewCloseTransfer($employeeId, $year, $posted['annual_half_day_units'], $hold, $posted['tabung_half_day_units'], $cap);
        if ($result['free_annual_half_day_units'] < 0 || $result['untransferred_half_day_units'] !== 0 || $posted['tabung_half_day_units'] > $cap) {
            throw new DomainException('Defisit, lebihan atau pelupusan Tabung memerlukan peraturan penutupan berasingan.');
        }

        return ['annual' => $annual, 'next' => $next, 'tabung' => $tabung, 'snapshot' => [
            'annual' => $posted['annual_half_day_units'], 'pending' => $hold,
            'tabung' => $posted['tabung_half_day_units'], 'transfer' => $result['transfer_half_day_units'],
        ]];
    }
}
