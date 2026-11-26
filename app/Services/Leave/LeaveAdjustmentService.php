<?php

namespace App\Services\Leave;

use App\Models\AuditEvent;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class LeaveAdjustmentService
{
    public function __construct(private readonly LedgerPostedTotals $totals) {}

    public function prepare(int $actor, array $data): int
    {
        $this->assertLocal();

        return DB::transaction(function () use ($actor, $data): int {
            $this->authorize($actor, 'leave.adjustment.prepare');
            $employee = DB::table('employees')->where('id', $data['employee'])->lockForUpdate()->first();
            if ($employee === null || (int) $employee->user_id === $actor) {
                throw new DomainException('Pelarasan sendiri tidak dibenarkan.');
            }
            $year = (int) now('Asia/Kuala_Lumpur')->format('Y');
            $annual = DB::table('leave_annual_accounts')->where('employee_id', $employee->id)->where('leave_year', $year)->lockForUpdate()->first();
            $tabung = DB::table('leave_tabung_accounts')->where('employee_id', $employee->id)->lockForUpdate()->first();
            if ($annual === null || $tabung === null) {
                throw new DomainException('Akaun cuti tahun semasa dan Tabung diperlukan.');
            }
            $payload = [
                'employee_id' => (int) $employee->id, 'annual_account_id' => (int) $annual->id, 'tabung_account_id' => (int) $tabung->id,
                'leave_year' => $year, 'bucket' => $data['bucket'], 'source_year' => $data['bucket'] === 'AL' ? null : (int) $data['source_year'],
                'signed_units' => (int) $data['units'], 'reason' => trim($data['reason']),
                'evidence_reference' => trim($data['evidence_reference']), 'prepared_by' => $actor,
            ];
            if ($payload['reason'] === '' || $payload['evidence_reference'] === '' || $payload['signed_units'] === 0
                || ($payload['source_year'] !== null && ($payload['source_year'] < 1900 || $payload['source_year'] > $year))) {
                throw new DomainException('Amaun, sebab dan rujukan sumber mesti sah.');
            }
            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
            $existing = DB::table('leave_balance_adjustments')->where('submission_key', $data['key'])->lockForUpdate()->first();
            if ($existing !== null) {
                if (! hash_equals($existing->payload_hash, $hash)) {
                    throw new DomainException('Kunci pelarasan telah digunakan untuk input lain.');
                }

                return (int) $existing->id;
            }
            [$balance, $pending] = $this->snapshot($annual->id, $tabung->id, $payload['bucket']);
            $id = DB::table('leave_balance_adjustments')->insertGetId([
                ...$payload, 'submission_key' => $data['key'], 'payload_hash' => $hash,
                'prepared_balance' => $balance, 'prepared_pending' => $pending, 'status' => 'PENDING',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit($actor, $id, 'prepared', $data['key']);

            return $id;
        }, 3);
    }

    public function decide(int $actor, int $id, array $data): void
    {
        $this->assertLocal();
        DB::transaction(function () use ($actor, $id, $data): void {
            $this->authorize($actor, 'leave.adjustment.decide');
            $candidate = DB::table('leave_balance_adjustments')->where('id', $id)->first();
            if ($candidate === null) {
                throw new DomainException('Pelarasan tidak ditemui.');
            }
            $employee = DB::table('employees')->where('id', $candidate->employee_id)->lockForUpdate()->first();
            $annual = DB::table('leave_annual_accounts')->where('id', $candidate->annual_account_id)->lockForUpdate()->first();
            $tabung = DB::table('leave_tabung_accounts')->where('id', $candidate->tabung_account_id)->lockForUpdate()->first();
            $row = DB::table('leave_balance_adjustments')->where('id', $id)->lockForUpdate()->first();
            if ($employee === null || $annual === null || $tabung === null || $row === null
                || (int) $row->prepared_by === $actor || (int) $employee->user_id === $actor
                || (int) $annual->employee_id !== (int) $employee->id || (int) $tabung->employee_id !== (int) $employee->id) {
                throw new DomainException('Pelulus bebas dan akaun sepadan diperlukan.');
            }
            $reason = trim($data['reason']);
            if ($reason === '') {
                throw new DomainException('Sebab keputusan diperlukan.');
            }
            if ($row->status !== 'PENDING') {
                if ((int) $row->decided_by === $actor && $row->decision_key === $data['key']
                    && $row->status === $data['decision'] && $row->decision_reason === $reason) {
                    return;
                }
                throw new DomainException('Pelarasan sudah diputuskan.');
            }
            if (DB::table('leave_balance_adjustments')->where('decision_key', $data['key'])->exists()) {
                throw new DomainException('Kunci keputusan telah digunakan.');
            }
            $update = [
                'status' => $data['decision'], 'decided_by' => $actor, 'decision_key' => $data['key'],
                'decision_reason' => $reason, 'decided_at' => now(), 'updated_at' => now(),
            ];
            if ($data['decision'] === 'APPROVED') {
                if ((int) $row->leave_year !== (int) now('Asia/Kuala_Lumpur')->format('Y')
                    || (int) $annual->leave_year !== (int) $row->leave_year) {
                    throw new DomainException('Pelarasan tahun tertutup memerlukan proses rekonsiliasi berasingan.');
                }
                $confirmed = DB::table('leave_annual_entitlement_confirmations')->where('annual_account_id', $annual->id)->first();
                if ($confirmed === null || ! DB::table('leave_ledger_entries')->where('annual_account_id', $annual->id)
                    ->where('kind', 'annual_entitlement_credit')->where('signed_half_day_units', $confirmed->confirmed_half_day_units)->exists()) {
                    throw new DomainException('Kelayakan tahunan perlu disahkan dan dikreditkan.');
                }
                [$before, $pending] = $this->snapshot($annual->id, $tabung->id, $row->bucket);
                if ($before !== (int) $row->prepared_balance || $pending !== (int) $row->prepared_pending) {
                    throw new DomainException('Baki atau tempahan berubah selepas penyediaan. Tolak dan sediakan semula.');
                }
                $after = $before + (int) $row->signed_units;
                if ($row->signed_units < 0 && $after < $pending) {
                    throw new DomainException('Pengurangan yang menyebabkan kekurangan baki atau mengganggu tempahan memerlukan semakan khusus HR.');
                }
                if ($row->bucket === 'TABUNG') {
                    if ($after < 0 || $after > (int) $confirmed->confirmed_half_day_units) {
                        throw new DomainException('Baki Tabung mesti dalam had kelayakan tahunan disahkan.');
                    }
                    $sourceBalance = (int) DB::table('leave_ledger_entries')->where('tabung_account_id', $tabung->id)
                        ->where('source_year', $row->source_year)->sum('signed_half_day_units');
                    $sourceHold = (int) DB::table('leave_reservations')->where('tabung_account_id', $tabung->id)
                        ->where('tabung_source_year', $row->source_year)->where('status', 'ACTIVE')->sum('tabung_units');
                    if ($row->signed_units < 0 && $sourceBalance + (int) $row->signed_units < $sourceHold) {
                        throw new DomainException('Pengurangan melebihi baki tahun sumber Tabung yang bebas.');
                    }
                    // Historical source changes require the dedicated carry/expiry contract.
                    if ($row->signed_units > 0 && (int) $row->source_year !== (int) $row->leave_year) {
                        throw new DomainException('Kredit Tabung tahun sumber lampau memerlukan proses rekonsiliasi khusus.');
                    }
                }
                $key = 'adjustment:'.$id;
                $operation = DB::table('leave_balance_operations')->insertGetId([
                    'employee_id' => $employee->id, 'actor_id' => $actor, 'operation_key' => $key,
                    'operation_type' => 'HR_ADJUSTMENT',
                    'payload_hash' => hash('sha256', json_encode([$id, $row->payload_hash, $actor, $data['key'], $before, $after], JSON_THROW_ON_ERROR)),
                    'created_at' => now(),
                ]);
                $entry = DB::table('leave_ledger_entries')->insertGetId([
                    'annual_account_id' => $row->bucket === 'AL' ? $annual->id : null,
                    'tabung_account_id' => $row->bucket === 'TABUNG' ? $tabung->id : null,
                    'balance_operation_id' => $operation, 'signed_half_day_units' => $row->signed_units,
                    'kind' => 'hr_manual_adjustment', 'source_year' => $row->source_year, 'operation_key' => $key, 'actor_id' => $actor,
                ]);
                $update += ['before_units' => $before, 'after_units' => $after, 'ledger_entry_id' => $entry];
            }
            DB::table('leave_balance_adjustments')->where('id', $id)->update($update);
            $this->audit($actor, $id, strtolower($data['decision']), $data['key']);
        }, 3);
    }

    private function snapshot(int $annual, int $tabung, string $bucket): array
    {
        $posted = $this->totals->forAccounts($annual, $tabung);
        $holds = DB::table('leave_reservations')->where('status', 'ACTIVE');
        return $bucket === 'AL'
            ? [$posted['annual_half_day_units'], $this->totals->pendingAnnualUnits($annual)]
            : [$posted['tabung_half_day_units'], (int) $holds->where('tabung_account_id', $tabung)->sum('tabung_units')];
    }

    private function authorize(int $actor, string $permission): void
    {
        $user = User::query()->whereKey($actor)->lockForUpdate()->first();
        if ($user === null || $user->status !== 'ACTIVE' || $user->username === null || ! $user->can($permission)) {
            throw new DomainException('Kebenaran pelarasan diperlukan.');
        }
    }

    private function assertLocal(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new DomainException('Pelarasan pembangunan sahaja.');
        }
    }

    private function audit(int $actor, int $id, string $action, string $key): void
    {
        AuditEvent::query()->create([
            'occurred_at' => now(), 'actor_id' => $actor, 'action_code' => 'hr.leave.adjustment.'.$action,
            'target_ref' => 'leave_adjustment:'.$id, 'outcome' => 'SUCCESS',
            'metadata' => ['source' => 'leave_adjustment', 'correlation_id' => $key],
        ]);
    }
}
