<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class EmployeeAccountLinkController extends Controller
{
    public function update(Request $request, int $employeeId): RedirectResponse
    {
        $request->merge(['reason' => is_string($request->input('reason')) ? trim($request->input('reason')) : $request->input('reason')]);
        $data = $request->validate(['user_id' => ['present', 'nullable', 'integer'], 'reason' => ['required', 'string', 'max:1000']]);

        // Account reassociation must be reviewed with leave history before leave is introduced.
        if (Schema::hasTable('leave_requests')) {
            throw ValidationException::withMessages(['user_id' => 'Perubahan pautan tidak tersedia sehingga sejarah cuti disemak.']);
        }
        try {
            DB::transaction(function () use ($request, $employeeId, $data): void {
                $employee = DB::table('employees')->where('id', $employeeId)->lockForUpdate()->first();
                abort_if($employee === null, 404);
                $actorId = (int) $request->user()->getKey();
                $newId = isset($data['user_id']) ? (int) $data['user_id'] : null;
                $oldId = $employee->user_id === null ? null : (int) $employee->user_id;
                if ($actorId === $newId || $actorId === $oldId) {
                    throw ValidationException::withMessages(['user_id' => 'Pautan sendiri memerlukan pegawai lain yang diberi kuasa.']);
                }
                if ($newId === $oldId || ($oldId !== null && $newId !== null)) {
                    throw ValidationException::withMessages(['user_id' => 'Nyahpaut akaun sedia ada sebelum membuat pautan baharu.']);
                }
                if ($newId !== null) {
                    $user = DB::table('users')->where('id', $newId)->lockForUpdate()->first();
                    if (! $user || $user->status !== 'ACTIVE' || $user->username === null) {
                        throw ValidationException::withMessages(['user_id' => 'Pilih akaun aktif yang sah.']);
                    }
                }
                DB::table('employees')->where('id', $employeeId)->update(['user_id' => $newId, 'updated_at' => now()]);
                DB::table('employee_account_link_history')->insert([
                    'employee_id' => $employeeId, 'previous_user_id' => $oldId, 'new_user_id' => $newId,
                    'actor_id' => $actorId, 'reason' => $data['reason'], 'created_at' => now(),
                ]);
                AuditEvent::query()->create([
                    'occurred_at' => now(), 'actor_id' => $actorId, 'action_code' => 'hr.employee.account_link_changed',
                    'target_ref' => 'employee:'.$employeeId, 'outcome' => 'SUCCESS',
                    'metadata' => ['source' => 'employee_ui', 'changed_fields' => ['user_id']],
                ]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['user_id' => 'Akaun sudah dipaut kepada pekerja lain.']);
        }

        return to_route('employees.index')->with('success', 'Pautan akaun dikemas kini.');
    }
}
