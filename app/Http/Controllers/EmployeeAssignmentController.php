<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeAssignmentController extends Controller
{
    public function store(Request $request, int $employeeId): RedirectResponse
    {
        $data = $request->validate([
            'unit_id' => ['required', 'integer'],
            'category_id' => ['required', 'integer'],
            'effective_from' => ['required', 'date_format:Y-m-d'],
        ]);

        DB::transaction(function () use ($request, $employeeId, $data): void {
            $employee = DB::table('employees')->where('id', $employeeId)->lockForUpdate()->first();
            abort_if($employee === null, 404);
            if ($data['effective_from'] < $employee->service_start || ($employee->service_end !== null && $data['effective_from'] > $employee->service_end)) {
                throw ValidationException::withMessages(['effective_from' => 'Tarikh mesti dalam tempoh perkhidmatan.']);
            }
            $unit = DB::table('organization_units')->where('id', $data['unit_id'])->lockForUpdate()->first();
            $category = DB::table('employment_categories')->where('id', $data['category_id'])->lockForUpdate()->first();
            if (! $unit || ! $unit->active || ! $category || ! $category->active) {
                throw ValidationException::withMessages(['unit_id' => 'Pilih unit dan kategori aktif.']);
            }
            $assignments = DB::table('employee_assignments')->where('employee_id', $employeeId)->orderBy('effective_from')->lockForUpdate()->get();
            $latest = $assignments->last();
            if ($latest === null || $latest->effective_from >= $data['effective_from']) {
                throw ValidationException::withMessages(['effective_from' => 'Tarikh mesti selepas penempatan terkini.']);
            }
            foreach ($assignments as $assignment) {
                if ($assignment->id !== $latest->id && ($assignment->effective_to === null || $assignment->effective_to >= $data['effective_from'])) {
                    throw ValidationException::withMessages(['effective_from' => 'Sejarah penempatan bertindih dan perlu disemak.']);
                }
            }
            if ($latest->effective_to !== null && $latest->effective_to >= $data['effective_from']) {
                throw ValidationException::withMessages(['effective_from' => 'Tarikh bertindih dengan penempatan sedia ada.']);
            }
            $now = now();
            if ($latest->effective_to === null) {
                DB::table('employee_assignments')->where('id', $latest->id)->update([
                    'effective_to' => CarbonImmutable::parse($data['effective_from'])->subDay()->toDateString(),
                    'updated_at' => $now,
                ]);
            }
            DB::table('employee_assignments')->insert([
                'employee_id' => $employeeId, 'unit_id' => $unit->id, 'category_id' => $category->id,
                'effective_from' => $data['effective_from'], 'effective_to' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
            AuditEvent::query()->create([
                'occurred_at' => $now, 'actor_id' => $request->user()->getKey(),
                'action_code' => 'hr.assignment.created', 'target_ref' => 'employee:'.$employeeId,
                'outcome' => 'SUCCESS', 'metadata' => ['source' => 'employee_ui', 'changed_fields' => ['assignment']],
            ]);
        }, 3);

        return to_route('employees.index')->with('success', 'Penempatan direkodkan.');
    }
}
