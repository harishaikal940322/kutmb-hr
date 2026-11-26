<?php

namespace App\Http\Controllers;

use App\Services\Leave\LeaveAdjustmentService;
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
