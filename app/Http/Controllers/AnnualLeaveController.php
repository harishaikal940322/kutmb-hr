<?php

namespace App\Http\Controllers;

use App\Services\Leave\AnnualEntitlementCreditService;
use App\Services\Leave\BalanceProjection;
use App\Services\Leave\LedgerPostedTotals;
use DomainException;
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
        $canReview = $user->can('iam.rbac.manage');
        $canView = $user->can('leave.entitlement.view');
        $canReviewPartial = $user->can('leave.partial-cancellation.review');
        $canWork = AnnualLeaveWorkflowController::hasAccess($user->id, $canReviewPartial);
        abort_unless($canReview || $canView || $canWork, 403);

        if ($canWork && ($request->query('view') === 'workflow' || (! $canView && $request->query('view') !== 'design'))) {
            return Inertia::render('leave/AnnualLeave', [
                'canViewEntitlements' => $canView,
                'workspace' => [
                    ...$workflow->workspace($user->id, $totals, $projection, $canReviewPartial),
                    'can_view_team' => $user->can('leave.calendar.team.view'),
                ],
            ]);
        }

        // Design fixtures must never be mixed with employee data or used as authorization.
        if ($request->query('view') === 'design' || ! $canView) {
            abort_unless($canReview, 403);

            return Inertia::render('leave/AnnualLeave', ['canViewEntitlements' => $canView]);
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
            'canReviewDesign' => $canReview,
            'canUseWorkflow' => $canWork,
            'canConfirmEntitlement' => $year >= $currentYear && $user->can('leave.entitlement.confirm'),
            'canManageEmployees' => $user->can('hr.employees.view'),
        ]);
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
