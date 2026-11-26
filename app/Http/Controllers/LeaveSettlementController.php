<?php

namespace App\Http\Controllers;

use App\Services\Leave\LeaveExitSettlementService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeaveSettlementController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        $prepare = $actor->can('leave.settlement.prepare');
        $confirm = $actor->can('leave.settlement.confirm');
        abort_unless($prepare || $confirm, 403);
        $data = $request->validate(['status' => ['nullable', 'in:OUTSTANDING,IN_PROGRESS,SETTLED']]);
        $status = $data['status'] ?? null;
        $query = DB::table('leave_exit_settlements as s')
            ->join('employees as e', 'e.id', '=', 's.employee_id')
            ->join('users as maker', 'maker.id', '=', 's.prepared_by')
            ->leftJoin('leave_exit_settlement_events as started', fn ($j) => $j->on('started.settlement_id', '=', 's.id')->where('started.event_code', 'STARTED'))
            ->leftJoin('leave_exit_settlement_events as evidence', fn ($j) => $j->on('evidence.settlement_id', '=', 's.id')->where('evidence.event_code', 'EVIDENCE'))
            ->leftJoin('leave_exit_settlement_events as settled', fn ($j) => $j->on('settled.settlement_id', '=', 's.id')->where('settled.event_code', 'SETTLED'))
            ->leftJoin('users as confirmer', 'confirmer.id', '=', 'settled.actor_id')
            ->where(fn ($q) => $q->whereNull('e.user_id')->orWhere('e.user_id', '<>', $actor->id));
        $rows = (clone $query)->when($status === 'OUTSTANDING', fn ($q) => $q->whereNull('started.id'))
            ->when($status === 'IN_PROGRESS', fn ($q) => $q->whereNotNull('started.id')->whereNull('settled.id'))
            ->when($status === 'SETTLED', fn ($q) => $q->whereNotNull('settled.id'))
            ->orderByDesc('s.id')->paginate(25, [
                's.id','e.display_name','e.employee_no','s.exit_date','s.credited_units','s.final_entitlement_units',
                's.annual_before','s.tabung_before','s.deficit_units','s.offset_units','s.residual_units',
                's.evidence_reference','s.reason','s.prepared_by','maker.name as maker_name','started.id as started_id',
                'evidence.id as evidence_id','evidence.external_reference','evidence.settlement_date',
                'settled.id as settled_id','confirmer.name as confirmer_name',
            ])->withQueryString()->through(function ($row) use ($actor, $prepare, $confirm) {
                $row->status = $row->settled_id ? 'SETTLED' : ($row->started_id ? 'IN_PROGRESS' : 'OUTSTANDING');
                $row->can_start = $confirm && (int) $row->prepared_by !== (int) $actor->id && ! $row->started_id;
                $row->can_evidence = $prepare && (int) $row->prepared_by === (int) $actor->id && $row->started_id && ! $row->evidence_id;
                $row->can_confirm = $confirm && (int) $row->prepared_by !== (int) $actor->id && $row->evidence_id && ! $row->settled_id;
                unset($row->prepared_by, $row->started_id, $row->evidence_id, $row->settled_id);
                return $row;
            });
        $today = now('Asia/Kuala_Lumpur')->toDateString();
        return Inertia::render('leave/Settlements', [
            'access' => AnnualLeaveWorkflowController::capabilities($actor), 'canPrepare' => $prepare,
            'status' => $status, 'rows' => $rows, 'today' => $today,
            'counts' => [
                'OUTSTANDING' => (clone $query)->whereNull('started.id')->count(),
                'IN_PROGRESS' => (clone $query)->whereNotNull('started.id')->whereNull('settled.id')->count(),
                'SETTLED' => (clone $query)->whereNotNull('settled.id')->count(),
            ],
            'employees' => $prepare ? DB::table('employees as e')->where('e.service_end', '<', $today)
                ->where('e.service_end', '>=', substr($today, 0, 4).'-01-01')
                ->where(fn ($q) => $q->whereNull('e.user_id')->orWhere('e.user_id', '<>', $actor->id))
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('leave_exit_settlements as s')->whereColumn('s.employee_id', 'e.id')->whereColumn('s.exit_date', 'e.service_end'))
                ->orderBy('e.display_name')->get(['e.id','e.display_name','e.employee_no','e.service_end']) : [],
        ]);
    }

    public function store(Request $request, LeaveExitSettlementService $service): RedirectResponse
    {
        $data = $request->validate([
            'employee' => ['required','integer','min:1'], 'key' => ['required','uuid'],
            'reference' => ['required','string','max:120'], 'reason' => ['required','string','max:1000'],
        ]);
        try {
            $service->prepare($request->user()->id, (int) $data['employee'], $data['key'], $data['reference'], $data['reason']);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['employee' => $e->getMessage()]);
        }
        return back()->with('success', 'Kes disediakan. Baki belum berubah.');
    }

    public function start(Request $request, int $settlement, LeaveExitSettlementService $service): RedirectResponse
    {
        return $this->transition($request, $settlement, $service, 'STARTED');
    }

    public function evidence(Request $request, int $settlement, LeaveExitSettlementService $service): RedirectResponse
    {
        return $this->transition($request, $settlement, $service, 'EVIDENCE');
    }

    public function confirm(Request $request, int $settlement, LeaveExitSettlementService $service): RedirectResponse
    {
        return $this->transition($request, $settlement, $service, 'SETTLED');
    }

    private function transition(Request $request, int $id, LeaveExitSettlementService $service, string $event): RedirectResponse
    {
        $rules = ['key' => ['required','uuid'], 'note' => ['required','string','max:1000']];
        if ($event === 'EVIDENCE') {
            $rules += ['reference' => ['required','string','max:120'], 'date' => ['required','date_format:Y-m-d']];
        }
        $data = $request->validate($rules);
        try {
            $service->transition($request->user()->id, $id, $event, $data['key'], $data['note'], $data['reference'] ?? null, $data['date'] ?? null);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['note' => $e->getMessage()]);
        }
        return back()->with('success', 'Tindakan penyelesaian direkodkan.');
    }
}
