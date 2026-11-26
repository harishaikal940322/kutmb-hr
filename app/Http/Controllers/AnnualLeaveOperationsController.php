<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Leave\AnnualLeaveYearEndPreview;
use App\Services\Leave\AnnualLeaveYearCloseService;
use App\Services\Leave\ConfirmedVersionLookup;
use App\Services\Leave\LeaveRequestWorkflowService;
use App\Services\Leave\LedgerPostedTotals;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AnnualLeaveOperationsController extends Controller
{
    public function ledger(Request $request): Response
    {
        $employee = DB::table('employees')->where('user_id', $request->user()->id)->first(['id', 'display_name']);
        abort_if($employee === null, 403);
        $year = $this->year($request);
        $annual = DB::table('leave_annual_accounts')->where('employee_id', $employee->id)->where('leave_year', $year)->value('id');
        $tabung = DB::table('leave_tabung_accounts')->where('employee_id', $employee->id)->value('id');
        $entries = DB::table('leave_ledger_entries')->where(function ($query) use ($annual, $tabung): void {
            $query->whereRaw('1 = 0');
            if ($annual !== null) {
                $query->orWhere('annual_account_id', $annual);
            }
            if ($tabung !== null) {
                $query->orWhere('tabung_account_id', $tabung);
            }
        })->orderByDesc('id')->paginate(25, ['id', 'annual_account_id', 'signed_half_day_units', 'kind', 'source_year', 'created_at'])->withQueryString();

        return Inertia::render('leave/Operations', [
            'access' => AnnualLeaveWorkflowController::capabilities($request->user()),
            'mode' => 'ledger', 'year' => $year, 'currentYear' => $this->currentYear(),
            'entries' => $entries, 'employeeName' => $employee->display_name,
            'balance' => $annual !== null && $tabung !== null ? app(LedgerPostedTotals::class)->forAccounts($annual, $tabung) : null,
        ]);
    }

    public function reports(Request $request): Response
    {
        $year = $this->year($request);
        $base = DB::table('leave_requests as request')
            ->join('leave_request_revisions as revision', function ($join): void {
                $join->on('revision.request_id', '=', 'request.id')->on('revision.revision', '=', 'request.current_revision');
            })
            ->where('revision.leave_year', $year);
        $summary = (clone $base)->groupBy('request.leave_type', 'request.status')
            ->orderBy('request.leave_type')->orderBy('request.status')
            ->select('request.leave_type', 'request.status')
            ->selectRaw('COUNT(*) AS request_count, SUM(revision.requested_units) AS units')->get();
        $reviews = (clone $base)->join('leave_attendance_reviews as review', 'review.revision_id', '=', 'revision.id')
            ->join('employees as employee', 'employee.id', '=', 'request.employee_id')
            ->leftJoin('leave_attendance_review_outcomes as outcome', 'outcome.review_id', '=', 'review.id')
            ->orderByDesc('review.id')->paginate(25, ['review.id', 'review.review_code', 'review.opened_at',
                'employee.display_name', 'employee.employee_no', 'request.request_key', 'request.status',
                'employee.user_id as target_user_id', 'outcome.finding', 'outcome.created_at as recorded_at'], 'review_page')->withQueryString()
            ->through(function ($row) use ($request) {
                $row->can_record = (int) ($request->user()->can('leave.attendance.review')
                    && (int) $row->target_user_id !== (int) $request->user()->id
                    && $row->finding === null && $row->status === 'REJECTED');
                unset($row->target_user_id);
                return $row;
            });

        $accounts = $this->accountRows($year)->orderBy('employee.display_name')->orderBy('employee.id')
            ->paginate(25, pageName: 'balance_page')->withQueryString();

        return Inertia::render('leave/Operations', [
            'access' => AnnualLeaveWorkflowController::capabilities($request->user()),
            'mode' => 'reports', 'year' => $year, 'currentYear' => $this->currentYear(),
            'summary' => $summary, 'reviews' => $reviews, 'accounts' => $accounts,
        ]);
    }

    public function recordReviewOutcome(Request $request, int $review): RedirectResponse
    {
        $data = $request->validate([
            'finding' => ['required', 'in:ABSENCE_CONFIRMED,ABSENCE_NOT_CONFIRMED,REFERRED'],
            'note' => ['required', 'string', 'max:1000'],
            'evidence_reference' => ['required', 'string', 'max:255'],
            'operation_key' => ['required', 'uuid'],
        ]);
        DB::transaction(function () use ($request, $review, $data): void {
            $actor = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($actor->status === 'ACTIVE' && $actor->can('leave.attendance.review') && $actor->can('leave.reports.view'), 403);
            $case = DB::table('leave_attendance_reviews as review')
                ->join('leave_request_revisions as revision', 'revision.id', '=', 'review.revision_id')
                ->join('leave_requests as request', 'request.id', '=', 'revision.request_id')
                ->join('employees as employee', 'employee.id', '=', 'request.employee_id')
                ->where('review.id', $review)->lockForUpdate()
                ->first(['review.id', 'request.status', 'request.current_revision', 'revision.revision', 'employee.user_id']);
            abort_if($case === null, 404);
            abort_if((int) $case->user_id === (int) $actor->id, 403);
            if ($case->status !== 'REJECTED' || (int) $case->current_revision !== (int) $case->revision) {
                throw ValidationException::withMessages(['finding' => 'Kes bukan semakan penolakan revision semasa.']);
            }
            $payload = ['finding' => $data['finding'], 'note' => trim($data['note']), 'evidence_reference' => trim($data['evidence_reference'])];
            if ($payload['note'] === '' || $payload['evidence_reference'] === '') {
                throw ValidationException::withMessages(['note' => 'Catatan dan rujukan bukti diperlukan.']);
            }
            $existing = DB::table('leave_attendance_review_outcomes')->where('review_id', $review)->first();
            if ($existing !== null) {
                if ($existing->operation_key === $data['operation_key'] && (int) $existing->reviewer_id === (int) $actor->id
                    && $existing->finding === $payload['finding'] && $existing->note === $payload['note']
                    && $existing->evidence_reference === $payload['evidence_reference']) {
                    return;
                }
                throw ValidationException::withMessages(['finding' => 'Hasil semakan sudah direkodkan.']);
            }
            if (DB::table('leave_attendance_review_outcomes')->where('operation_key', $data['operation_key'])->exists()) {
                throw ValidationException::withMessages(['finding' => 'Rujukan operasi telah digunakan.']);
            }
            DB::table('leave_attendance_review_outcomes')->insert([
                'review_id' => $review, 'reviewer_id' => $actor->id, ...$payload,
                'operation_key' => $data['operation_key'], 'created_at' => now(),
            ]);
            AuditEvent::query()->create([
                'occurred_at' => now(), 'actor_id' => $actor->id,
                'action_code' => 'hr.leave.attendance_review_recorded', 'target_ref' => 'leave_attendance_review:'.$review,
                'outcome' => 'SUCCESS',
                'metadata' => ['source' => 'annual_leave_review', 'reason_code' => $payload['finding'], 'correlation_id' => $data['operation_key']],
            ]);
        }, 3);
        return back()->with('success', 'Hasil semakan HR direkodkan tanpa perubahan baki atau gaji.');
    }

    public function yearEnd(Request $request, AnnualLeaveYearEndPreview $preview, LedgerPostedTotals $totals, AnnualLeaveYearCloseService $close): Response
    {
        $year = $this->year($request);
        $data = $request->validate(['employee' => ['nullable', 'integer', 'min:1'], 'cap' => ['nullable', 'integer', 'min:0', 'max:100000']]);
        $result = null;
        $employeeId = isset($data['employee']) ? (int) $data['employee'] : null;
        if ($employeeId !== null && isset($data['cap'])) {
            $result = DB::transaction(function () use ($employeeId, $year, $data, $preview, $totals): array {
                $employee = DB::table('employees')->where('id', $employeeId)->lockForUpdate()->first(['id']);
                $annual = DB::table('leave_annual_accounts')->where('employee_id', $employeeId)->where('leave_year', $year)->lockForUpdate()->first(['id']);
                $tabung = DB::table('leave_tabung_accounts')->where('employee_id', $employeeId)->lockForUpdate()->first(['id']);
                if ($employee === null || $annual === null || $tabung === null) {
                    throw ValidationException::withMessages(['employee' => 'Akaun AL dan Tabung untuk pekerja ini diperlukan.']);
                }
                $posted = $totals->forAccounts($annual->id, $tabung->id);
                $hold = $totals->pendingAnnualUnits((int) $annual->id);

                return $preview->previewCloseTransfer($employeeId, $year, $posted['annual_half_day_units'], $hold,
                    $posted['tabung_half_day_units'], (int) $data['cap']);
            });
        }

        $eligibility = $employeeId !== null && isset($data['cap']) && $request->user()->can('leave.year-end.post')
            ? $close->eligibility($employeeId, $year, (int) $data['cap']) : null;

        return Inertia::render('leave/Operations', [
            'access' => AnnualLeaveWorkflowController::capabilities($request->user()),
            'mode' => 'year-end', 'year' => $year, 'currentYear' => $this->currentYear(),
            'employees' => DB::table('employees')->orderBy('display_name')->get(['id', 'employee_no', 'display_name']),
            'selectedEmployee' => $employeeId, 'cap' => $data['cap'] ?? null, 'preview' => $result,
            'closeEligibility' => $eligibility,
        ]);
    }

    public function postYearEnd(Request $request, AnnualLeaveYearCloseService $close): RedirectResponse
    {
        $data = $request->validate([
            'employee' => ['required', 'integer', 'min:1'], 'year' => ['required', 'integer', 'min:1900'],
            'cap' => ['required', 'integer', 'min:0', 'max:100000'],
            'annual' => ['required', 'integer'], 'pending' => ['required', 'integer', 'min:0'],
            'tabung' => ['required', 'integer', 'min:0'], 'transfer' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:1000'], 'acknowledged' => ['accepted'],
        ]);
        try {
            $close->close($request->user()->id, (int) $data['employee'], (int) $data['year'], (int) $data['cap'], [
                'annual' => (int) $data['annual'], 'pending' => (int) $data['pending'],
                'tabung' => (int) $data['tabung'], 'transfer' => (int) $data['transfer'],
            ], $data['reason']);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['close' => $e->getMessage()]);
        }

        return back()->with('success', 'Pindahan tutup tahun direkodkan.');
    }

    public function record(Request $request): Response
    {
        $today = now('Asia/Kuala_Lumpur')->toDateString();

        return Inertia::render('leave/Operations', [
            'access' => AnnualLeaveWorkflowController::capabilities($request->user()),
            'mode' => 'record', 'year' => $this->currentYear(), 'currentYear' => $this->currentYear(), 'today' => $today,
            'employees' => DB::table('employees as employee')->join('users as user', 'user.id', '=', 'employee.user_id')
                ->where('user.status', 'ACTIVE')->whereNotNull('user.username')->where('user.id', '<>', $request->user()->id)
                ->where('employee.service_start', '<=', $today)
                ->where(fn ($query) => $query->whereNull('employee.service_end')->orWhere('employee.service_end', '>=', $today))
                ->orderBy('employee.display_name')->get(['employee.id', 'employee.employee_no', 'employee.display_name']),
        ]);
    }

    public function submitRecord(Request $request, LeaveRequestWorkflowService $workflow, ConfirmedVersionLookup $lookup): RedirectResponse
    {
        $data = $request->validate([
            'employee' => ['required', 'integer', 'min:1'], 'leave_type' => ['required', 'in:AL,EL'],
            'start' => ['required', 'date_format:Y-m-d'], 'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
            'session' => ['required', 'in:FULL,AM,PM'], 'reason' => ['nullable', 'string', 'max:1000', 'required_if:leave_type,EL'],
            'recording_reason' => ['required', 'string', 'max:1000'], 'consent_reference' => ['required', 'string', 'max:120'],
            'confirmed_at' => ['required', 'date_format:Y-m-d\TH:i'], 'consent_acknowledged' => ['accepted'], 'key' => ['required', 'uuid'],
        ]);
        $year = $this->currentYear();
        if ((int) substr($data['start'], 0, 4) !== $year || (int) substr($data['end'], 0, 4) !== $year
            || ($data['session'] !== 'FULL' && $data['start'] !== $data['end'])) {
            throw ValidationException::withMessages(['start' => 'Gunakan tarikh tahun semasa; separuh hari terhad kepada satu tarikh.']);
        }
        try {
            DB::transaction(function () use ($request, $data, $year, $workflow, $lookup): void {
                foreach (['leave_calendar_confirmation_guard', 'leave_working_schedule_guard', 'office_assignment_guard'] as $guard) {
                    if (DB::table($guard)->where('id', 1)->lockForUpdate()->first() === null) {
                        throw new DomainException('Konfigurasi cuti belum lengkap.');
                    }
                }
                $employee = DB::table('employees')->where('id', $data['employee'])->lockForUpdate()->first();
                if ($employee === null || $employee->user_id === null || (int) $employee->user_id === (int) $request->user()->id) {
                    throw new DomainException('Pilih pekerja lain yang mempunyai pautan akaun.');
                }
                $annual = DB::table('leave_annual_accounts as annual')
                    ->join('leave_annual_entitlement_confirmations as entitlement', 'entitlement.annual_account_id', '=', 'annual.id')
                    ->join('leave_ledger_entries as credit', 'credit.annual_account_id', '=', 'annual.id')
                    ->where('annual.employee_id', $employee->id)->where('annual.leave_year', $year)
                    ->where('credit.kind', 'annual_entitlement_credit')
                    ->whereColumn('credit.signed_half_day_units', 'entitlement.confirmed_half_day_units')->value('annual.id');
                $tabung = DB::table('leave_tabung_accounts')->where('employee_id', $employee->id)->value('id');
                if ($annual === null || $tabung === null) {
                    throw new DomainException('Kelayakan pekerja belum dikreditkan.');
                }
                $slots = [];
                $last = \Carbon\CarbonImmutable::parse($data['end'], 'Asia/Kuala_Lumpur');
                for ($day = \Carbon\CarbonImmutable::parse($data['start'], 'Asia/Kuala_Lumpur'); $day <= $last; $day = $day->addDay()) {
                    $date = $day->toDateString();
                    $schedule = $lookup->workingScheduleFor($date);
                    $calendar = $lookup->calendarFor($date);
                    if (! in_array((int) $day->format('N'), $schedule->working_weekdays, true) || in_array($date, $calendar['holidays'], true)) {
                        continue;
                    }
                    foreach ($data['session'] === 'FULL' ? ['AM', 'PM'] : [$data['session']] as $session) {
                        $slots[] = ['date' => $date, 'session' => $session];
                    }
                }
                $workflow->submitOnBehalf($request->user()->id, $employee->user_id, $annual, $tabung, $data['key'],
                    $data['leave_type'], $slots, $data['reason'] ?? null, $data['recording_reason'], $data['consent_reference'],
                    $data['confirmed_at'].':00+08:00');
            }, 3);
        } catch (DomainException|\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['start' => $exception->getMessage()]);
        }

        return to_route('leave.annual.record')->with('success', 'Permohonan staf direkodkan dan dihantar untuk kelulusan.');
    }

    private function accountRows(int $year): \Illuminate\Database\Query\Builder
    {
        return DB::table('employees as employee')
            ->leftJoin('leave_annual_accounts as annual', fn ($join) => $join->on('annual.employee_id', '=', 'employee.id')->where('annual.leave_year', $year))
            ->leftJoin('leave_tabung_accounts as tabung', 'tabung.employee_id', '=', 'employee.id')
            ->leftJoin('leave_annual_entitlement_confirmations as entitlement', 'entitlement.annual_account_id', '=', 'annual.id')
            ->select('employee.id', 'employee.employee_no', 'employee.display_name', 'entitlement.confirmed_half_day_units')
            ->selectSub(DB::table('leave_ledger_entries')->whereColumn('annual_account_id', 'annual.id')->selectRaw('COALESCE(SUM(signed_half_day_units), 0)'), 'annual_posted')
            ->selectSub(DB::table('leave_ledger_entries')->whereColumn('tabung_account_id', 'tabung.id')->selectRaw('COALESCE(SUM(signed_half_day_units), 0)'), 'tabung_posted')
            ->selectRaw("(SELECT COALESCE(SUM(reservation.annual_units), 0) FROM leave_reservations AS reservation WHERE reservation.annual_account_id = annual.id AND reservation.status = 'ACTIVE' AND NOT EXISTS (SELECT 1 FROM leave_reservation_slot_allocations AS allocation WHERE allocation.reservation_id = reservation.id)) + (SELECT COALESCE(SUM(allocation.units), 0) FROM leave_reservation_slot_allocations AS allocation INNER JOIN leave_reservations AS reservation ON reservation.id = allocation.reservation_id WHERE allocation.annual_account_id = annual.id AND allocation.allocation_bucket = 'ANNUAL' AND reservation.status = 'ACTIVE') AS annual_pending" )
            ->selectSub(DB::table('leave_reservations')->whereColumn('tabung_account_id', 'tabung.id')->where('status', 'ACTIVE')->selectRaw('COALESCE(SUM(tabung_units), 0)'), 'tabung_pending');
    }

    private function year(Request $request): int
    {
        $data = $request->validate(['year' => ['nullable', 'integer', 'min:1900', 'max:'.($this->currentYear() + 1)]]);

        return (int) ($data['year'] ?? $this->currentYear());
    }

    private function currentYear(): int
    {
        return (int) now('Asia/Kuala_Lumpur')->format('Y');
    }
}
