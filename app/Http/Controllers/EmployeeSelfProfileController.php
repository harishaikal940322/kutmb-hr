<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeSelfProfileController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $employee = DB::table('employees')->where('user_id', $request->user()->getKey())
            ->first(['id', 'employee_no', 'display_name', 'service_start', 'confirmation_date', 'service_end']);
        abort_if($employee === null, 404);
        $today = now('Asia/Kuala_Lumpur')->toDateString();
        $assignments = DB::table('employee_assignments')
            ->join('organization_units', 'organization_units.id', '=', 'employee_assignments.unit_id')
            ->join('employment_categories', 'employment_categories.id', '=', 'employee_assignments.category_id')
            ->where('employee_id', $employee->id)->where('effective_from', '<=', $today)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
            ->get(['organization_units.name as unit', 'employment_categories.name as category', 'employee_assignments.effective_from']);
        $inService = $employee->service_start <= $today && ($employee->service_end === null || $employee->service_end >= $today);
        $current = $inService && $assignments->count() === 1 ? $assignments->first() : null;

        return Inertia::render('employees/SelfProfile', [
            'employee' => $employee,
            'assignment' => $current,
        ]);
    }
}
