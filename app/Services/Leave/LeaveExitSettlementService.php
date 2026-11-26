<?php

namespace App\Services\Leave;

use App\Models\AuditEvent;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/** TB-Q06: current exit-year settlement, explicit maker/checker, no payroll processing. */
class LeaveExitSettlementService
{
    public function __construct(private readonly LedgerPostedTotals $totals, private readonly AnnualEntitlementCalculator $calculator) {}

    public function prepare(int $actor, int $employeeId, string $key, string $reference, string $reason): int
    {
        return DB::transaction(function () use ($actor, $employeeId, $key, $reference, $reason): int {
            $employee = $this->employee($employeeId);
            $this->authorize($actor, $employee, 'prepare');
            $reference = trim($reference);
            $reason = trim($reason);
            if ($reference === '' || $reason === '') {
                throw new DomainException('Rujukan bukti dan sebab diperlukan.');
            }
            $existing = DB::table('leave_exit_settlements')->where('operation_key', $key)->first();
            if ($existing !== null) {
                if ((int) $existing->employee_id === $employeeId && (int) $existing->prepared_by === $actor
                    && $existing->evidence_reference === $reference && $existing->reason === $reason) {
                    return (int) $existing->id;
                }
                throw new DomainException('Rujukan operasi sudah digunakan.');
            }
            $snapshot = $this->snapshot($employee);
            if (DB::table('leave_exit_settlements')->where('annual_account_id', $snapshot['annual_account_id'])->exists()) {
                throw new DomainException('Kes penyelesaian tahun ini sudah wujud.');
            }
            $id = DB::table('leave_exit_settlements')->insertGetId([
                'employee_id' => $employeeId, 'annual_account_id' => $snapshot['annual_account_id'],
                'tabung_account_id' => $snapshot['tabung_account_id'], 'exit_date' => $snapshot['exit_date'],
                'credited_units' => $snapshot['credited_units'], 'final_entitlement_units' => $snapshot['final_entitlement_units'],
                'annual_before' => $snapshot['annual_before'], 'tabung_before' => $snapshot['tabung_before'],
                'deficit_units' => $snapshot['deficit_units'], 'offset_units' => $snapshot['offset_units'],
                'residual_units' => $snapshot['residual_units'], 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'prepared_by' => $actor, 'evidence_reference' => $reference, 'reason' => $reason, 'operation_key' => $key,
            ]);
            $this->audit($actor, $id, 'prepared', $key);
            return $id;
        }, 3);
    }

