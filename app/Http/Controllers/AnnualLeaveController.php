<?php

namespace App\Http\Controllers;

use App\Services\Leave\AnnualEntitlementCreditService;
use App\Services\Leave\BalanceProjection;
use App\Services\Leave\LedgerPostedTotals;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AnnualLeaveController extends Controller
{
    public function __invoke(Request $request, AnnualLeaveWorkflowController $workflow, LedgerPostedTotals $totals, BalanceProjection $projection): Response
    {
        $user = $request->user();
        $access = AnnualLeaveWorkflowController::capabilities($user);
        abort_unless($access['module'], 403);

        $sections = $access['sections'];
        $canView = in_array('staff', $sections, true);
        $workflowSections = array_values(array_diff($sections, ['staff']));
        $section = $request->query('section');
        abort_if($section !== null && (! is_string($section) || ! in_array($section, $sections, true)), 403);
        $section ??= $request->query('view') === 'workflow'
            ? ($workflowSections[0] ?? ($canView ? 'staff' : null))
            : ($canView ? 'staff' : ($workflowSections[0] ?? null));

        if ($section !== 'staff') {
            return Inertia::render('leave/AnnualLeave', [
                'access' => $access,
                'section' => $section,
                'workspace' => $section === null ? null : $workflow->workspace($user->id, $totals, $projection, $access),
            ]);
        }

        $today = now('Asia/Kuala_Lumpur')->toDateString();
        $currentYear = (int) substr($today, 0, 4);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:'.($currentYear + 1)],
        ]);
        $search = trim($filters['search'] ?? '');
        $year = (int) ($filters['year'] ?? $currentYear);

        $employees = DB::table('employees')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('employee_no', 'like', '%'.$search.'%')
                ->orWhere('display_name', 'like', '%'.$search.'%')))
            ->orderBy('display_name')->orderBy('id')
            ->paginate(20, ['id', 'employee_no', 'display_name', 'service_start', 'confirmation_date', 'service_end'])
            ->appends(['search' => $search, 'year' => $year]);
        $ids = $employees->getCollection()->pluck('id');
        $assignments = DB::table('employee_assignments')
            ->join('organization_units', 'organization_units.id', '=', 'employee_assignments.unit_id')
            ->join('employment_categories', 'employment_categories.id', '=', 'employee_assignments.category_id')
            ->whereIn('employee_assignments.employee_id', $ids)
            ->where('effective_from', '<=', $today)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
            ->get(['employee_assignments.employee_id', 'organization_units.name as unit', 'employment_categories.name as category'])
            ->groupBy('employee_id');
        $accounts = DB::table('leave_annual_accounts as annual')
            ->leftJoin('leave_annual_entitlement_confirmations as confirmation', function ($join): void {
                $join->on('confirmation.annual_account_id', '=', 'annual.id')
                    ->on('confirmation.employee_id', '=', 'annual.employee_id')
                    ->on('confirmation.leave_year', '=', 'annual.leave_year');
            })
            ->whereIn('annual.employee_id', $ids)->where('annual.leave_year', $year)
            ->select(['annual.employee_id', 'confirmation.confirmed_half_day_units', 'confirmation.created_at as confirmed_at'])
            ->selectRaw("EXISTS (SELECT 1 FROM leave_ledger_entries AS entry WHERE entry.annual_account_id = annual.id AND entry.kind = 'annual_entitlement_credit' AND entry.signed_half_day_units = confirmation.confirmed_half_day_units) AS credited")
            ->get()->keyBy('employee_id');

        $employees->through(function ($employee) use ($assignments, $accounts, $today): array {
            $inService = $employee->service_start <= $today
                && ($employee->service_end === null || $employee->service_end >= $today);
            $current = $assignments->get($employee->id, collect());
            $assignment = $inService && $current->count() === 1 ? $current->first() : null;
            $account = $accounts->get($employee->id);
            $units = $account?->confirmed_half_day_units;

            return [
                'id' => (int) $employee->id,
                'employee_no' => $employee->employee_no,
                'display_name' => $employee->display_name,
                'service_start' => $employee->service_start,
                'confirmation_date' => $employee->confirmation_date,
                'service_end' => $employee->service_end,
                'employment_status' => $employee->service_start > $today ? 'FUTURE' : ($inService ? 'ACTIVE' : 'ENDED'),
                'assignment_status' => ! $inService ? 'NOT_APPLICABLE' : ($assignment !== null ? 'CURRENT' : 'REVIEW'),
                'unit' => $assignment?->unit,
                'category' => $assignment?->category,
                'confirmed_half_day_units' => $units === null ? null : (int) $units,
                'entitlement_status' => $account === null ? 'NO_ACCOUNT' : ($units === null ? 'UNCONFIRMED' : ($account->credited ? 'CREDITED' : 'CONFIRMED')),
                'confirmed_at' => $units === null ? null : $account->confirmed_at,
            ];
        });

        return Inertia::render('leave/Entitlements', [
            'employees' => $employees,
            'filters' => ['search' => $search, 'year' => $year],
            'currentYear' => $currentYear,
            'asOf' => $today,
            'access' => $access,
            'canCreditEntitlement' => $today === sprintf('%04d-01-01', $year) && $user->can('leave.entitlement.credit'),
            'canConfirmEntitlement' => $year >= $currentYear && $user->can('leave.entitlement.confirm'),
            'canManageEmployees' => $user->can('hr.employees.view'),
        ]);
    }

    public function previewNewHireProrata(Request $request, int $employee): JsonResponse
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:1900', 'max:'.((int) now('Asia/Kuala_Lumpur')->format('Y') + 1)],
            'annual_units' => ['required', 'integer', 'min:1'],
        ]);
        $record = DB::table('employees')->where('id', $employee)->first(['service_start', 'service_end']);
        abort_if($record === null, 404);

        $year = (int) $data['year'];
        if ((int) substr((string) $record->service_start, 0, 4) !== $year
            || ($record->service_end !== null && $record->service_end < sprintf('%04d-12-31', $year))) {
            throw ValidationException::withMessages(['year' => 'Pratonton ini untuk tahun mula berkhidmat sehingga 31 Disember.']);
        }

        $months = 13 - (int) substr($record->service_start, 5, 2);
        $annualUnits = (int) $data['annual_units'];
        $remainder = ($annualUnits % 12) * $months;
        if ($remainder % 12 === 6) {
            throw ValidationException::withMessages(['annual_units' => 'Hasil tepat di antara dua unit separuh hari memerlukan keputusan pembundaran HR.']);
        }

        return response()->json([
            'eligible_months' => $months,
            'prorated_half_day_units' => intdiv($annualUnits, 12) * $months + intdiv($remainder + 6, 12),
        ], headers: ['Cache-Control' => 'private, no-store']);
    }

    public function credit(Request $request, int $employee, AnnualEntitlementCreditService $entitlements): RedirectResponse
    {
        $today = now('Asia/Kuala_Lumpur');
        if ($today->format('m-d') !== '01-01') {
            throw ValidationException::withMessages(['credit' => 'Kelayakan hanya boleh dikreditkan pada 1 Januari.']);
        }

        $confirmationId = DB::table('leave_annual_entitlement_confirmations')
            ->where('employee_id', $employee)->where('leave_year', (int) $today->format('Y'))->value('id');
        abort_if($confirmationId === null, 404);

        try {
            $entitlements->creditConfirmedEntitlementOnJanuaryFirst($request->user()->id, $confirmationId);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['credit' => $exception->getMessage()]);
        }

        return back();
    }

    public function confirm(Request $request, int $employee, AnnualEntitlementCreditService $entitlements): RedirectResponse
    {
        $currentYear = (int) now('Asia/Kuala_Lumpur')->format('Y');
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:'.$currentYear, 'max:'.($currentYear + 1)],
            'units' => ['required', 'integer', 'min:1'],
            'reference' => ['required', 'string', 'max:120'],
            'key' => ['required', 'uuid'],
        ]);
        $actor = $request->user()->id;
        $year = (int) $data['year'];
        try {
            DB::transaction(function () use ($employee, $year, $data, $actor, $entitlements): void {
                abort_unless(DB::table('employees')->where('id', $employee)->lockForUpdate()->first(['id']) !== null, 404);
                $accounts = DB::table('leave_annual_accounts');
                $annualId = (clone $accounts)->where('employee_id', $employee)->where('leave_year', $year)->value('id')
                    ?? $accounts->insertGetId(['employee_id' => $employee, 'leave_year' => $year]);
                $tabung = DB::table('leave_tabung_accounts');
                if (! (clone $tabung)->where('employee_id', $employee)->exists()) {
                    $tabung->insert(['employee_id' => $employee]);
                }

                $entitlements->confirmAnnualEntitlement($actor, $annualId, (int) $data['units'], $data['reference'], $data['key']);
            }, 3);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['units' => $exception->getMessage()]);
        }

        return back();
    }
}
