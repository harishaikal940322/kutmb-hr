<?php

namespace Tests\Feature\Leave;

use App\Models\User;
use App\Services\Leave\LeaveRequestWorkflowService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeaveRequestWorkflowServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-02T09:00:00+08:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_submission_is_atomic_snapshots_the_route_and_replays_by_payload_key(): void
    {
        $fixture = $this->fixture('submit');
        $approvers = $this->staffApprovers($fixture);
        $service = app(LeaveRequestWorkflowService::class);
        $slots = [['date' => '2026-03-06', 'session' => 'AM'], ['date' => '2026-03-06', 'session' => 'PM']];

        $first = $service->submit($fixture['user']->id, $fixture['annual_id'], $fixture['tabung_id'], 'submit-'.$fixture['suffix'], 'AL', $slots);
        $replay = $service->submit($fixture['user']->id, $fixture['annual_id'], $fixture['tabung_id'], 'submit-'.$fixture['suffix'], 'AL', $slots);
        $revision = DB::table('leave_request_revisions')->where('request_id', $first['request_id'])->sole();

        $this->assertSame($first['request_id'], $replay['request_id']);
        $this->assertSame($first['request_key'], $replay['request_key']);
        $this->assertSame($first['status'], $replay['status']);
        $this->assertSame($first['reservation_id'], $replay['reservation_id']);
        $this->assertTrue($replay['replayed']);
        $this->assertSame('WAITING_UNIT_MANAGER_SUPPORT', $first['status']);
        $this->assertSame(2, (int) $revision->requested_units);
        $this->assertFalse((bool) $revision->is_short_notice_emergency);
        $this->assertSame(2, DB::table('leave_request_days')->where('revision_id', $revision->id)->count());
        $this->assertSame([
            [$approvers['pu']->id, 'UNIT_MANAGER_SUPPORT'],
            [$approvers['pb']->id, 'GENERAL_MANAGER_FINAL'],
        ], DB::table('leave_approval_steps')->where('revision_id', $revision->id)->orderBy('step_order')
            ->get(['assignee_user_id', 'stage_code'])->map(fn (object $step): array => [(int) $step->assignee_user_id, $step->stage_code])->all());
        $this->assertSame(1, DB::table('leave_reservations')->where('id', $first['reservation_id'])->where('status', 'ACTIVE')->count());
        $this->assertSame(1, DB::table('leave_calendar_usage_snapshots')->where('employee_id', $fixture['employee_id'])->count());
        $this->assertSame(1, DB::table('audit_events')->where('action_code', 'hr.leave.request_submitted')->where('target_ref', 'leave_request:'.$first['request_id'])->count());

        $this->assertNotSame($approvers['secretary']->id, $approvers['pb']->id);
    }

    public function test_support_and_not_support_forward_without_releasing_the_pending_hold(): void
    {
        $fixture = $this->fixture('forward');
        $approvers = $this->staffApprovers($fixture);
        $service = app(LeaveRequestWorkflowService::class);
        $submitted = $service->submit($fixture['user']->id, $fixture['annual_id'], $fixture['tabung_id'], 'forward-'.$fixture['suffix'], 'AL', [['date' => '2026-03-06', 'session' => 'AM']]);

        $first = $service->decide($approvers['pu']->id, $submitted['request_id'], 'forward-support-'.$fixture['suffix'], 'NOT_SUPPORT', 'Synthetic reason');
        $reservation = DB::table('leave_reservations')->where('id', $submitted['reservation_id'])->first();
        $this->assertSame('WAITING_GENERAL_MANAGER_FINAL', $first['status']);
        $this->assertSame('ACTIVE', $reservation->status);
        $this->assertSame(1, DB::table('leave_reservation_events')->where('reservation_id', $reservation->id)->where('event_code', 'RESERVED')->count());
        $this->assertSame(1, DB::table('leave_workflow_actions')->where('revision_id', DB::table('leave_request_revisions')->where('request_id', $submitted['request_id'])->value('id'))->count());
    }

    public function test_unit_manager_self_application_skips_unit_support_and_routes_directly_to_general_manager(): void
    {
        $fixture = $this->fixture('selfpu');
        $this->assignOffice($fixture['user'], 'PU', $fixture['unit_id']);
        $pb = $this->user('self-pb');
        $secretary = $this->user('self-secretary');
        $this->assignOffice($pb, 'PB');
        $this->assignOffice($secretary, 'SECRETARY_ALK');

        $submitted = app(LeaveRequestWorkflowService::class)->submit(
            $fixture['user']->id,
            $fixture['annual_id'],
            $fixture['tabung_id'],
            'self-pu-'.$fixture['suffix'],
            'AL',
            [['date' => '2026-03-06', 'session' => 'AM']],
        );

        $steps = DB::table('leave_approval_steps')->where('revision_id', DB::table('leave_request_revisions')->where('request_id', $submitted['request_id'])->value('id'))
            ->orderBy('step_order')->get();
        $this->assertSame('WAITING_GENERAL_MANAGER_FINAL', $submitted['status']);
        $this->assertCount(1, $steps);
        $this->assertSame('GENERAL_MANAGER_FINAL', $steps[0]->stage_code);
        $this->assertSame($pb->id, (int) $steps[0]->assignee_user_id);
        $this->assertNotSame($fixture['user']->id, (int) $steps[0]->assignee_user_id);
        $this->assertNotSame($secretary->id, (int) $steps[0]->assignee_user_id);
    }

    public function test_requests_over_five_days_persist_general_manager_support_then_secretary_final(): void
    {
        $fixture = $this->fixture('longroute');
        $approvers = $this->staffApprovers($fixture);
        $slots = [];
        $date = new \DateTimeImmutable('2026-03-10');
        while (count($slots) < 11) {
            if ((int) $date->format('N') <= 5) {
                foreach (['AM', 'PM'] as $session) {
                    if (count($slots) < 11) {
                        $slots[] = ['date' => $date->format('Y-m-d'), 'session' => $session];
                    }
                }
            }
            $date = $date->modify('+1 day');
        }

        $submitted = app(LeaveRequestWorkflowService::class)->submit(
            $fixture['user']->id,
            $fixture['annual_id'],
            $fixture['tabung_id'],
            'long-route-'.$fixture['suffix'],
            'AL',
            $slots,
        );

        $steps = DB::table('leave_approval_steps')->where('revision_id', DB::table('leave_request_revisions')->where('request_id', $submitted['request_id'])->value('id'))
            ->orderBy('step_order')->get();
        $this->assertSame('WAITING_UNIT_MANAGER_SUPPORT', $submitted['status']);
        $this->assertSame([
            ['UNIT_MANAGER_SUPPORT', $approvers['pu']->id],
            ['GENERAL_MANAGER_SUPPORT', $approvers['pb']->id],
            ['SECRETARY_FINAL', $approvers['secretary']->id],
        ], $steps->map(fn (object $step): array => [$step->stage_code, (int) $step->assignee_user_id])->all());
    }

    public function test_final_approval_commits_once_and_action_replay_does_not_duplicate_the_ledger(): void
    {
        $fixture = $this->fixture('approve');
        $approvers = $this->staffApprovers($fixture);
        $service = app(LeaveRequestWorkflowService::class);
        $submitted = $service->submit($fixture['user']->id, $fixture['annual_id'], $fixture['tabung_id'], 'approve-'.$fixture['suffix'], 'AL', [['date' => '2026-03-06', 'session' => 'AM']]);
        $service->decide($approvers['pu']->id, $submitted['request_id'], 'approve-support-'.$fixture['suffix'], 'SUPPORT');
        $key = 'approve-final-'.$fixture['suffix'];

        $approved = $service->decide($approvers['pb']->id, $submitted['request_id'], $key, 'APPROVE');
        $replay = $service->decide($approvers['pb']->id, $submitted['request_id'], $key, 'APPROVE');

        $this->assertSame('APPROVED', $approved['status']);
        $this->assertSame('APPROVED', $replay['status']);
        $this->assertTrue($replay['replayed']);
        $this->assertSame('COMMITTED', DB::table('leave_reservations')->where('id', $submitted['reservation_id'])->value('status'));
        $this->assertSame(1, DB::table('leave_ledger_entries')->where('kind', 'approved_leave_use')->count());
        $this->assertSame(2, DB::table('leave_workflow_actions')->where('revision_id', DB::table('leave_request_revisions')->where('request_id', $submitted['request_id'])->value('id'))->count());
    }

    public function test_balance_posting_failure_rolls_back_the_final_action_and_request_transition(): void
    {
        $fixture = $this->fixture('rollback');
        $approvers = $this->staffApprovers($fixture);
        $service = app(LeaveRequestWorkflowService::class);
        $submitted = $service->submit($fixture['user']->id, $fixture['annual_id'], $fixture['tabung_id'], 'rollback-'.$fixture['suffix'], 'AL', [['date' => '2026-03-06', 'session' => 'AM']]);
        $service->decide($approvers['pu']->id, $submitted['request_id'], 'rollback-support-'.$fixture['suffix'], 'SUPPORT');
        $actionKey = 'rollback-final-'.$fixture['suffix'];
        DB::table('leave_balance_operations')->insert([
            'employee_id' => $fixture['employee_id'],
            'actor_id' => $fixture['user']->id,
            'operation_key' => $actionKey.':c',
            'operation_type' => 'SYNTHETIC_CONFLICT',
            'payload_hash' => hash('sha256', 'different operation'),
            'created_at' => now(),
        ]);

        $this->assertDomainException(fn () => $service->decide($approvers['pb']->id, $submitted['request_id'], $actionKey, 'APPROVE'));

        $this->assertSame('WAITING_GENERAL_MANAGER_FINAL', DB::table('leave_requests')->where('id', $submitted['request_id'])->value('status'));
        $this->assertSame('ACTIVE', DB::table('leave_reservations')->where('id', $submitted['reservation_id'])->value('status'));
        $this->assertSame(0, DB::table('leave_workflow_actions')->where('operation_key', $actionKey)->count());
        $this->assertSame(0, DB::table('leave_ledger_entries')->where('kind', 'approved_leave_use')->count());
    }

    public function test_late_al_rejection_releases_hold_and_opens_review_without_auto_classification(): void
    {
        $fixture = $this->fixture('lateal');
        $approvers = $this->staffApprovers($fixture);
        $service = app(LeaveRequestWorkflowService::class);
        $submitted = $service->submit($fixture['user']->id, $fixture['annual_id'], $fixture['tabung_id'], 'late-al-'.$fixture['suffix'], 'AL', [['date' => '2026-03-02', 'session' => 'AM']], 'Synthetic emergency reason');
        $service->decide($approvers['pu']->id, $submitted['request_id'], 'late-al-support-'.$fixture['suffix'], 'SUPPORT');

        $rejected = $service->decide($approvers['pb']->id, $submitted['request_id'], 'late-al-reject-'.$fixture['suffix'], 'REJECT', 'Synthetic rejection reason');

        $this->assertSame('REJECTED', $rejected['status']);
        $this->assertSame('RELEASED', DB::table('leave_reservations')->where('id', $submitted['reservation_id'])->value('status'));
        $this->assertSame('LATE_AL_REJECTION', DB::table('leave_attendance_reviews')->where('revision_id', DB::table('leave_request_revisions')->where('request_id', $submitted['request_id'])->value('id'))->value('review_code'));
        $this->assertTrue((bool) DB::table('leave_requests')->where('id', $submitted['request_id'])->value('attendance_review_required'));
        $this->assertSame(0, DB::table('leave_ledger_entries')->where('kind', 'approved_leave_use')->count());
    }

    public function test_rejected_retrospective_el_posts_the_documented_debit_and_opens_hr_review(): void
    {
        $fixture = $this->fixture('retroel');
        $approvers = $this->staffApprovers($fixture);
        $service = app(LeaveRequestWorkflowService::class);
        $submitted = $service->submit($fixture['user']->id, $fixture['annual_id'], $fixture['tabung_id'], 'retro-el-'.$fixture['suffix'], 'EL', [['date' => '2026-02-27', 'session' => 'AM']], 'Synthetic emergency reason');
        $service->decide($approvers['pu']->id, $submitted['request_id'], 'retro-el-support-'.$fixture['suffix'], 'SUPPORT');

        $rejected = $service->decide($approvers['pb']->id, $submitted['request_id'], 'retro-el-reject-'.$fixture['suffix'], 'REJECT', 'Synthetic rejection reason');

        $revisionId = DB::table('leave_request_revisions')->where('request_id', $submitted['request_id'])->value('id');
        $this->assertSame('REJECTED', $rejected['status']);
        $this->assertSame('COMMITTED', DB::table('leave_reservations')->where('id', $submitted['reservation_id'])->value('status'));
        $this->assertSame(1, DB::table('leave_ledger_entries')->where('kind', 'rejected_el_absence_use')->count());
        $this->assertSame('RETROSPECTIVE_EL_REJECTION', DB::table('leave_attendance_reviews')->where('revision_id', $revisionId)->value('review_code'));
        $this->assertTrue((bool) DB::table('leave_requests')->where('id', $submitted['request_id'])->value('attendance_review_required'));
    }

    public function test_short_notice_needs_a_reason_and_ambiguous_counting_boundary_fails_closed(): void
    {
        $fixture = $this->fixture('notice');
        $this->staffApprovers($fixture);
        $service = app(LeaveRequestWorkflowService::class);

        $this->assertDomainException(fn () => $service->submit(
            $fixture['user']->id, $fixture['annual_id'], $fixture['tabung_id'], 'notice-missing-'.$fixture['suffix'], 'AL', [['date' => '2026-03-03', 'session' => 'AM']],
        ));
        $this->assertDomainException(fn () => $service->submit(
            $fixture['user']->id, $fixture['annual_id'], $fixture['tabung_id'], 'notice-edge-'.$fixture['suffix'], 'AL', [['date' => '2026-03-05', 'session' => 'AM']], 'Synthetic reason',
        ));
        $this->assertSame(0, DB::table('leave_requests')->count());

        $submitted = $service->submit(
            $fixture['user']->id, $fixture['annual_id'], $fixture['tabung_id'], 'notice-valid-'.$fixture['suffix'], 'AL', [['date' => '2026-03-03', 'session' => 'AM']], 'Synthetic emergency reason',
        );
        $revision = DB::table('leave_request_revisions')->where('request_id', $submitted['request_id'])->sole();
        $this->assertTrue((bool) $revision->is_short_notice_emergency);
        $this->assertSame(0, (int) $revision->notice_working_days_min);
        $this->assertSame(2, (int) $revision->notice_working_days_max);
    }

    public function test_wrong_assignee_and_changed_submission_replay_are_rejected_without_side_effects(): void
    {
        $fixture = $this->fixture('security');
        $approvers = $this->staffApprovers($fixture);
        $service = app(LeaveRequestWorkflowService::class);
        $slots = [['date' => '2026-03-06', 'session' => 'AM']];
        $submitted = $service->submit($fixture['user']->id, $fixture['annual_id'], $fixture['tabung_id'], 'security-'.$fixture['suffix'], 'AL', $slots);

        $this->assertDomainException(fn () => $service->decide($approvers['pb']->id, $submitted['request_id'], 'wrong-assignee-'.$fixture['suffix'], 'SUPPORT'));
        $this->assertDomainException(fn () => $service->decide($approvers['pu']->id, $submitted['request_id'], 'unsupported-correction-'.$fixture['suffix'], 'RETURN_FOR_CORRECTION', 'Synthetic correction request'));
        $this->assertDomainException(fn () => $service->submit($fixture['user']->id, $fixture['annual_id'], $fixture['tabung_id'], 'security-'.$fixture['suffix'], 'AL', [['date' => '2026-03-06', 'session' => 'PM']]));
        $this->assertSame(0, DB::table('leave_workflow_actions')->count());
        $this->assertSame(1, DB::table('leave_requests')->count());
        $this->assertSame('ACTIVE', DB::table('leave_reservations')->where('id', $submitted['reservation_id'])->value('status'));
    }

    /** @return array{suffix:string,user:User,employee_id:int,unit_id:int,annual_id:int,tabung_id:int} */
    private function fixture(string $label): array
    {
        $suffix = Str::lower(Str::random(8));
        $user = User::factory()->create(['username' => $label.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $unitId = DB::table('organization_units')->insertGetId(['code' => $label.$suffix, 'name' => 'Synthetic unit', 'active' => true]);
        $categoryId = DB::table('employment_categories')->insertGetId(['code' => 'cat'.$label.$suffix, 'name' => 'Synthetic category', 'active' => true]);
        $employeeId = DB::table('employees')->insertGetId([
            'employee_no' => $label.$suffix,
            'display_name' => 'Synthetic employee '.$label,
            'user_id' => $user->id,
            'service_start' => '2020-01-01',
        ]);
        DB::table('employee_assignments')->insert([
            'employee_id' => $employeeId,
            'unit_id' => $unitId,
            'category_id' => $categoryId,
            'effective_from' => '2020-01-01',
        ]);
        $annualId = DB::table('leave_annual_accounts')->insertGetId(['employee_id' => $employeeId, 'leave_year' => 2026]);
        $tabungId = DB::table('leave_tabung_accounts')->insertGetId(['employee_id' => $employeeId]);
        DB::table('leave_calendar_versions')->insert([
            'label' => 'Synthetic calendar '.$suffix,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'confirmed_by' => $user->id,
            'confirmed_at' => now(),
        ]);
        DB::table('leave_policy_versions')->insert([
            'label' => 'Synthetic schedule '.$suffix,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'working_weekdays' => '[1,2,3,4,5]',
            'work_start' => '08:00',
            'session_boundary' => '13:00',
            'work_end' => '17:00',
            'schedule_simulated_by' => $user->id,
            'schedule_simulated_at' => now(),
        ]);

        return ['suffix' => $suffix, 'user' => $user, 'employee_id' => $employeeId, 'unit_id' => $unitId, 'annual_id' => $annualId, 'tabung_id' => $tabungId];
    }

    /** @return array{pu:User,pb:User,secretary:User} */
    /** @param array{suffix:string,user:User,employee_id:int,unit_id:int,annual_id:int,tabung_id:int} $fixture
     * @return array{pu:User,pb:User,secretary:User}
     */
    private function staffApprovers(array $fixture): array
    {
        $pu = $this->user('pu');
        $pb = $this->user('pb');
        $secretary = $this->user('secretary');
        $this->assignOffice($pu, 'PU', $fixture['unit_id']);
        $this->assignOffice($pb, 'PB');
        $this->assignOffice($secretary, 'SECRETARY_ALK');

        return ['pu' => $pu, 'pb' => $pb, 'secretary' => $secretary];
    }

    private function user(string $label): User
    {
        return User::factory()->create(['username' => $label.Str::lower(Str::random(8)), 'email' => null, 'status' => 'ACTIVE']);
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

    private function assertDomainException(callable $action): void
    {
        try {
            $action();
        } catch (DomainException) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail('An incomplete or unsafe request action must fail closed.');
    }
}
