<?php

namespace Database\Seeders;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Explicit, synthetic MOD-004 fixture setup for the existing local application.
 * This is not an account provisioning or HR policy mechanism for real employees.
 */
class DevelopmentEmployeeOrganizationSeeder extends Seeder
{
    private const EMPLOYEES = [
        'staff' => ['DEV-001', 'PEKERJA', 3],
        'unit-manager' => ['DEV-002', 'PENGURUS', 6],
        'general-manager' => ['DEV-003', 'PENGURUS', 8],
        'hr-executive' => ['DEV-004', 'PEKERJA', 0],
        'hr-manager' => ['DEV-005', 'PENGURUS', 5],
        'it-admin' => ['DEV-006', 'PEKERJA', 2],
    ];

    public function run(): void
    {
        if (! app()->environment('local') || config('database.default') !== 'mysql'
            || DB::connection()->getDatabaseName() !== 'kutmb_hr_dev') {
            throw new RuntimeException('Employee organization seeding requires local kutmb_hr_dev.');
        }

        DB::transaction(function (): void {
            // Serialize fixture setup with ordinary office and IAM administration.
            foreach (['office_assignment_guard', 'iam_guard'] as $guard) {
                if (DB::table($guard)->where('id', 1)->lockForUpdate()->first() === null) {
                    throw new RuntimeException('Required baseline is missing: '.$guard);
                }
            }

            $today = now('Asia/Kuala_Lumpur')->startOfDay();
            $users = [];
            foreach ([...array_keys(self::EMPLOYEES), 'alk-secretary'] as $role) {
                $username = 'dev-'.$role;
                $user = User::query()->where('username_canonical', $username)->lockForUpdate()->first();

                if ($user === null || $user->status !== 'ACTIVE' || ! $user->hasRole($role)
                    || $user->roles->contains(fn ($assigned) => (bool) $assigned->is_system)) {
                    throw new RuntimeException('An active, non-system fixture account with the expected role is required: '.$username);
                }

                // Restrict the fixture to accounts actually created by the development user seeder.
                if (! AuditEvent::query()->where('action_code', 'iam.development_user.seeded')
                    ->where('target_ref', 'user:'.$user->id)->exists()) {
                    throw new RuntimeException('Account is not a recorded development fixture: '.$username);
                }

                $users[$role] = $user;
            }

            if (DB::table('employees')->where('user_id', $users['alk-secretary']->id)->exists()) {
                throw new RuntimeException('The Secretary ALK fixture must remain a non-employee approver.');
            }

            $unit = $this->reference('organization_units', 'DEV-OPERASI', 'Unit Operasi (Demo)');
            $categories = [
                'PEKERJA' => $this->reference('employment_categories', 'DEV-PEKERJA', 'Pekerja (Demo)'),
                'PENGURUS' => $this->reference('employment_categories', 'DEV-PENGURUS', 'Pengurus (Demo)'),
            ];

            foreach (self::EMPLOYEES as $role => [$number, $category, $years]) {
                $user = $users[$role];
                $existing = DB::table('employees')->where('employee_no', $number)->lockForUpdate()->first();
                $linked = DB::table('employees')->where('user_id', $user->id)->lockForUpdate()->first();

                if ($existing !== null) {
                    if ((int) $existing->user_id !== $user->id || $linked === null || $linked->id !== $existing->id) {
                        throw new RuntimeException('Existing employee link conflicts with fixture '.$number.'; resolve it manually.');
                    }

                    // Preserve any later changes to service dates, names, units or category.
                    continue;
                }
                if ($linked !== null) {
                    throw new RuntimeException('Account is already linked to another employee: '.$user->username);
                }

                $start = $years === 0
                    ? $today->copy()->startOfYear()->addDays(14)->min($today)
                    : $today->copy()->subYearsNoOverflow($years)->startOfYear();
                $confirmed = $years === 0 ? null : $start->copy()->addMonthsNoOverflow(6)->toDateString();

                $employeeId = DB::table('employees')->insertGetId([
                    'employee_no' => $number,
                    'display_name' => $user->name,
                    'user_id' => $user->id,
                    'service_start' => $start->toDateString(),
                    'confirmation_date' => $confirmed,
                    'service_end' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('employee_assignments')->insert([
                    'employee_id' => $employeeId,
                    'unit_id' => $unit,
                    'category_id' => $categories[$category],
                    'effective_from' => $start->toDateString(),
                    'effective_to' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // An initial association is audited as CLI fixture creation, not a
                // fabricated HR actor's later account-link mutation.
                $this->audit('hr.employee.development_seeded', 'employee:'.$employeeId, ['employee', 'user_id', 'assignment']);
            }

            $effectiveFrom = $today->copy()->startOfYear()->toDateString();
            $this->office($users['unit-manager'], 'PU', $unit, $effectiveFrom, $today->toDateString());
            $this->office($users['general-manager'], 'PB', null, $effectiveFrom, $today->toDateString());
            $this->office($users['alk-secretary'], 'SECRETARY_ALK', null, $effectiveFrom, $today->toDateString());
        }, 3);

        $this->command?->info('Development employee and office mapping complete. Existing records were preserved.');
    }

    private function reference(string $table, string $code, string $name): int
    {
        $record = DB::table($table)->where('code', $code)->lockForUpdate()->first();
        if ($record !== null) {
            if (! (bool) $record->active) {
                throw new RuntimeException('Existing fixture reference is inactive: '.$code);
            }

            return (int) $record->id;
        }

        $id = DB::table($table)->insertGetId([
            'code' => $code,
            'name' => $name,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->audit('hr.organization.development_seeded', $table.':'.$id, ['code', 'name', 'active']);

        return (int) $id;
    }

    private function office(User $user, string $code, ?int $unit, string $from, string $today): void
    {
        $scope = DB::table('approval_assignments')->where('office_code', $code)->where('unit_id', $unit);
        $current = (clone $scope)->where('effective_from', '<=', $today)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
            ->lockForUpdate()->get();

        if ($current->count() === 1 && (int) $current->first()->user_id === $user->id) {
            return;
        }

        // Do not overwrite, revive or overlap an existing office appointment.
        if ($current->isNotEmpty() || (clone $scope)->exists()
            || DB::table('approval_assignments')->where('user_id', $user->id)->exists()) {
            throw new RuntimeException('Existing appointment history requires manual review: '.$code);
        }

        // The employee must be assigned to the same unit as their PU office.
        if ($code === 'PU') {
            $assignments = DB::table('employee_assignments as assignment')
                ->join('employees as employee', 'employee.id', '=', 'assignment.employee_id')
                ->where('employee.user_id', $user->id)
                ->where('assignment.effective_from', '<=', $today)
                ->where(fn ($query) => $query->whereNull('assignment.effective_to')->orWhere('assignment.effective_to', '>=', $today))
                ->get(['assignment.unit_id']);
            if ($assignments->count() !== 1 || (int) $assignments->first()->unit_id !== $unit) {
                throw new RuntimeException('The PU fixture must have one current assignment in its approval unit.');
            }
        }

        if (! $user->can('leave.request.decide')) {
            throw new RuntimeException('Missing approval permission for '.$user->username.'. Run DevelopmentLeaveAccessSeeder first.');
        }

        $id = DB::table('approval_assignments')->insertGetId([
            'user_id' => $user->id,
            'office_code' => $code,
            'unit_id' => $unit,
            'effective_from' => $from,
            'effective_to' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->audit('hr.office.development_seeded', 'office_assignment:'.$id, ['office_assignment']);
    }

    private function audit(string $action, string $target, array $fields): void
    {
        AuditEvent::query()->create([
            'occurred_at' => now(),
            'actor_id' => null,
            'action_code' => $action,
            'target_ref' => $target,
            'outcome' => 'SUCCESS',
            'metadata' => ['source' => 'development_seeder', 'changed_fields' => $fields],
        ]);
    }
}
