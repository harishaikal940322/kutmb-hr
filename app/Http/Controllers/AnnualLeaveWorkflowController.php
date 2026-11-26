<?php

namespace App\Http\Controllers;

use App\Services\Leave\BalanceProjection;
use App\Services\Leave\LeaveRequestWorkflowService;
use App\Services\Leave\LedgerPostedTotals;
use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
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

        $requests = $this->requests()
            ->join('leave_reservations as reservation', 'reservation.id', '=', 'revision.reservation_id')
            ->select('leave.id', 'leave.request_key', 'leave.status', 'leave.leave_type', 'employee.display_name', 'revision.requested_units', 'revision.reason', 'reservation.tabung_units', 'leave.current_step_order')
            ->selectSub(DB::table('leave_request_days')->whereColumn('revision_id', 'revision.id')->selectRaw('MIN(leave_date)'), 'starts_on')
            ->selectSub(DB::table('leave_request_days')->whereColumn('revision_id', 'revision.id')->selectRaw('MAX(leave_date)'), 'ends_on')
            ->orderByDesc('leave.id');
        /** @var Paginator<int, \stdClass> $history */
        $history = $this->owned(clone $requests, $userId)
            ->simplePaginate(20, pageName: 'history_page');
        /** @var Paginator<int, \stdClass> $inbox */
        $inbox = $this->assigned(clone $requests, $userId)
            ->addSelect('step.is_final')->simplePaginate(20, pageName: 'approval_page');

        $generalManager = $this->isCurrentGeneralManager($userId);
        /** @var Paginator<int, \stdClass>|null $cancellations */
        $cancellations = $generalManager ? $this->cancellations($requests, $userId)
            ->addSelect('cancellation.reason as cancellation_reason')
            ->simplePaginate(20, pageName: 'cancellation_page') : null;

        foreach ([$history, $inbox, $cancellations] as $page) {
            if ($page === null) {
                continue;
            }
            $ids = $page->getCollection()->pluck('id');
            $actions = DB::table('leave_workflow_actions as action')
                ->join('leave_request_revisions as revision', 'revision.id', '=', 'action.revision_id')
                ->whereIn('revision.request_id', $ids)
                ->orderBy('action.id')->get(['revision.request_id', 'action.action_code', 'action.created_at'])
                ->groupBy('request_id');
            $cancellationActions = $page !== $inbox ? DB::table('leave_request_cancellation_actions')->whereIn('request_id', $ids)
                ->select('request_id', 'created_at')->selectRaw("CONCAT('CANCELLATION_', action_code) AS action_code")
                ->orderBy('id')->get()->groupBy('request_id') : collect();
            $page->through(static function ($row) use ($actions, $cancellationActions, $page, $history, $today): array {
                $ownFutureAnnual = $page === $history && $row->leave_type === 'AL' && $row->starts_on > $today;
                $capabilities = [
                    'can_withdraw' => $ownFutureAnnual && $row->current_step_order !== null,
                    'can_request_cancellation' => $ownFutureAnnual && $row->status === 'APPROVED' && (int) $row->tabung_units === 0,
                ];
                $result = (array) $row;
                unset($result['current_step_order'], $result['tabung_units']);

                return [...$result, ...$capabilities, 'actions' => $actions->get($row->id, collect())->concat($cancellationActions->get($row->id, collect()))->sortBy('created_at')->values()];
            });
        }

        return [
            'today' => $today,
            'approver' => self::isApprover($userId, $today),
            'linked' => DB::table('employees')->where('user_id', $userId)->exists(),
            'balance' => $balance,
            'history' => $history->appends(['view' => 'workflow', ...request()->only(['approval_page', 'cancellation_page'])]),
            'cancellations' => $cancellations?->appends(['view' => 'workflow', ...request()->only(['history_page', 'approval_page'])]),
            'inbox' => $inbox->appends(['view' => 'workflow', ...request()->only(['history_page', 'cancellation_page'])]),
        ];
    }

    public function store(Request $request, LeaveRequestWorkflowService $workflow): RedirectResponse
    {
        $userId = $request->user()->id;
        abort_unless(DB::table('employees')->where('user_id', $userId)->exists(), 403);
        $data = $request->validate([
            'leave_type' => ['sometimes', 'required', 'in:AL,EL'],
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
            'session' => ['required', 'in:FULL,AM,PM'],
            'reason' => ['nullable', 'string', 'max:1000', 'required_if:leave_type,EL'],
            'key' => ['required', 'uuid'],
        ]);
        $type = $data['leave_type'] ?? 'AL';
        $today = now('Asia/Kuala_Lumpur')->toDateString();
        $year = (int) substr($today, 0, 4);
        if (($type === 'AL' && $data['start'] < $today) || ($type === 'EL' && $data['end'] > $today)) {
            throw ValidationException::withMessages(['start' => $type === 'AL'
                ? 'Pilih tarikh mula pada hari ini atau selepasnya.'
                : 'EL terhad kepada hari ini atau tarikh yang telah berlalu.']);
        }
        if ((int) substr($data['start'], 0, 4) !== $year || (int) substr($data['end'], 0, 4) !== $year
            || ($data['session'] !== 'FULL' && $data['start'] !== $data['end'])) {
            throw ValidationException::withMessages(['start' => 'Pilih tarikh dalam tahun semasa. Separuh hari terhad kepada satu tarikh.']);
        }

        $accounts = $this->accounts($userId, $year);
        if ($accounts === null) {
            throw ValidationException::withMessages(['start' => 'Kelayakan tahun semasa perlu disahkan dan dikreditkan sebelum permohonan.']);
        }

        try {
            $workflow->submitCurrentYearRange($userId, $accounts->annual_id, $accounts->tabung_id,
                $data['key'], $type, $data['start'], $data['end'], $data['session'], $data['reason'] ?? null);
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

    public function withdraw(Request $request, int $leaveRequest, LeaveRequestWorkflowService $workflow): RedirectResponse
    {
        $actor = $request->user()->id;
        abort_unless($this->owned($this->requests(), $actor)->where('leave.id', $leaveRequest)->exists(), 404);
        $data = $request->validate(['key' => ['required', 'uuid']]);

        try {
            $workflow->withdrawPendingAnnualLeave($actor, $leaveRequest, $data['key']);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['key' => $exception->getMessage()]);
        }

        return back();
    }

    public function requestCancellation(Request $request, int $leaveRequest, LeaveRequestWorkflowService $workflow): RedirectResponse
    {
        $actor = $request->user()->id;
        abort_unless($this->owned($this->requests(), $actor)->where('leave.id', $leaveRequest)->exists(), 404);
        $data = $request->validate(['key' => ['required', 'uuid'], 'reason' => ['required', 'string', 'max:1000']]);

        try {
            $workflow->requestApprovedAnnualLeaveCancellation($actor, $leaveRequest, $data['key'], $data['reason']);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }

        return back();
    }

    public function decideCancellation(Request $request, int $leaveRequest, LeaveRequestWorkflowService $workflow): RedirectResponse
    {
        $actor = $request->user()->id;
        abort_unless($this->isCurrentGeneralManager($actor), 404);
        $data = $request->validate([
            'key' => ['required', 'uuid'],
            'decision' => ['required', 'in:CONFIRM,REJECT'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $pending = $this->cancellations($this->requests(), $actor)->where('leave.id', $leaveRequest)->exists();
        $replay = DB::table('leave_request_cancellation_actions')->where('request_id', $leaveRequest)
            ->where('actor_id', $actor)->where('operation_key', $data['key'])->whereNotNull('assignment_id')->exists();
        abort_unless($pending || $replay, 404);

        try {
            $workflow->decideApprovedAnnualLeaveCancellation($actor, $leaveRequest, $data['key'], $data['decision'], $data['reason'] ?? null);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['decision' => $exception->getMessage()]);
        }

        return back();
    }

    private function owned(Builder $query, int $actor): Builder
    {
        return $query->where('leave.requester_user_id', $actor)->where('employee.user_id', $actor);
    }

    private function cancellations(Builder $query, int $actor): Builder
    {
        return $query->join('leave_request_cancellation_actions as cancellation', fn ($join) => $join
            ->on('cancellation.revision_id', '=', 'revision.id')->where('cancellation.action_code', 'REQUESTED'))
            ->where('leave.leave_type', 'AL')->where('leave.status', 'WAITING_CANCELLATION')
            ->where('leave.requester_user_id', '<>', $actor)->where('employee.user_id', '<>', $actor)
            ->whereColumn('employee.user_id', 'leave.requester_user_id')
            ->whereNotExists(fn (Builder $decision) => $decision->from('leave_request_cancellation_actions as completed')
                ->whereColumn('completed.request_id', 'leave.id')->whereColumn('completed.cycle', 'cancellation.cycle')
                ->whereIn('completed.action_code', ['CONFIRMED', 'REJECTED']))
            ->whereNotExists(fn (Builder $day) => $day->from('leave_request_days')
                ->whereColumn('revision_id', 'revision.id')->where('leave_date', '<=', now('Asia/Kuala_Lumpur')->toDateString()));
    }

    private function isCurrentGeneralManager(int $actor): bool
    {
        $today = now('Asia/Kuala_Lumpur')->toDateString();
        $generalManagers = DB::table('approval_assignments')->where('office_code', 'PB')
            ->where('effective_from', '<=', $today)
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
            ->limit(2)->pluck('user_id');

        return $generalManagers->count() === 1 && (int) $generalManagers->first() === $actor;
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
            ->join('employees as employee', 'employee.id', '=', 'leave.employee_id')
            ->join('leave_request_revisions as revision', fn ($join) => $join
                ->on('revision.request_id', '=', 'leave.id')->on('revision.revision', '=', 'leave.current_revision'))
            ->whereIn('leave.leave_type', ['AL', 'EL']);
    }

    private function assigned(Builder $query, int $actor): Builder
    {
        $today = now('Asia/Kuala_Lumpur')->toDateString();

        return $query->join('leave_approval_steps as step', fn ($join) => $join
            ->on('step.revision_id', '=', 'revision.id')->on('step.step_order', '=', 'leave.current_step_order'))
            ->join('approval_assignments as office', 'office.id', '=', 'step.assignment_id')
            ->whereColumn('employee.user_id', 'leave.requester_user_id')
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
