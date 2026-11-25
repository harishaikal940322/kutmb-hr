<?php

namespace App\Services\Leave;

use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

/** Resolves a current target-scoped route while the caller's transaction and guard locks are held. */
class LeaveApprovalAssignmentResolver
{
    public function __construct(private readonly LeaveApprovalRouteResolver $routeResolver) {}

    /**
     * @param  array<array-key, mixed>  $slots
     * @return list<array{step_order:int,stage_code:string,office_code:string,assignment_id:int,assignee_user_id:int,unit_id:?int,is_final:bool}>
     */
    public function resolveForEmployee(int $requesterUserId, int $employeeId, int $requestedHalfDayUnits, array $slots): array
    {
        $this->assertLocal();
        if (DB::transactionLevel() < 1) {
            throw new RuntimeException('Approval assignments must be resolved inside the request transaction.');
        }
        if ($requesterUserId < 1 || $employeeId < 1 || $requestedHalfDayUnits < 1 || $slots === []) {
            throw new DomainException('A linked employee, requester, leave duration, and date set are required.');
        }
        if (! array_is_list($slots) || count($slots) !== $requestedHalfDayUnits) {
            throw new DomainException('The route threshold must match the requested half-day session count.');
        }

        $seenSlots = [];
        $validatedSlots = [];
        foreach ($slots as $slot) {
            if (! is_array($slot) || ! isset($slot['date'], $slot['session'])
                || ! is_string($slot['date']) || ! is_string($slot['session'])
                || ! in_array($slot['session'], ['AM', 'PM'], true)) {
                throw new DomainException('Each route date must contain one valid AM or PM session.');
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $slot['date'], new DateTimeZone('Asia/Kuala_Lumpur'));
            if ($date === false || $date->format('Y-m-d') !== $slot['date']) {
                throw new DomainException('Each route date must be a valid calendar date.');
            }
            $key = $slot['date'].':'.$slot['session'];
            if (isset($seenSlots[$key])) {
                throw new DomainException('A route cannot contain a duplicate half-day session.');
            }
            $seenSlots[$key] = true;
            $validatedSlots[] = ['date' => $slot['date'], 'session' => $slot['session']];
        }

        $this->lockGuards();
        $employee = DB::table('employees')->where('id', $employeeId)->lockForUpdate()->first();
        $requester = DB::table('users')->where('id', $requesterUserId)->lockForUpdate()->first();
        if ($employee === null || (int) $employee->user_id !== $requesterUserId
            || $requester === null || $requester->status !== 'ACTIVE' || $requester->username === null) {
            throw new DomainException('Only the linked active employee can resolve a leave approval route.');
        }

        $dates = array_values(array_unique(array_column($validatedSlots, 'date')));
        sort($dates);
        $unitIds = [];
        foreach ($dates as $date) {
            if ($date < $employee->service_start || ($employee->service_end !== null && $date > $employee->service_end)) {
                throw new DomainException('The requested leave date is outside the employee service period.');
            }
            $assignments = DB::table('employee_assignments')->where('employee_id', $employeeId)
                ->where('effective_from', '<=', $date)
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
                ->lockForUpdate()->get(['id', 'unit_id']);
            if ($assignments->count() !== 1) {
                throw new DomainException('Exactly one effective employee unit is required for each requested leave date.');
            }
            $unitIds[(int) $assignments[0]->unit_id] = true;
        }
        if (count($unitIds) !== 1) {
            throw new DomainException('A request spanning employee units requires HR review before route resolution.');
        }
        $unitId = (int) array_key_first($unitIds);

        $today = now('Asia/Kuala_Lumpur')->toDateString();
        $applicantOffices = DB::table('approval_assignments')->where('user_id', $requesterUserId)
            ->whereIn('office_code', ['PU', 'PB'])->where('effective_from', '<=', $today)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
            ->orderBy('office_code')->lockForUpdate()->get(['id', 'office_code', 'unit_id']);
        $applicantGeneralManager = $applicantOffices->firstWhere('office_code', 'PB');
        $applicantUnitOffices = $applicantOffices->where('office_code', 'PU')->values();

        if ($applicantGeneralManager !== null) {
            $applicantOffice = 'PB';
        } elseif ($applicantUnitOffices->isNotEmpty()) {
            if ($applicantUnitOffices->count() !== 1 || (int) $applicantUnitOffices[0]->unit_id !== $unitId) {
                throw new DomainException('The applicant Unit Manager assignment does not match the employee unit for every leave date.');
            }
            $applicantOffice = 'PU';
        } else {
            $applicantOffice = 'STAFF';
        }

        $candidates = DB::table('approval_assignments')->whereIn('office_code', ['PU', 'PB', 'SECRETARY_ALK'])
            ->where('effective_from', '<=', $today)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
            ->where(fn ($query) => $query->where('office_code', '!=', 'PU')->orWhere('unit_id', $unitId))
            ->orderBy('user_id')->orderBy('id')->lockForUpdate()->get(['id', 'office_code', 'unit_id', 'user_id']);
        $candidateUsers = DB::table('users')->whereIn('id', $candidates->pluck('user_id')->unique()->all())
            ->orderBy('id')->lockForUpdate()->get(['id', 'status', 'username'])->keyBy('id');
        $routeAssignments = array_values($candidates->map(fn (stdClass $assignment): array => [
            'assignment_id' => (int) $assignment->id,
            'office_code' => (string) $assignment->office_code,
            'user_id' => (int) $assignment->user_id,
            'unit_id' => $assignment->unit_id === null ? null : (int) $assignment->unit_id,
            'active' => isset($candidateUsers[(int) $assignment->user_id])
                && $candidateUsers[(int) $assignment->user_id]->status === 'ACTIVE'
                && $candidateUsers[(int) $assignment->user_id]->username !== null,
        ])->all());

        return $this->routeResolver->resolve($applicantOffice, $requesterUserId, $unitId, $requestedHalfDayUnits, $routeAssignments);
    }

    private function lockGuards(): void
    {
        foreach (['leave_calendar_confirmation_guard', 'leave_working_schedule_guard', 'office_assignment_guard'] as $table) {
            if (! DB::table($table)->where('id', 1)->lockForUpdate()->first()) {
                throw new RuntimeException('Leave calendar, schedule, and office assignment guards must be available.');
            }
        }
    }

    private function assertLocal(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Leave approval route resolution is unavailable outside local development and testing.');
        }
    }
}