    public function transition(int $actor, int $id, string $event, string $key, string $note, ?string $reference = null, ?string $date = null): void
    {
        DB::transaction(function () use ($actor, $id, $event, $key, $note, $reference, $date): void {
            $candidate = DB::table('leave_exit_settlements')->where('id', $id)->first();
            if ($candidate === null || ! in_array($event, ['STARTED', 'EVIDENCE', 'SETTLED'], true)) {
                throw new DomainException('Kes atau tindakan tidak sah.');
            }
            $employee = $this->employee((int) $candidate->employee_id);
            $this->authorize($actor, $employee, $event === 'EVIDENCE' ? 'prepare' : 'confirm');
            $case = DB::table('leave_exit_settlements')->where('id', $id)->lockForUpdate()->first();
            $note = trim($note);
            $reference = $reference === null ? null : trim($reference);
            if ($note === '' || ($event === 'EVIDENCE' ? ((int) $case->prepared_by !== $actor || $reference === null || $reference === '' || $date === null
                || $date < $case->exit_date || $date > now('Asia/Kuala_Lumpur')->toDateString()) : ((int) $case->prepared_by === $actor || $reference !== null || $date !== null))) {
                throw new DomainException('Penyedia dan pengesah bebas serta bukti yang sah diperlukan.');
            }
            $existing = DB::table('leave_exit_settlement_events')->where('operation_key', $key)->first();
            if ($existing !== null) {
                if ((int) $existing->settlement_id === $id && (int) $existing->actor_id === $actor
                    && $existing->event_code === $event && $existing->note === $note
                    && $existing->external_reference === $reference && $existing->settlement_date === $date) {
                    return;
                }
                throw new DomainException('Rujukan operasi sudah digunakan.');
            }
            $events = DB::table('leave_exit_settlement_events')->where('settlement_id', $id)->lockForUpdate()->get()->keyBy('event_code');
            if ($events->has($event) || $events->has('SETTLED')
                || ($event === 'STARTED' && $events->isNotEmpty())
                || ($event === 'EVIDENCE' && ! $events->has('STARTED'))
                || ($event === 'SETTLED' && (! $events->has('STARTED') || ! $events->has('EVIDENCE')))) {
                throw new DomainException('Tindakan tidak sepadan dengan status penyelesaian.');
            }
            $operation = null;
            if ($event === 'STARTED') {
                $snapshot = $this->snapshot($employee);
                if (json_decode($case->snapshot, true, 512, JSON_THROW_ON_ERROR) != $snapshot) {
                    throw new DomainException('Baki atau rekod sumber berubah; HR perlu menyemak kes ini sebelum tindakan lanjut.');
                }
                $operationKey = 'exit-settlement:'.$id;
                $operation = DB::table('leave_balance_operations')->insertGetId([
                    'employee_id' => $employee->id, 'actor_id' => $actor, 'operation_key' => $operationKey,
                    'operation_type' => 'EXIT_SETTLEMENT', 'payload_hash' => hash('sha256', json_encode([$id, $snapshot, $actor, $key], JSON_THROW_ON_ERROR)),
                    'created_at' => now(),
                ]);
                $reduction = $snapshot['credited_units'] - $snapshot['final_entitlement_units'];
                if ($reduction > 0) {
                    $this->entry($operation, $actor, $operationKey.':prorata', $snapshot['annual_account_id'], null, -$reduction, 'exit_prorata_reduction', null);
                }
                foreach ($snapshot['fifo'] as $year => $units) {
                    $this->entry($operation, $actor, $operationKey.':tabung:'.$year, null, $snapshot['tabung_account_id'], -$units, 'exit_tabung_offset_out', (int) $year);
                }
                if ($snapshot['offset_units'] > 0) {
                    $this->entry($operation, $actor, $operationKey.':annual', $snapshot['annual_account_id'], null, $snapshot['offset_units'], 'exit_tabung_offset_in', null);
                }
            } else {
                // External evidence/confirmation may occur after the calendar year changes.
                if ($employee->service_end !== $case->exit_date) {
                    throw new DomainException('Tarikh tamat perkhidmatan berubah; rujuk HR.');
                }
                $posted = $this->totals->forAccounts((int) $case->annual_account_id, (int) $case->tabung_account_id);
                $snapshot = json_decode($case->snapshot, true, 512, JSON_THROW_ON_ERROR);
                $start = $events->get('STARTED');
                $changed = DB::table('leave_ledger_entries')->where(function ($q) use ($case): void {
                    $q->where('annual_account_id', $case->annual_account_id)->orWhere('tabung_account_id', $case->tabung_account_id);
                })->where('id', '>', $snapshot['last_entry_id'])->where('balance_operation_id', '<>', $start->balance_operation_id)->exists();
                if ($changed || $posted['annual_half_day_units'] !== -(int) $case->residual_units
                    || $posted['tabung_half_day_units'] !== (int) $case->tabung_before - (int) $case->offset_units
                    || $this->totals->pendingAnnualUnits((int) $case->annual_account_id) !== 0
                    || array_sum($this->totals->pendingTabungBySource((int) $case->tabung_account_id)) !== 0) {
                    throw new DomainException('Baki selepas offset berubah; jangan sahkan penyelesaian luar sebelum semakan HR.');
                }
            }
            DB::table('leave_exit_settlement_events')->insert([
                'settlement_id' => $id, 'event_code' => $event, 'actor_id' => $actor, 'operation_key' => $key,
                'balance_operation_id' => $operation, 'external_reference' => $reference, 'settlement_date' => $date, 'note' => $note,
            ]);
            $this->audit($actor, $id, strtolower($event), $key);
        }, 3);
    }

