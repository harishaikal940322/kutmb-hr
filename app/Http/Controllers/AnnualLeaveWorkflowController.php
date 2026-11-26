<?php

namespace App\Http\Controllers;

use App\Services\Leave\BalanceProjection;
use App\Services\Leave\LeaveRequestWorkflowService;
use App\Services\Leave\LedgerPostedTotals;
use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnnualLeaveWorkflowController extends Controller
{
    public static function hasAccess(int $userId): bool
    {
        $today = now('Asia/Kuala_Lumpur')->toDateString();

        return DB::table('employees')->where('user_id', $userId)->exists()
            || self::isApprover($userId, $today);
    }

    private static function isApprover(int $userId, string $today): bool
    {
        return DB::table('approval_assignments')->where('user_id', $userId)
            ->whereIn('office_code', ['PU', 'PB', 'SECRETARY_ALK'])
            ->where('effective_from', '<=', $today)
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
            ->exists();
    }

    /** @return array<string, mixed> */
    public function workspace(int $userId, LedgerPostedTotals $totals, BalanceProjection $projection): array
    {
        $today = now('Asia/Kuala_Lumpur')->toDateString();
        $accounts = $this->accounts($userId, (int) substr($today, 0, 4));
        $balance = $accounts === null ? null : DB::transaction(function () use ($accounts, $totals, $projection): array {
            $posted = $totals->forAccounts($accounts->annual_id, $accounts->tabung_id);
            $holds = DB::table('leave_reservations')->where('status', 'ACTIVE');
            $annual = (int) (clone $holds)->where('annual_account_id', $accounts->annual_id)->sum('annual_units');
            $tabung = (int) $holds->where('tabung_account_id', $accounts->tabung_id)->sum('tabung_units');

            return [...$posted, ...$projection->project($posted['annual_half_day_units'], $posted['tabung_half_day_units'], $annual, $tabung, 0), 'pending' => $annual + $tabung];
        });

        $requests = $this->requests()->join('employees as employee', 'employee.id', '=', 'leave.employee_id')
            ->select('leave.id', 'leave.request_key', 'leave.status', 'employee.display_name', 'revision.requested_units', 'revision.reason')
            ->selectSub(DB::table('leave_request_days')->whereColumn('revision_id', 'revision.id')->selectRaw('MIN(leave_date)'), 'starts_on')
            ->selectSub(DB::table('leave_request_days')->whereColumn('revision_id', 'revision.id')->selectRaw('MAX(leave_date)'), 'ends_on')
            ->orderByDesc('leave.id');
        /** @var \Illuminate\Pagination\Paginator<int, \stdClass> $history */
        $history = (clone $requests)->where('leave.requester_user_id', $userId)->where('employee.user_id', $userId)
            ->simplePaginate(20, pageName: 'history_page');
        /** @var \Illuminate\Pagination\Paginator<int, \stdClass> $inbox */
        $inbox = $this->assigned($requests, $userId)
            ->addSelect('step.is_final')->simplePaginate(20, pageName: 'approval_page');

        foreach ([$history, $inbox] as $page) {
            $actions = DB::table('leave_workflow_actions as action')
                ->join('leave_request_revisions as revision', 'revision.id', '=', 'action.revision_id')
                ->whereIn('revision.request_id', $page->getCollection()->pluck('id'))
                ->orderBy('action.id')->get(['revision.request_id', 'action.action_code', 'action.created_at'])
                ->groupBy('request_id');
            $page->through(fn ($row) => [...(array) $row, 'actions' => $actions->get($row->id, collect())->values()]);
        }

        return [
            'today' => $today,
            'approver' => self::isApprover($userId, $today),
            'linked' => DB::table('employees')->where('user_id', $userId)->exists(),
            'balance' => $balance,
            'history' => $history->appends(['view' => 'workflow', ...request()->only('approval_page')]),
            'inbox' => $inbox->appends(['view' => 'workflow', ...request()->only('history_page')]),
        ];
    }

    public function store(Request $request, LeaveRequestWorkflowService $workflow): RedirectResponse
    {
        $userId = $request->user()->id;
        abort_unless(DB::table('employees')->where('user_id', $userId)->exists(), 403);
        $data = $request->validate([
            'start' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now('Asia/Kuala_Lumpur')->toDateString()],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
            'session' => ['required', 'in:FULL,AM,PM'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'key' => ['required', 'uuid'],
        ]);
        $year = (int) now('Asia/Kuala_Lumpur')->format('Y');
        if ((int) substr($data['start'], 0, 4) !== $year || (int) substr($data['end'], 0, 4) !== $year
            || ($data['session'] !== 'FULL' && $data['start'] !== $data['end'])) {
            throw ValidationException::withMessages(['start' => 'Pilih tarikh dalam tahun semasa. Separuh hari terhad kepada satu tarikh.']);
        }

        $accounts = $this->accounts($userId, $year);
        if ($accounts === null) {
            throw ValidationException::withMessages(['start' => 'Kelayakan tahun semasa perlu disahkan dan dikreditkan sebelum permohonan.']);
        }

        try {
            $workflow->submitAnnualRange($userId, $accounts->annual_id, $accounts->tabung_id,
                $data['key'], $data['start'], $data['end'], $data['session'], $data['reason'] ?? null);
        } catch (DomainException|\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['start' => $exception->getMessage()]);
        }

        return back()->with('success', 'Permohonan dihantar.');
    }

    public function decide(Request $request, int $leaveRequest, LeaveRequestWorkflowService $workflow): RedirectResponse
    {
        $actor = $request->user()->id;
        $assigned = $this->assigned($this->requests(), $actor)->where('leave.id', $leaveRequest)->exists();
        $key = $request->input('key');
        $replay = is_string($key) && strlen($key) <= 36 && $this->requests()
            ->join('leave_workflow_actions as action', 'action.revision_id', '=', 'revision.id')
            ->where('leave.id', $leaveRequest)->where('action.actor_id', $actor)
            ->where('action.operation_key', $key)->exists();
        abort_unless($assigned || $replay, 404);
        $data = $request->validate([
            'decision' => ['required', 'in:SUPPORT,NOT_SUPPORT,APPROVE,REJECT'],
            'reason' => ['nullable', 'string', 'max:2000', 'required_if:decision,NOT_SUPPORT,REJECT'],
            'key' => ['required', 'uuid'],
        ]);

        try {
            $workflow->decide($actor, $leaveRequest, $data['key'], $data['decision'], $data['reason'] ?? null);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['decision' => $exception->getMessage()]);
        }

        return back()->with('success', 'Tindakan direkodkan.');
    }

    private function accounts(int $userId, int $year): ?\stdClass
    {
        return DB::table('employees as employee')
            ->join('leave_annual_accounts as annual', 'annual.employee_id', '=', 'employee.id')
            ->join('leave_tabung_accounts as tabung', 'tabung.employee_id', '=', 'employee.id')
            ->join('leave_annual_entitlement_confirmations as entitlement', 'entitlement.annual_account_id', '=', 'annual.id')
            ->join('leave_ledger_entries as credit', 'credit.annual_account_id', '=', 'annual.id')
            ->where('employee.user_id', $userId)->where('annual.leave_year', $year)
            ->where('credit.kind', 'annual_entitlement_credit')
            ->whereColumn('credit.signed_half_day_units', 'entitlement.confirmed_half_day_units')
            ->first(['annual.id as annual_id', 'tabung.id as tabung_id']);
    }

    private function requests(): Builder
    {
        return DB::table('leave_requests as leave')
            ->join('leave_request_revisions as revision', fn ($join) => $join
                ->on('revision.request_id', '=', 'leave.id')->on('revision.revision', '=', 'leave.current_revision'))
            ->where('leave.leave_type', 'AL');
    }

    private function assigned(Builder $query, int $actor): Builder
    {
        $today = now('Asia/Kuala_Lumpur')->toDateString();

        return $query->join('leave_approval_steps as step', fn ($join) => $join
            ->on('step.revision_id', '=', 'revision.id')->on('step.step_order', '=', 'leave.current_step_order'))
            ->join('approval_assignments as office', 'office.id', '=', 'step.assignment_id')
            ->join('employees as applicant', 'applicant.id', '=', 'leave.employee_id')
            ->whereColumn('applicant.user_id', 'leave.requester_user_id')
            ->where('step.assignee_user_id', $actor)->where('office.user_id', $actor)
            ->where('leave.requester_user_id', '<>', $actor)
            ->whereColumn('office.office_code', 'step.office_code')
            ->whereRaw('office.unit_id <=> step.unit_id')
            ->where('office.effective_from', '<=', $today)
            ->where(fn (Builder $query) => $query->whereNull('office.effective_to')->orWhere('office.effective_to', '>=', $today))
            ->where(fn (Builder $query) => $query->where('step.stage_code', '<>', 'UNIT_MANAGER_SUPPORT')
                ->orWhereExists(fn (Builder $assignment) => $assignment->from('employee_assignments as current_assignment')
                    ->whereColumn('current_assignment.employee_id', 'leave.employee_id')
                    ->whereColumn('current_assignment.unit_id', 'step.unit_id')
                    ->where('current_assignment.effective_from', '<=', $today)
                    ->where(fn (Builder $period) => $period->whereNull('current_assignment.effective_to')->orWhere('current_assignment.effective_to', '>=', $today))));
    }
}
