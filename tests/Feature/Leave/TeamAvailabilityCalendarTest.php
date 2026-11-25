<?php

namespace Tests\Feature\Leave;

use App\Models\User;
use App\Services\Leave\LeaveRequestWorkflowService;
use App\Services\Leave\TeamAvailabilityCalendar;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeamAvailabilityCalendarTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-06T09:00:00+08:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_calendar_returns_only_approved_current_unit_slots_and_hides_partial_refunds(): void
    {
        $viewer = $this->user('calendar-viewer');
        $unitId = DB::table('organization_units')->insertGetId([
            'code' => 'calendar-unit-'.Str::lower(Str::random(8)),
            'name' => 'Synthetic unit',
            'active' => true,
        ]);
        $categoryId = DB::table('employment_categories')->insertGetId([
            'code' => 'calendar-category-'.Str::lower(Str::random(8)),
            'name' => 'Synthetic category',
            'active' => true,
        ]);
        $this->employee($viewer, $unitId, $categoryId, 'Synthetic viewer');
        $this->confirmedCalendar($viewer->id);
        $viewer->givePermissionTo('leave.calendar.team.view');

        $peer = $this->user('calendar-peer');
        [$peerEmployeeId, $peerAnnualId, $peerTabungId] = $this->employeeWithAccounts($peer, $unitId, $categoryId, 'Synthetic peer');
        $pendingPeer = $this->user('calendar-pending');
        [, $pendingAnnualId, $pendingTabungId] = $this->employeeWithAccounts($pendingPeer, $unitId, $categoryId, 'Synthetic pending peer');
        $otherUnitId = DB::table('organization_units')->insertGetId([
            'code' => 'calendar-other-unit-'.Str::lower(Str::random(8)),
            'name' => 'Synthetic other unit',
            'active' => true,
        ]);
        $otherPeer = $this->user('calendar-other');
        [, $otherAnnualId, $otherTabungId] = $this->employeeWithAccounts($otherPeer, $otherUnitId, $categoryId, 'Synthetic other peer');

        $pu = $this->user('calendar-pu');
        $otherPu = $this->user('calendar-other-pu');
        $gm = $this->user('calendar-gm');
        $this->assignOffice($pu, 'PU', $unitId);
        $this->assignOffice($otherPu, 'PU', $otherUnitId);
        $this->assignOffice($gm, 'PB');

        $workflow = app(LeaveRequestWorkflowService::class);
        $peerRequest = $this->submitAndApprove($workflow, $peer->id, $peerAnnualId, $peerTabungId, $pu->id, $gm->id,
            'approved-peer-'.Str::lower(Str::random(8)), [
                ['date' => '2026-03-06', 'session' => 'AM'],
                ['date' => '2026-03-09', 'session' => 'AM'],
            ]);
        Carbon::setTestNow('2026-03-07T09:00:00+08:00');
        $hrReviewer = $this->user('calendar-hr');
        $hrReviewer->givePermissionTo('leave.partial-cancellation.review');
        $partial = $workflow->requestApprovedAnnualLeavePartialCancellation(
            $peer->id,
            $peerRequest['request_id'],
            'calendar-partial-request-'.Str::lower(Str::random(8)),
            'Synthetic early return request',
        );
        $workflow->verifyApprovedAnnualLeavePartialCancellation(
            $hrReviewer->id,
            $peerRequest['request_id'],
            $partial['partial_cancellation_id'],
            'calendar-partial-hr-'.Str::lower(Str::random(8)),
            '2026-03-07',
            'Synthetic verified return',
        );
        $workflow->decideApprovedAnnualLeavePartialCancellation(
            $gm->id,
            $peerRequest['request_id'],
            $partial['partial_cancellation_id'],
            'calendar-partial-gm-'.Str::lower(Str::random(8)),
            'CONFIRM',
        );

        $this->submitPending($workflow, $pendingPeer->id, $pendingAnnualId, $pendingTabungId,
            'pending-peer-'.Str::lower(Str::random(8)), [['date' => '2026-03-10', 'session' => 'AM']]);
        $this->submitAndApprove($workflow, $otherPeer->id, $otherAnnualId, $otherTabungId, $otherPu->id, $gm->id,
            'approved-other-'.Str::lower(Str::random(8)), [['date' => '2026-03-12', 'session' => 'PM']]);

        $rows = app(TeamAvailabilityCalendar::class)->forCurrentUnitMonth($viewer->id, '2026-03');

        $this->assertSame([['name' => 'Synthetic peer', 'date' => '2026-03-06', 'session' => 'AM']], $rows);
        $this->assertSame(1, DB::table('audit_events')->where('actor_id', $viewer->id)
            ->where('action_code', 'hr.leave.unit_calendar_viewed')
            ->where('target_ref', 'organization_unit:'.$unitId.'/leave_calendar:2026-03')->count());
        $this->assertSame(1, DB::table('leave_active_slots')->where('employee_id', $peerEmployeeId)
            ->where('leave_date', '2026-03-09')->where('status', 'RELEASED')->count());
    }

    public function test_calendar_denies_accounts_without_permission_and_rejects_invalid_months(): void
    {
        $viewer = $this->user('calendar-denied');
        $unitId = DB::table('organization_units')->insertGetId([
            'code' => 'calendar-denied-unit-'.Str::lower(Str::random(8)),
            'name' => 'Synthetic denied unit',
            'active' => true,
        ]);
        $categoryId = DB::table('employment_categories')->insertGetId([
            'code' => 'calendar-denied-category-'.Str::lower(Str::random(8)),
            'name' => 'Synthetic denied category',
            'active' => true,
        ]);
        $this->employee($viewer, $unitId, $categoryId, 'Synthetic denied viewer');
        $this->confirmedCalendar($viewer->id);
        $calendar = app(TeamAvailabilityCalendar::class);

        $this->assertDomainException(fn () => $calendar->forCurrentUnitMonth($viewer->id, '2026-03'));
        $viewer->givePermissionTo('leave.calendar.team.view');
        $this->assertDomainException(fn () => $calendar->forCurrentUnitMonth($viewer->id, '2026-13'));
        $this->assertSame(0, DB::table('audit_events')->where('action_code', 'hr.leave.unit_calendar_viewed')->count());
    }

    private function submitAndApprove(LeaveRequestWorkflowService $workflow, int $userId, int $annualId, int $tabungId, int $puId, int $gmId, string $key, array $slots): array
    {
        $submitted = $this->submitPending($workflow, $userId, $annualId, $tabungId, $key, $slots);
        $workflow->decide($puId, $submitted['request_id'], $key.'-support', 'SUPPORT');
        $workflow->decide($gmId, $submitted['request_id'], $key.'-approve', 'APPROVE');

        return $submitted;
    }

    private function submitPending(LeaveRequestWorkflowService $workflow, int $userId, int $annualId, int $tabungId, string $key, array $slots): array
    {
        return $workflow->submit($userId, $annualId, $tabungId, $key, 'AL', $slots, 'Synthetic short-notice reason');
    }

    private function confirmedCalendar(int $actorId): void
    {
        DB::table('leave_calendar_versions')->insert([
            'label' => 'Synthetic unit calendar '.Str::lower(Str::random(8)),
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'confirmed_by' => $actorId,
            'confirmed_at' => now(),
        ]);
        DB::table('leave_policy_versions')->insert([
            'label' => 'Synthetic unit schedule '.Str::lower(Str::random(8)),
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'working_weekdays' => '[1,2,3,4,5]',
            'work_start' => '08:00',
            'session_boundary' => '13:00',
            'work_end' => '17:00',
            'schedule_simulated_by' => $actorId,
            'schedule_simulated_at' => now(),
        ]);
    }

    private function employee(User $user, int $unitId, int $categoryId, string $displayName): int
    {
        $employeeId = DB::table('employees')->insertGetId([
            'employee_no' => 'calendar-'.Str::lower(Str::random(8)),
            'display_name' => $displayName,
            'user_id' => $user->id,
            'service_start' => '2020-01-01',
        ]);
        DB::table('employee_assignments')->insert([
            'employee_id' => $employeeId,
            'unit_id' => $unitId,
            'category_id' => $categoryId,
            'effective_from' => '2020-01-01',
        ]);

        return $employeeId;
    }

    /** @return array{int,int,int} */
    private function employeeWithAccounts(User $user, int $unitId, int $categoryId, string $displayName): array
    {
        $employeeId = $this->employee($user, $unitId, $categoryId, $displayName);
        $annualId = DB::table('leave_annual_accounts')->insertGetId(['employee_id' => $employeeId, 'leave_year' => 2026]);
        $tabungId = DB::table('leave_tabung_accounts')->insertGetId(['employee_id' => $employeeId]);

        return [$employeeId, $annualId, $tabungId];
    }

    private function user(string $label): User
    {
        return User::factory()->create([
            'username' => $label.'-'.Str::lower(Str::random(8)),
            'email' => null,
            'status' => 'ACTIVE',
        ]);
    }

    private function assignOffice(User $user, string $office, ?int $unitId = null): void
    {
        DB::table('approval_assignments')->insert([
            'user_id' => $user->id,
            'office_code' => $office,
            'unit_id' => $unitId,
            'effective_from' => '2020-01-01',
        ]);
    }

    private function assertDomainException(callable $operation): void
    {
        try {
            $operation();
        } catch (DomainException) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail('An unauthorized or invalid team-calendar query must fail closed.');
    }
}
