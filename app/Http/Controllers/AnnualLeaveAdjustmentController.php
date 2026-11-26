<?php

namespace App\Http\Controllers;

use App\Services\Leave\LeaveAdjustmentService;
use App\Services\Leave\LeaveRequestWorkflowService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AnnualLeaveAdjustmentController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        $prepare = $actor->can('leave.adjustment.prepare');
        $decide = $actor->can('leave.adjustment.decide');
        abort_unless($prepare || $decide, 403);
        $rows = DB::table('leave_balance_adjustments as adjustment')
            ->join('employees as employee', 'employee.id', '=', 'adjustment.employee_id')
            ->join('users as maker', 'maker.id', '=', 'adjustment.prepared_by')
            ->leftJoin('users as checker', 'checker.id', '=', 'adjustment.decided_by')
            ->when(! $decide, fn ($query) => $query->where('adjustment.prepared_by', $actor->id))
            ->orderByDesc('adjustment.id')->paginate(25, [
                'adjustment.id', 'employee.display_name', 'employee.employee_no', 'adjustment.leave_year', 'adjustment.bucket',
                'adjustment.source_year', 'adjustment.signed_units', 'adjustment.reason', 'adjustment.evidence_reference',
                'adjustment.prepared_balance', 'adjustment.prepared_pending', 'adjustment.status', 'adjustment.before_units',
                'adjustment.after_units', 'adjustment.decision_reason', 'adjustment.created_at', 'maker.name as prepared_by_name',
                'checker.name as decided_by_name', 'adjustment.prepared_by', 'employee.user_id as target_user_id',
            ])->through(function ($row) use ($decide, $actor): array {
                $result = (array) $row;
                unset($result['prepared_by'], $result['target_user_id']);
                $result['can_decide'] = $decide && $row->status === 'PENDING'
                    && (int) $row->prepared_by !== $actor->id && (int) $row->target_user_id !== $actor->id;

                return $result;
            });

        return Inertia::render('leave/Adjustments', [
            'access' => AnnualLeaveWorkflowController::capabilities($actor),
            'canPrepare' => $prepare, 'year' => (int) now('Asia/Kuala_Lumpur')->format('Y'), 'adjustments' => $rows,
            'specialCases' => DB::table('leave_request_partial_cancellations as p')
                ->join('leave_requests as q', 'q.id', '=', 'p.request_id')->join('employees as e', 'e.id', '=', 'q.employee_id')
                ->where('p.case_kind', 'AFTER_END')->when(! $decide, fn ($query) => $query->where('p.requested_by', $actor->id))
                ->where(fn ($query) => $query->whereNull('e.user_id')->orWhere('e.user_id', '<>', $actor->id))
                ->orderByDesc('p.id')->simplePaginate(20, ['p.id', 'q.request_key', 'e.employee_no', 'e.display_name', 'p.status',
                    'p.request_reason', 'p.evidence_reference', 'p.actual_return_date', 'p.actual_return_session', 'p.unused_half_day_units'], 'special_page'),
            'specialCandidates' => $prepare ? DB::table('leave_requests as q')
                ->join('leave_request_revisions as r', fn ($join) => $join->on('r.request_id', '=', 'q.id')->on('r.revision', '=', 'q.current_revision'))
                ->join('leave_reservations as b', 'b.id', '=', 'r.reservation_id')->join('employees as e', 'e.id', '=', 'q.employee_id')
                ->where('q.status', 'APPROVED')->where('q.leave_type', 'AL')->where('b.status', 'COMMITTED')
                ->where('b.leave_year', (int) now('Asia/Kuala_Lumpur')->format('Y'))->whereColumn('e.user_id', 'q.requester_user_id')
                ->where('e.user_id', '<>', $actor->id)
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('leave_request_days as d')->whereColumn('d.revision_id', 'r.id')->where('d.leave_date', '>', now('Asia/Kuala_Lumpur')->toDateString()))
                ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('leave_request_partial_cancellations as p')->whereColumn('p.request_id', 'q.id')->whereIn('p.status', ['REQUESTED', 'HR_VERIFIED', 'CONFIRMED']))
                ->orderByDesc('q.id')->limit(50)->get(['q.request_key', 'e.employee_no', 'e.display_name']) : [],
            'employees' => $prepare ? DB::table('employees')->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '<>', $actor->id))
                ->orderBy('display_name')->get(['id', 'employee_no', 'display_name']) : [],
        ]);
    }

    public function store(Request $request, LeaveAdjustmentService $service): RedirectResponse
    {
        $data = $request->validate([
            'employee' => ['required', 'integer', 'min:1'], 'bucket' => ['required', 'in:AL,TABUNG'],
            'source_year' => ['nullable', 'required_if:bucket,TABUNG', 'integer', 'min:1900', 'max:'.now('Asia/Kuala_Lumpur')->year],
            'units' => ['required', 'integer', 'min:-100000', 'max:100000', 'not_in:0'],
            'reason' => ['required', 'string', 'max:1000'], 'evidence_reference' => ['required', 'string', 'max:120'], 'key' => ['required', 'uuid'],
        ]);
        try {
            $service->prepare($request->user()->id, $data);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['units' => $exception->getMessage()]);
        }

        return back()->with('success', 'Pelarasan disediakan. Baki belum berubah.');
    }

    public function prepareSpecial(Request $request, LeaveRequestWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate([
            'request_key' => ['required', 'string', 'max:80'],
            'reason' => ['required', 'string', 'max:1000'],
            'evidence_reference' => ['required', 'string', 'max:120'],
            'key' => ['required', 'uuid'],
        ]);
        $id = DB::table('leave_requests')->where('request_key', trim($data['request_key']))->value('id');
        if ($id === null) {
            throw ValidationException::withMessages(['request_key' => 'Permohonan asal tidak ditemui.']);
        }
        try {
            $workflow->prepareAfterEndCancellation($request->user()->id, (int) $id, $data['key'], $data['reason'], $data['evidence_reference']);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['request_key' => $exception->getMessage()]);
        }

        return back()->with('success', 'Pelarasan khas disediakan. Baki belum berubah.');
    }

    public function decide(Request $request, int $adjustment, LeaveAdjustmentService $service): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:APPROVED,REJECTED'], 'reason' => ['required', 'string', 'max:1000'], 'key' => ['required', 'uuid']]);
        try {
            $service->decide($request->user()->id, $adjustment, $data);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['decision' => $exception->getMessage()]);
        }

        return back()->with('success', 'Keputusan pelarasan direkodkan.');
    }
}
