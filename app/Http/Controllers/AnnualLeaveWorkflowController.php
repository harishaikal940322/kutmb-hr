<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Leave\BalanceProjection;
use App\Services\Leave\LeaveRequestWorkflowService;
use App\Services\Leave\LedgerPostedTotals;
use App\Services\Leave\TeamAvailabilityCalendar;
use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnnualLeaveWorkflowController extends Controller
{
    /** Permissions are necessary; linked employee and office scope remain independent. */
    public static function capabilities(User $user): array
    {
        $today = now('Asia/Kuala_Lumpur')->toDateString();
        $linked = DB::table('employees')->where('user_id', $user->id)->exists();
        $self = $user->can('leave.request.self');
        $decision = $user->can('leave.request.decide');
        $team = $user->can('leave.calendar.team.view');
        $review = $user->can('leave.partial-cancellation.review');
        $entitlements = $user->can('leave.entitlement.view');
        $technical = $user->can('iam.rbac.manage');
        $calendar = $user->can('leave.calendar.manage');
        $policy = $user->can('leave.policy.manage');
        $reports = $user->can('leave.reports.view');
        $yearEnd = $user->can('leave.year-end.preview');
        $record = $user->can('leave.request.record-on-behalf');
        $adjustments = $user->can('leave.adjustment.prepare') || $user->can('leave.adjustment.decide');
        $settlements = $user->can('leave.settlement.prepare') || $user->can('leave.settlement.confirm');

        $unitAvailable = false;
        if ($team && $linked) {
            $units = DB::table('employee_assignments as assignment')
                ->join('employees as employee', 'employee.id', '=', 'assignment.employee_id')
                ->join('organization_units as unit', 'unit.id', '=', 'assignment.unit_id')
                ->where('employee.user_id', $user->id)
                ->where('employee.service_start', '<=', $today)
                ->where(fn (Builder $query) => $query->whereNull('employee.service_end')->orWhere('employee.service_end', '>=', $today))
                ->where('assignment.effective_from', '<=', $today)
                ->where(fn (Builder $query) => $query->whereNull('assignment.effective_to')->orWhere('assignment.effective_to', '>=', $today))
                ->limit(2)->get(['unit.active']);
            $unitAvailable = $units->count() === 1 && (bool) $units->first()->active;
        }

        $sections = [];
        if ($self && $linked) {
            $sections[] = 'apply';
            $sections[] = 'requests';
        }
        if ($review || ($decision && self::isApprover($user->id, $today))) {
            $sections[] = 'approvals';
        }
        if ($team && $unitAvailable) {
            $sections[] = 'team';
        }
        if ($entitlements) {
            $sections[] = 'staff';
        }

        return [
            'sections' => $sections,
            'module' => $self || $decision || $team || $review || $entitlements || $technical || $calendar || $policy || $reports || $yearEnd || $record || $adjustments || $settlements,
            'can_manage_calendar' => $calendar,
            'can_manage_policy' => $policy,
            'can_view_reports' => $reports,
            'can_preview_year_end' => $yearEnd,
            'can_record_on_behalf' => $record,
            'can_manage_adjustments' => $adjustments,
            'can_manage_settlements' => $settlements,
            'can_view_own_ledger' => $self && $linked,
            'technical' => $technical,
            'needs_employee' => ($self || $team) && ! $linked,
            'needs_unit' => $team && $linked && ! $unitAvailable,
            'needs_office' => $decision && ! self::isApprover($user->id, $today),
            'can_decide' => $decision,
            'can_review_partial' => $review,
        ];
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
    public function workspace(int $userId, LedgerPostedTotals $totals, BalanceProjection $projection, array $access): array
    {
        $today = now('Asia/Kuala_Lumpur')->toDateString();
        $canSelf = in_array('requests', $access['sections'], true);
        $canDecide = $access['can_decide'] && self::isApprover($userId, $today);
        $canReviewPartial = $access['can_review_partial'];
        $accounts = $canSelf ? $this->accounts($userId, (int) substr($today, 0, 4)) : null;
        $balance = $accounts === null ? null : DB::transaction(function () use ($accounts, $totals, $projection): array {
            $posted = $totals->forAccounts($accounts->annual_id, $accounts->tabung_id);
            $holds = DB::table('leave_reservations')->where('status', 'ACTIVE');
            $annual = $totals->pendingAnnualUnits((int) $accounts->annual_id);
            $tabung = (int) $holds->where('tabung_account_id', $accounts->tabung_id)->sum('tabung_units');

            return [...$posted, ...$projection->project($posted['annual_half_day_units'], $posted['tabung_half_day_units'], $annual, $tabung, 0), 'pending' => $annual + $tabung];
        });

        $nextYear = (int) substr($today, 0, 4) + 1;
        $future = $canSelf ? $this->accounts($userId, $nextYear, true) : null;
        $futureBalance = $future === null ? null : DB::transaction(function () use ($future, $totals): array {
            DB::table('leave_annual_accounts')->where('id', $future->annual_id)->lockForUpdate()->first();
            $pending = $totals->pendingAnnualUnits((int) $future->annual_id);
            $commitments = (int) DB::table('leave_annual_commitments')->where('annual_account_id', $future->annual_id)->where('status', 'COMMITTED')->sum('units');

            return ['confirmed' => (int) $future->confirmed_half_day_units, 'pending' => $pending,
                'committed' => $commitments, 'available' => (int) $future->confirmed_half_day_units - $pending - $commitments];
        });

        $requests = $this->requests()
            ->join('leave_reservations as reservation', 'reservation.id', '=', 'revision.reservation_id')
            ->select('leave.id', 'leave.request_key', 'leave.status', 'leave.leave_type', 'employee.display_name', 'revision.requested_units', 'revision.reason', 'reservation.tabung_units', 'leave.current_step_order', 'leave.current_revision')
            ->selectSub(DB::table('leave_request_days')->whereColumn('revision_id', 'revision.id')->selectRaw('MIN(leave_date)'), 'starts_on')
            ->selectSub(DB::table('leave_request_days')->whereColumn('revision_id', 'revision.id')->selectRaw('MAX(leave_date)'), 'ends_on')
            ->selectSub(DB::table('leave_request_days')->whereColumn('revision_id', 'revision.id')
                ->selectRaw("CASE WHEN COUNT(*) = COUNT(DISTINCT leave_date) * 2 THEN 'FULL' WHEN COUNT(*) = 1 THEN MIN(session_code) ELSE 'UNSUPPORTED' END"), 'session_mode')
            ->orderByDesc('leave.id');
        /** @var Paginator<int, \stdClass> $history */
        $history = $this->owned(clone $requests, $userId)
            ->when(! $canSelf, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->simplePaginate(20, pageName: 'history_page');
        /** @var Paginator<int, \stdClass> $inbox */
        $inbox = $this->assigned(clone $requests, $userId)
            ->when(! $canDecide, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->addSelect('step.is_final')->simplePaginate(20, pageName: 'approval_page');

        $generalManager = $canDecide && $this->isCurrentGeneralManager($userId);
        /** @var Paginator<int, \stdClass>|null $cancellations */
        $cancellations = $generalManager ? $this->cancellations($requests, $userId)
            ->addSelect('cancellation.reason as cancellation_reason')
            ->simplePaginate(20, pageName: 'cancellation_page') : null;

        $partialReviews = $canReviewPartial || $generalManager
            ? $this->partialCases($userId, $canReviewPartial, $generalManager)
                ->orderByDesc('partial.id')->simplePaginate(20, pageName: 'partial_page')
                ->appends(['view' => 'workflow', 'section' => 'approvals'])
            : null;

        foreach ([$history, $inbox, $cancellations] as $page) {
            if ($page === null) {
                continue;
            }
            $ids = $page->getCollection()->pluck('id');
            $actions = DB::table('leave_workflow_actions as action')
                ->join('leave_request_revisions as revision', 'revision.id', '=', 'action.revision_id')
                ->whereIn('revision.request_id', $ids)
                ->orderBy('action.id')->get(['revision.request_id', 'revision.revision as action_revision', 'action.action_code', 'action.created_at'])
                ->groupBy('request_id');
            $cancellationActions = $page !== $inbox ? DB::table('leave_request_cancellation_actions')->whereIn('request_id', $ids)
                ->select('request_id', 'created_at')->selectRaw("CONCAT('CANCELLATION_', action_code) AS action_code")
                ->orderBy('id')->get()->groupBy('request_id') : collect();
            $partialStates = $page === $history
                ? DB::table('leave_request_partial_cancellations')->whereIn('request_id', $ids)
                    ->orderBy('cycle')->pluck('status', 'request_id')
                : collect();
            $page->through(static function ($row) use ($actions, $cancellationActions, $partialStates, $page, $history, $today): array {
                $ownFutureAnnual = $page === $history && $row->leave_type === 'AL' && $row->starts_on > $today;
                $partialStatus = $partialStates->get($row->id);
                 $requestActions = $actions->get($row->id, collect());
                $returnedForCorrection = $requestActions->contains(fn ($action): bool => (int) $action->action_revision === (int) $row->current_revision
                    && $action->action_code === 'RETURN_FOR_CORRECTION');
                $capabilities = [
                    'partial_status' => $partialStatus,
                    'can_return_for_correction' => $page !== $history && $row->leave_type === 'AL'
                        && $row->starts_on !== null && $row->starts_on > $today && $row->current_step_order !== null,
                    'can_resubmit' => $page === $history && $row->leave_type === 'AL' && $row->status === 'DRAFT'
                        && $returnedForCorrection && $row->session_mode !== 'UNSUPPORTED',
                    'can_request_partial' => $page === $history && $row->leave_type === 'AL' && $row->status === 'APPROVED'
                        && $row->starts_on !== null && $row->starts_on <= $today && $row->ends_on >= $today
                        && ! in_array($partialStatus, ['REQUESTED', 'HR_VERIFIED', 'CONFIRMED'], true),
                    'can_withdraw' => $ownFutureAnnual && $row->current_step_order !== null,
                    'can_request_cancellation' => $ownFutureAnnual && $row->status === 'APPROVED',
                ];
                $result = (array) $row;
                unset($result['current_step_order'], $result['tabung_units'], $result['current_revision']);

                return [...$result, ...$capabilities, 'actions' => $requestActions->concat($cancellationActions->get($row->id, collect()))->sortBy('created_at')->values()];
            });
        }

        return [
            'today' => $today,
            'approver' => in_array('approvals', $access['sections'], true),
            'can_decide' => $canDecide && self::isApprover($userId, $today),
            'partial_reviews' => $partialReviews,
            'linked' => $canSelf,
            'can_view_team' => in_array('team', $access['sections'], true),
            'balance' => $balance,
            'future_balance' => $futureBalance,
            'history' => $history->appends(['view' => 'workflow', 'section' => 'requests']),
            'cancellations' => $cancellations?->appends(['view' => 'workflow', 'section' => 'approvals', ...request()->only(['approval_page', 'partial_page'])]),
            'inbox' => $inbox->appends(['view' => 'workflow', 'section' => 'approvals', ...request()->only(['cancellation_page', 'partial_page'])]),
        ];
    }

    public function team(Request $request, TeamAvailabilityCalendar $calendar): JsonResponse
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);

        try {
            $slots = $calendar->forCurrentUnitMonth($request->user()->id, $data['month']);
        } catch (DomainException) {
            abort(403);
        }

        return response()->json(['month' => $data['month'], 'slots' => $slots], headers: ['Cache-Control' => 'private, no-store']);
    }

    public function store(Request $request, LeaveRequestWorkflowService $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('leave.request.self'), 403);
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
        $requestedYear = (int) substr($data['start'], 0, 4);
        $future = $type === 'AL' && $requestedYear === $year + 1;
        $lastYear = (int) substr($data['end'], 0, 4);
        $crossYear = $type === 'AL' && $requestedYear === $year && $lastYear === $year + 1;
        if ((! $future && $requestedYear !== $year) || (! $crossYear && $lastYear !== $requestedYear)
            || ($data['session'] !== 'FULL' && $data['start'] !== $data['end'])) {
            throw ValidationException::withMessages(['start' => 'Gunakan tahun semasa atau AL sehingga tahun hadapan. Separuh hari terhad kepada satu tarikh.']);
        }

        $accounts = $this->accounts($userId, $requestedYear, $future);
        if ($accounts === null) {
            throw ValidationException::withMessages(['start' => $future ? 'Kelayakan tahun hadapan perlu disahkan oleh HR.' : 'Kelayakan tahun semasa perlu disahkan dan dikreditkan sebelum permohonan.']);
        }

        try {
            $workflow->submitCurrentYearRange($userId, $accounts->annual_id, $accounts->tabung_id,
                $data['key'], $type, $data['start'], $data['end'], $data['session'], $data['reason'] ?? null);
        } catch (DomainException|\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['start' => $exception->getMessage()]);
        }

        return back()->with('success', 'Permohonan dihantar.');
    }

    public function resubmit(Request $request, int $leaveRequest, LeaveRequestWorkflowService $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('leave.request.self'), 403);
        $actor = $request->user()->id;
        abort_unless($this->owned($this->requests(), $actor)->where('leave.id', $leaveRequest)->exists(), 404);
        $data = $request->validate([
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after_or_equal:start'],
            'session' => ['required', 'in:FULL,AM,PM'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'key' => ['required', 'uuid'],
        ]);
        $today = now('Asia/Kuala_Lumpur')->toDateString();
        $year = (int) substr($today, 0, 4);
        if ($data['start'] < $today || (int) substr($data['start'], 0, 4) !== $year || (int) substr($data['end'], 0, 4) !== $year
            || ($data['session'] !== 'FULL' && $data['start'] !== $data['end'])) {
            throw ValidationException::withMessages(['start' => 'Pilih tarikh bekerja pada hari ini atau selepasnya dalam tahun semasa. Separuh hari terhad kepada satu tarikh.']);
        }
        $accounts = $this->accounts($actor, $year);
        if ($accounts === null) {
            throw ValidationException::withMessages(['start' => 'Kelayakan tahun semasa perlu disahkan dan dikreditkan sebelum permohonan.']);
        }

        try {
            $workflow->resubmitCurrentYearAnnualRange($actor, $leaveRequest, $accounts->annual_id, $accounts->tabung_id,
                $data['key'], $data['start'], $data['end'], $data['session'], $data['reason'] ?? null);
        } catch (DomainException|\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['start' => $exception->getMessage()]);
        }

        return back()->with('success', 'Permohonan yang dibetulkan dihantar semula untuk semakan.');
    }

    public function decide(Request $request, int $leaveRequest, LeaveRequestWorkflowService $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('leave.request.decide'), 403);
        $actor = $request->user()->id;
        $assigned = $this->assigned($this->requests(), $actor)->where('leave.id', $leaveRequest)->exists();
        $key = $request->input('key');
        $replay = is_string($key) && strlen($key) <= 36 && $this->requests()
            ->join('leave_workflow_actions as action', 'action.revision_id', '=', 'revision.id')
            ->where('leave.id', $leaveRequest)->where('action.actor_id', $actor)
            ->where('action.operation_key', $key)->exists();
        abort_unless($assigned || $replay, 404);
        $data = $request->validate([
            'decision' => ['required', 'in:SUPPORT,NOT_SUPPORT,APPROVE,REJECT,RETURN_FOR_CORRECTION'],
            'reason' => ['nullable', 'string', 'max:2000', 'required_if:decision,NOT_SUPPORT,REJECT,RETURN_FOR_CORRECTION'],
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
        abort_unless($request->user()->can('leave.request.self'), 403);
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
        abort_unless($request->user()->can('leave.request.self'), 403);
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
        abort_unless($request->user()->can('leave.request.decide'), 403);
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

    public function requestPartial(Request $request, int $leaveRequest, LeaveRequestWorkflowService $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('leave.request.self'), 403);
        $actor = $request->user()->id;
        abort_unless($this->owned($this->requests(), $actor)->where('leave.id', $leaveRequest)->exists(), 404);
        $data = $request->validate(['key' => ['required', 'uuid'], 'reason' => ['required', 'string', 'max:1000']]);

        try {
            $workflow->requestApprovedAnnualLeavePartialCancellation($actor, $leaveRequest, $data['key'], $data['reason']);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }

        return back();
    }

    public function verifyPartial(Request $request, int $case, LeaveRequestWorkflowService $workflow): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->can('leave.partial-cancellation.review'), 403);
        $data = $request->validate([
            'key' => ['required', 'uuid'], 'reason' => ['required', 'string', 'max:1000'],
            'actual_return_date' => ['required', 'date_format:Y-m-d'],
            'actual_return_session' => ['required', 'in:AM,PM'],
        ]);
        $requestId = $this->partialCases($actor->id, true, false, $data['key'])->where('partial.id', $case)->select('partial.request_id')->value('partial.request_id');
        abort_if($requestId === null, 404);

        try {
            $workflow->verifyApprovedAnnualLeavePartialCancellation($actor->id, $requestId, $case, $data['key'], $data['actual_return_date'], $data['reason'], $data['actual_return_session']);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['actual_return_date' => $exception->getMessage()]);
        }

        return back();
    }

    public function decidePartial(Request $request, int $case, LeaveRequestWorkflowService $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('leave.request.decide'), 403);
        $actor = $request->user()->id;
        abort_unless($this->isCurrentGeneralManager($actor), 403);
        $data = $request->validate([
            'key' => ['required', 'uuid'], 'decision' => ['required', 'in:CONFIRM,REJECT'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $requestId = $this->partialCases($actor, false, true, $data['key'])->where('partial.id', $case)->select('partial.request_id')->value('partial.request_id');
        abort_if($requestId === null, 404);

        try {
            $workflow->decideApprovedAnnualLeavePartialCancellation($actor, $requestId, $case, $data['key'], $data['decision'], $data['reason'] ?? null);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['decision' => $exception->getMessage()]);
        }

        return back();
    }

    private function partialCases(int $actor, bool $hr, bool $gm, ?string $key = null): Builder
    {
        $statuses = array_merge($hr ? ['REQUESTED'] : [], $gm ? ['HR_VERIFIED'] : []);

        return $this->requests()
            ->join('leave_request_partial_cancellations as partial', 'partial.revision_id', '=', 'revision.id')
            ->whereColumn('employee.user_id', 'leave.requester_user_id')
            ->where('leave.requester_user_id', '<>', $actor)
            ->where(fn (Builder $query) => $query->where('partial.case_kind', 'ORDINARY')
                ->orWhere(fn (Builder $special) => $special->where('partial.case_kind', 'AFTER_END')->where('partial.requested_by', '<>', $actor)
                    ->when($gm && ! $hr, fn (Builder $gmScope) => $gmScope->where('partial.hr_verified_by', '<>', $actor))
                    ->when($hr && ! $gm && ! User::query()->findOrFail($actor)->can('leave.adjustment.decide'), fn (Builder $denied) => $denied->whereRaw('1 = 0'))))
            ->where(fn (Builder $query) => $query->whereIn('partial.status', $statuses)
                ->when($key !== null && $hr, fn (Builder $query) => $query->orWhere(fn (Builder $query) => $query
                    ->where('partial.hr_verified_by', $actor)->where('partial.hr_operation_key', $key)))
                ->when($key !== null && $gm, fn (Builder $query) => $query->orWhere(fn (Builder $query) => $query
                    ->where('partial.general_manager_user_id', $actor)->where('partial.decision_operation_key', $key))))
            ->select('partial.id', 'partial.status', 'partial.case_kind', 'partial.evidence_reference', 'partial.request_reason', 'partial.actual_return_date', 'partial.actual_return_session',
                'partial.unused_half_day_units', 'employee.display_name', 'leave.request_key');
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

    private function accounts(int $userId, int $year, bool $future = false): ?\stdClass
    {
        $query = DB::table('employees as employee')
            ->join('leave_annual_accounts as annual', 'annual.employee_id', '=', 'employee.id')
            ->join('leave_tabung_accounts as tabung', 'tabung.employee_id', '=', 'employee.id')
            ->join('leave_annual_entitlement_confirmations as entitlement', 'entitlement.annual_account_id', '=', 'annual.id')
            ->where('employee.user_id', $userId)->where('annual.leave_year', $year);
        if (! $future) {
            $query->join('leave_ledger_entries as credit', 'credit.annual_account_id', '=', 'annual.id')
                ->where('credit.kind', 'annual_entitlement_credit')
                ->whereColumn('credit.signed_half_day_units', 'entitlement.confirmed_half_day_units');
        }

        return $query->first(['annual.id as annual_id', 'tabung.id as tabung_id', 'entitlement.confirmed_half_day_units']);
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