    private function snapshot(object $employee): array
    {
        $today = now('Asia/Kuala_Lumpur')->toDateString();
        $year = (int) substr($today, 0, 4);
        if ($employee->service_end === null || $employee->service_end >= $today || (int) substr($employee->service_end, 0, 4) !== $year) {
            throw new DomainException('Penyediaan terhad kepada pekerja yang telah tamat perkhidmatan dalam tahun semasa.');
        }
        $annual = DB::table('leave_annual_accounts')->where('employee_id', $employee->id)->where('leave_year', $year)->lockForUpdate()->first();
        $tabung = DB::table('leave_tabung_accounts')->where('employee_id', $employee->id)->lockForUpdate()->first();
        if ($annual === null || $tabung === null || DB::table('leave_annual_year_closes')->where('annual_account_id', $annual->id)->exists()
            || DB::table('leave_reservations')->where('employee_id', $employee->id)->where('status', 'ACTIVE')->exists()
            || DB::table('leave_annual_commitments')->whereIn('annual_account_id', DB::table('leave_annual_accounts')->where('employee_id', $employee->id)->select('id'))->where('status', 'COMMITTED')->exists()
            || DB::table('leave_balance_adjustments')->where('employee_id', $employee->id)->where('status', 'PENDING')->exists()
            || DB::table('leave_request_partial_cancellations as p')->join('leave_requests as q', 'q.id', '=', 'p.request_id')->where('q.employee_id', $employee->id)->whereIn('p.status', ['REQUESTED','HR_VERIFIED'])->exists()
            || DB::table('leave_attendance_reviews as review')->join('leave_request_revisions as revision', 'revision.id', '=', 'review.revision_id')
                ->join('leave_requests as request', 'request.id', '=', 'revision.request_id')->where('request.employee_id', $employee->id)
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('leave_attendance_review_outcomes as outcome')
                    ->whereColumn('outcome.review_id', 'review.id')->where('outcome.finding', 'NO_ABSENCE_CONFIRMED'))->exists()) {
            throw new DomainException('Akaun sumber dan semua semakan/tempahan berkaitan mesti selesai dahulu.');
        }
        foreach (DB::table('leave_annual_accounts')->where('employee_id', $employee->id)->where('id', '<>', $annual->id)->get() as $other) {
            $otherPosted = (int) DB::table('leave_ledger_entries')->where('annual_account_id', $other->id)->sum('signed_half_day_units');
            if ($otherPosted !== 0 || $this->totals->pendingAnnualUnits((int) $other->id) !== 0) {
                throw new DomainException('Baki tahun lain memerlukan rekonsiliasi berasingan.');
            }
        }
        $rates = DB::table('leave_tabung_cap_confirmations')->where('annual_account_id', $annual->id)->get();
        $confirmation = DB::table('leave_annual_entitlement_confirmations')->where('annual_account_id', $annual->id)->first();
        if ($rates->count() !== 1 || $rates->first()->effective_from !== sprintf('%04d-01-01', $year) || $confirmation === null) {
            throw new DomainException('Kadar penuh tunggal sejak awal tahun dan kredit disahkan diperlukan; perubahan kadar perlu semakan HR.');
        }
        $rate = (int) $rates->first()->annual_rate_units;
        $opening = $this->calculator->calculate($employee->service_start, null, $year, $rate);
        $final = $this->calculator->calculate($employee->service_start, $employee->service_end, $year, $rate);
        $credited = (int) $confirmation->confirmed_half_day_units;
        $finalUnits = $final['prorated_half_day_units'];
        $entries = DB::table('leave_ledger_entries')->where('annual_account_id', $annual->id);
        if ($opening['prorated_half_day_units'] !== $credited || $finalUnits > $credited
            || (clone $entries)->where('kind', 'annual_entitlement_credit')->where('signed_half_day_units', $credited)->count() !== 1
            || (clone $entries)->whereNotIn('kind', ['annual_entitlement_credit','approved_leave_use','rejected_el_absence_use','approved_leave_cancellation','approved_leave_partial_cancellation'])->exists()) {
            throw new DomainException('Kredit dan sejarah kelayakan asal tidak dapat disahkan tanpa polisi tambahan.');
        }
        $posted = $this->totals->forAccounts((int) $annual->id, (int) $tabung->id);
        $annualBefore = $posted['annual_half_day_units'];
        $tabungBefore = $posted['tabung_half_day_units'];
        $deficit = $credited - $finalUnits - $annualBefore;
        if ($deficit <= 0 || $tabungBefore < 0 || $this->totals->pendingAnnualUnits((int) $annual->id) !== 0
            || array_sum($this->totals->pendingTabungBySource((int) $tabung->id)) !== 0) {
            throw new DomainException('Tiada defisit yang layak atau masih terdapat baki/tempahan tidak konsisten.');
        }
        $offset = min($deficit, $tabungBefore);
        $lastEntry = DB::table('leave_ledger_entries')->where(function ($q) use ($annual, $tabung): void {
            $q->where('annual_account_id', $annual->id)->orWhere('tabung_account_id', $tabung->id);
        })->max('id');
        return [
            'annual_account_id' => (int) $annual->id, 'tabung_account_id' => (int) $tabung->id,
            'exit_date' => $employee->service_end, 'rate_units' => $rate, 'credited_units' => $credited,
            'final_entitlement_units' => $finalUnits, 'annual_before' => $annualBefore, 'tabung_before' => $tabungBefore,
            'deficit_units' => $deficit, 'offset_units' => $offset, 'residual_units' => $deficit - $offset,
            'fifo' => $this->totals->allocateTabungFifo((int) $tabung->id, $offset), 'last_entry_id' => (int) $lastEntry,
        ];
    }

    private function employee(int $id): object
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new DomainException('Penyelesaian baki pembangunan sahaja.');
        }
        return DB::table('employees')->where('id', $id)->lockForUpdate()->first()
            ?? throw new DomainException('Pekerja tidak ditemui.');
    }

    private function authorize(int $actor, object $employee, string $action): void
    {
        $user = User::query()->whereKey($actor)->lockForUpdate()->first();
        if ($user === null || $user->status !== 'ACTIVE' || $user->username === null
            || (int) $employee->user_id === $actor || ! $user->can('leave.settlement.'.$action)) {
            throw new DomainException('Kebenaran HR bebas diperlukan.');
        }
    }

    private function entry(int $operation, int $actor, string $key, ?int $annual, ?int $tabung, int $units, string $kind, ?int $year): void
    {
        DB::table('leave_ledger_entries')->insert([
            'balance_operation_id' => $operation, 'actor_id' => $actor, 'operation_key' => $key,
            'annual_account_id' => $annual, 'tabung_account_id' => $tabung, 'signed_half_day_units' => $units,
            'kind' => $kind, 'source_year' => $year,
        ]);
    }

    private function audit(int $actor, int $id, string $action, string $key): void
    {
        AuditEvent::query()->create([
            'occurred_at' => now(), 'actor_id' => $actor, 'action_code' => 'hr.leave.settlement.'.$action,
            'target_ref' => 'leave_exit_settlement:'.$id, 'outcome' => 'SUCCESS',
            'metadata' => ['source' => 'leave_exit_settlement', 'correlation_id' => $key],
        ]);
    }
}
