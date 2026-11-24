<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeFoundationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('employees/Index', [
            'employees' => DB::table('employees')->orderBy('display_name')->get(['id', 'employee_no', 'display_name', 'service_start', 'confirmation_date', 'service_end']),
            'units' => DB::table('organization_units')->where('active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'categories' => DB::table('employment_categories')->where('active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'assignments' => DB::table('employee_assignments')->join('organization_units', 'organization_units.id', '=', 'employee_assignments.unit_id')->join('employment_categories', 'employment_categories.id', '=', 'employee_assignments.category_id')->orderBy('effective_from')->get(['employee_assignments.employee_id', 'employee_assignments.effective_from', 'employee_assignments.effective_to', 'organization_units.name as unit_name', 'employment_categories.name as category_name']),
            'canAssign' => $request->user()->can('hr.assignments.manage'),
            'canCreate' => $request->user()->can('hr.employees.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'employee_no' => is_string($request->input('employee_no')) ? trim($request->input('employee_no')) : $request->input('employee_no'),
            'display_name' => is_string($request->input('display_name')) ? trim($request->input('display_name')) : $request->input('display_name'),
        ]);
        $data = $request->validate([
            'employee_no' => ['required', 'string', 'max:64', Rule::unique('employees', 'employee_no')],
            'display_name' => ['required', 'string', 'max:255'],
            'service_start' => ['required', 'date_format:Y-m-d'],
            'confirmation_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:service_start'],
            'service_end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:service_start', Rule::when($request->filled('confirmation_date'), ['after_or_equal:confirmation_date'])],
            'effective_from' => ['required', 'date_format:Y-m-d', 'after_or_equal:service_start', Rule::when($request->filled('service_end'), ['before_or_equal:service_end'])],
            'unit_id' => ['required', 'integer'],
            'category_id' => ['required', 'integer'],
        ]);

        try {
            DB::transaction(function () use ($request, $data): void {
                $unit = DB::table('organization_units')->where('id', $data['unit_id'])->lockForUpdate()->first();
                $category = DB::table('employment_categories')->where('id', $data['category_id'])->lockForUpdate()->first();
                if (! $unit || ! $unit->active || ! $category || ! $category->active) {
                    throw ValidationException::withMessages(['unit_id' => 'Pilih unit dan kategori aktif.']);
                }
                $now = now();
                $id = DB::table('employees')->insertGetId([
                    'employee_no' => $data['employee_no'],
                    'display_name' => $data['display_name'],
                    'service_start' => $data['service_start'],
                    'confirmation_date' => $data['confirmation_date'] ?? null,
                    'service_end' => $data['service_end'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('employee_assignments')->insert([
                    'employee_id' => $id,
                    'unit_id' => $unit->id,
                    'category_id' => $category->id,
                    'effective_from' => $data['effective_from'],
                    'effective_to' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                AuditEvent::query()->create([
                    'occurred_at' => $now,
                    'actor_id' => $request->user()->getKey(),
                    'action_code' => 'hr.employee.created',
                    'target_ref' => 'employee:'.$id,
                    'outcome' => 'SUCCESS',
                    'metadata' => ['source' => 'employee_ui', 'changed_fields' => ['employee', 'assignment']],
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['employee_no' => 'Nombor pekerja ini telah digunakan.']);
        }

        return to_route('employees.index')->with('success', 'Pekerja ditambah.');
    }

    public function update(Request $request, int $employeeId): RedirectResponse
    {
        $request->merge([
            'employee_no' => is_string($request->input('employee_no')) ? trim($request->input('employee_no')) : $request->input('employee_no'),
            'display_name' => is_string($request->input('display_name')) ? trim($request->input('display_name')) : $request->input('display_name'),
        ]);
        $data = $request->validate([
            'employee_no' => ['required', 'string', 'max:64', Rule::unique('employees', 'employee_no')->ignore($employeeId)],
            'display_name' => ['required', 'string', 'max:255'],
            'service_start' => ['required', 'date_format:Y-m-d'],
            'confirmation_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:service_start'],
            'service_end' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:service_start', Rule::when($request->filled('confirmation_date'), ['after_or_equal:confirmation_date'])],
        ]);
        try {
            DB::transaction(function () use ($request, $employeeId, $data): void {
                $employee = DB::table('employees')->where('id', $employeeId)->lockForUpdate()->first();
                abort_if($employee === null, 404);
                $assignments = DB::table('employee_assignments')->where('employee_id', $employeeId)->lockForUpdate()->get();
                foreach ($assignments as $assignment) {
                    if ($assignment->effective_from < $data['service_start']) {
                        throw ValidationException::withMessages(['service_start' => 'Tarikh mula tidak boleh selepas penempatan sedia ada.']);
                    }
                    $end = $data['service_end'] ?? null;
                    if ($end !== null && ($assignment->effective_from > $end || ($assignment->effective_to !== null && $assignment->effective_to > $end))) {
                        throw ValidationException::withMessages(['service_end' => 'Tarikh tamat tidak boleh mendahului sejarah penempatan.']);
                    }
                }
                DB::table('employees')->where('id', $employeeId)->update([
                    'employee_no' => $data['employee_no'], 'display_name' => $data['display_name'],
                    'service_start' => $data['service_start'], 'confirmation_date' => $data['confirmation_date'] ?? null,
                    'service_end' => $data['service_end'] ?? null, 'updated_at' => now(),
                ]);
                AuditEvent::query()->create([
                    'occurred_at' => now(), 'actor_id' => $request->user()->getKey(), 'action_code' => 'hr.employee.updated',
                    'target_ref' => 'employee:'.$employeeId, 'outcome' => 'SUCCESS',
                    'metadata' => ['source' => 'employee_ui', 'changed_fields' => ['employee']],
                ]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['employee_no' => 'Nombor pekerja ini telah digunakan.']);
        }

        return to_route('employees.index')->with('success', 'Rekod pekerja dikemas kini.');
    }
}
