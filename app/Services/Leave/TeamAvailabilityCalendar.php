<?php

namespace App\Services\Leave;

use App\Models\AuditEvent;
use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Internal local/testing-only approved absence projection. */
class TeamAvailabilityCalendar
{
    /** @return list<array{name:string,date:string,session:string}> */
    public function forCurrentUnitMonth(int $viewerUserId, string $month): array
    {
        $this->assertLocal();
        [$firstDate, $lastDate] = $this->monthBounds($month);
        if ($viewerUserId < 1) {
            throw new DomainException('An active linked staff account is required to view team availability.');
        }

        return DB::transaction(function () use ($viewerUserId, $month, $firstDate, $lastDate): array {
            $viewer = DB::table('users')->where('id', $viewerUserId)->lockForUpdate()->first(['status', 'username']);
            $user = User::query()->find($viewerUserId);
            if ($viewer === null || $viewer->status !== 'ACTIVE' || $viewer->username === null
                || $user === null || $user->status !== 'ACTIVE' || $user->username === null
                || ! DB::table('permissions')->where('name', 'leave.calendar.team.view')->where('guard_name', 'web')->exists()
                || ! $user->hasPermissionTo('leave.calendar.team.view', 'web')) {
                throw new DomainException('An active account with explicit team-calendar access is required.');
            }

            $today = now('Asia/Kuala_Lumpur')->toDateString();
            $employee = DB::table('employees')->where('user_id', $viewerUserId)
                ->where('service_start', '<=', $today)
                ->where(fn ($query) => $query->whereNull('service_end')->orWhere('service_end', '>=', $today))
                ->lockForUpdate()->first(['id']);
            if ($employee === null) {
                throw new DomainException('A currently employed linked staff record is required to view team availability.');
            }

            $assignmentQuery = DB::table('employee_assignments')->where('employee_id', $employee->id)
                ->where('effective_from', '<=', $today)
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $today));
            $assignments = (clone $assignmentQuery)->orderBy('id')->lockForUpdate()->get(['unit_id']);
            if ($assignments->count() !== 1) {
                throw new DomainException('Exactly one current unit assignment is required to scope team availability.');
            }
            $unitId = (int) $assignments->first()->unit_id;
            $unit = DB::table('organization_units')->where('id', $unitId)->lockForUpdate()->first(['active']);
            if ($unit === null || ! (bool) $unit->active) {
                throw new DomainException('An active current unit is required to scope team availability.');
            }

            // Read the peer assignments and absence rows from one database snapshot.
            $memberIds = $this->currentUnitMemberIds($unitId, $today);

            /** @var Collection<int, object{display_name:string,leave_date:string,session_code:string}> $rows */
            $rows = $memberIds === [] ? collect() : DB::table('leave_requests as request')
                ->join('employees as employee', 'employee.id', '=', 'request.employee_id')
                ->join('leave_request_revisions as revision', function ($join): void {
                    $join->on('revision.request_id', '=', 'request.id')
                        ->on('revision.revision', '=', 'request.current_revision');
                })
                ->join('leave_request_days as request_day', 'request_day.revision_id', '=', 'revision.id')
                ->join('leave_reservations as reservation', 'reservation.id', '=', 'revision.reservation_id')
                ->join('leave_active_slots as slot', function ($join): void {
                    $join->on('slot.reservation_id', '=', 'reservation.id')
                        ->on('slot.employee_id', '=', 'request.employee_id')
                        ->on('slot.leave_date', '=', 'request_day.leave_date')
                        ->on('slot.session_code', '=', 'request_day.session_code');
                })
                ->whereIn('request.employee_id', $memberIds)
                ->whereIn('request.status', ['APPROVED', 'WAITING_CANCELLATION'])
                ->where('reservation.status', 'COMMITTED')
                ->where('slot.status', 'COMMITTED')
                ->whereBetween('request_day.leave_date', [$firstDate, $lastDate])
                ->orderBy('request_day.leave_date')->orderBy('employee.display_name')->orderBy('request_day.session_code')
                ->get(['employee.display_name', 'request_day.leave_date', 'request_day.session_code']);

            AuditEvent::query()->create([
                'occurred_at' => now(),
                'actor_id' => $viewerUserId,
                'action_code' => 'hr.leave.unit_calendar_viewed',
                'target_ref' => 'organization_unit:'.$unitId.'/leave_calendar:'.$month,
                'outcome' => 'SUCCESS',
                'metadata' => ['source' => 'team_availability_calendar'],
            ]);

            return array_values($rows->map(fn (object $row): array => [
                'name' => (string) $row->display_name,
                'date' => (string) $row->leave_date,
                'session' => (string) $row->session_code,
            ])->all());
        }, 3);
    }

    /** @return list<int> */
    private function currentUnitMemberIds(int $unitId, string $today): array
    {
        $query = DB::table('employee_assignments as assignment')
            ->join('employees as employee', 'employee.id', '=', 'assignment.employee_id')
            ->where('assignment.effective_from', '<=', $today)
            ->where(fn ($builder) => $builder->whereNull('assignment.effective_to')->orWhere('assignment.effective_to', '>=', $today))
            ->where('employee.service_start', '<=', $today)
            ->where(fn ($builder) => $builder->whereNull('employee.service_end')->orWhere('employee.service_end', '>=', $today));

        return array_values($query->groupBy('employee.id')->havingRaw('COUNT(assignment.id) = 1 AND MIN(assignment.unit_id) = ?', [$unitId])->orderBy('employee.id')
            ->pluck('employee.id')->map(fn (mixed $id): int => (int) $id)->all());
    }

    /** @return array{string,string} */
    private function monthBounds(string $month): array
    {
        if (preg_match('/\A\d{4}-(0[1-9]|1[0-2])\z/D', $month) !== 1) {
            throw new DomainException('A valid calendar month in YYYY-MM form is required.');
        }
        [$year, $monthNumber] = array_map('intval', explode('-', $month));
        if ($year < 1000 || ! checkdate($monthNumber, 1, $year)) {
            throw new DomainException('A supported calendar month in YYYY-MM form is required.');
        }
        $first = DateTimeImmutable::createFromFormat('!Y-m-d', $month.'-01', new DateTimeZone('Asia/Kuala_Lumpur'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($first === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new DomainException('A valid calendar month in YYYY-MM form is required.');
        }

        return [$first->format('Y-m-d'), $first->modify('last day of this month')->format('Y-m-d')];
    }

    private function assertLocal(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw ValidationException::withMessages(['leave' => 'Team availability is unavailable outside local development and testing.']);
        }
    }
}
