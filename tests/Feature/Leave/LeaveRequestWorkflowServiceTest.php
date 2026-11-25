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

    public function test_ct_dec_051_rejects_hr_submitting_on_behalf_even_with_permission(): void
    {
        $fixture = $this->fixture('assisted-denied');
        $hrActor = $this->user('hr-recorder');
        $hrActor->givePermissionTo('leave.request.record-on-behalf');
        $service = app(LeaveRequestWorkflowService::class);

        $this->assertDomainException(fn () => $service->submitOnBehalf(
            $hrActor->id,
            $fixture['user']->id,
            $fixture['annual_id'],
            $fixture['tabung_id'],
            'assisted-'.$fixture['suffix'],
            'AL',
            [['date' => '2026-03-06', 'session' => 'AM']],
            'Synthetic leave reason',
            'Synthetic HR assistance reason',
            'SYNTHETIC-CONSENT-'.$fixture['suffix'],
            '2026-03-02T08:45:00+08:00',
        ));

        $this->assertSame(0, DB::table('leave_requests')->count());
        $this->assertSame(0, DB::table('leave_request_on_behalf_submissions')->count());
        $this->assertSame(0, DB::table('leave_reservations')->count());
        $this->assertSame(0, DB::table('leave_balance_operations')->count());
        $this->assertSame(0, DB::table('leave_active_slots')->count());
    }

    public function test_ct_dec_008_not_support_does_not_advance_or_release_the_pending_hold(): void
    {
        $fixture = $this->fixture('forward');
        $approvers = $this->staffApprovers($fixture);
        $service = app(LeaveRequestWorkflowService::class);
        $submitted = $service->submit($fixture['user']->id, $fixture['annual_id'], $fixture['tabung_id'], 'forward-'.$fixture['suffix'], 'AL', [['date' => '2026-03-06', 'session' => 'AM']]);

        $actionKey = 'forward-not-support-'.$fixture['suffix'];
        $this->assertDomainException(fn () => $service->decide($approvers['pu']->id, $submitted['request_id'], $actionKey, 'NOT_SUPPORT', 'Synthetic reason'));
        $reservation = DB::table('leave_reservations')->where('id', $submitted['reservation_id'])->first();
        $this->assertSame('WAITING_UNIT_MANAGER_SUPPORT', DB::table('leave_requests')->where('id', $submitted['request_id'])->value('status'));
        $this->assertSame('ACTIVE', $reservation->status);
        $this->assertSame(1, DB::table('leave_reservation_events')->where('reservation_id', $reservation->id)->where('event_code', 'RESERVED')->count());
        $this->assertSame(0, DB::table('leave_workflow_actions')->where('operation_key', $actionKey)->count());
    }

    public function test_ct_dec_050_holds_unit_manager_self_application_without_an_approved_route(): void
    {
        $fixture = $this->fixture('selfpu');
        $this->assignOffice($fixture['user'], 'PU', $fixture['unit_id']);

        $this->assertDomainException(fn () => app(LeaveRequestWorkflowService::class)->submit(
            $fixture['user']->id, $fixture['annual_id'], $fixture['tabung_id'], 'self-pu-'.$fixture['suffix'], 'AL', [['date' => '2026-03-06', 'session' => 'AM']],
        ));

        $this->assertSame(0, DB::table('leave_requests')->count());
        $this->assertSame(0, DB::table('leave_reservations')->count());
        $this->assertSame(0, DB::table('leave_balance_operations')->count());
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

    public function test_ct_dec_046_holds_a_final_decision_after_an_al_date_starts(): void
    {
        $fixture = $this->fixture('lateal');
        $approvers = $this->staffApprovers($fixture);
        $service = app(LeaveRequestWorkflowService::class);
        $submitted = $service->submit($fixture['user']->id, $fixture['annual_id'], $fixture['tabung_id'], 'late-al-'.$fixture['suffix'], 'AL', [['date' => '2026-03-06', 'session' => 'AM']]);
        $service->decide($approvers['pu']->id, $submitted['request_id'], 'late-al-support-'.$fixture['suffix'], 'SUPPORT');
        Carbon::setTestNow('2026-03-06T09:00:00+08:00');

        $actionKey = 'late-al-reject-'.$fixture['suffix'];
        $this->assertDomainException(fn () => $service->decide($approvers['pb']->id, $submitted['request_id'], $actionKey, 'REJECT', 'Synthetic rejection reason'));

        $this->assertSame('WAITING_GENERAL_MANAGER_FINAL', DB::table('leave_requests')->where('id', $submitted['request_id'])->value('status'));
        $this->assertSame('ACTIVE', DB::table('leave_reservations')->where('id', $submitted['reservation_id'])->value('status'));
        $this->assertSame(0, DB::table('leave_workflow_actions')->where('operation_key', $actionKey)->count());
        $this->assertSame(0, DB::table('leave_attendance_reviews')->count());
        $this->assertSame(0, DB::table('leave_ledger_entries')->where('kind', 'approved_leave_use')->count());
    }

    public function test_ct_dec_032_holds_a_pending_request_after_effective_unit_transfer(): void
    {
        $fixture = $this->fixture('transfer-pending');
        $approvers = $this->staffApprovers($fixture);
        $service = app(LeaveRequestWorkflowService::class);
        $submitted = $service->submit($fixture['user']->id, $fixture['annual_id'], $fixture['tabung_id'], 'transfer-pending-'.$fixture['suffix'], 'AL', [['date' => '2026-03-06', 'session' => 'AM']]);
        $newUnitId = DB::table('organization_units')->insertGetId(['code' => 'new'.$fixture['suffix'], 'name' => 'Synthetic transferred unit', 'active' => true]);
        $categoryId = DB::table('employee_assignments')->where('employee_id', $fixture['employee_id'])->value('category_id');
        DB::table('employee_assignments')->where('employee_id', $fixture['employee_id'])->update(['effective_to' => '2026-03-03']);
        DB::table('employee_assignments')->insert([
            'employee_id' => $fixture['employee_id'],
            'unit_id' => $newUnitId,
            'category_id' => $categoryId,
            'effective_from' => '2026-03-04',
        ]);
        Carbon::setTestNow('2026-03-04T09:00:00+08:00');
        $actionKey = 'transfer-pending-support-'.$fixture['suffix'];

        $this->assertDomainException(fn () => $service->decide($approvers['pu']->id, $submitted['request_id'], $actionKey, 'SUPPORT'));

        $this->assertSame('WAITING_UNIT_MANAGER_SUPPORT', DB::table('leave_requests')->where('id', $submitted['request_id'])->value('status'));
        $this->assertSame('ACTIVE', DB::table('leave_reservations')->where('id', $submitted['reservation_id'])->value('status'));
        $this->assertSame(0, DB::table('leave_workflow_actions')->where('operation_key', $actionKey)->count());
    }

    public function test_employee_can_withdraw_a_pending_unused_al_request_once(): void
    {
        $fixture = $this->fixture('withdraw');
        $this->staffApprovers($fixture);
        $service = app(LeaveRequestWorkflowService::class);
        $submitted = $service->submit($fixture['user']->id, $fixture['annual_id'], $fixture['tabung_id'], 'withdraw-submit-'.$fixture['suffix'], 'AL', [['date' => '2026-03-06', 'session' => 'AM']]);
        $key = 'withdraw-action-'.$fixture['suffix'];

        $withdrawn = $service->withdrawPendingAnnualLeave($fixture['user']->id, $submitted['request_id'], $key);
        $replay = $service->withdrawPendingAnnualLeave($fixture['user']->id, $submitted['request_id'], $key);

        $this->assertSame('CANCELLED', $withdrawn['status']);
        $this->assertTrue($replay['replayed']);
        $this->assertSame('CANCELLED', DB::table('leave_requests')->where('id', $submitted['request_id'])->value('status'));
        $this->assertNull(DB::table('leave_requests')->where('id', $submitted['request_id'])->value('current_step_order'));
        $this->assertSame('RELEASED', DB::table('leave_reservations')->where('id', $submitted['reservation_id'])->value('status'));
        $this->assertSame(1, DB::table('leave_request_withdrawals')->where('request_id', $submitted['request_id'])->count());
        $this->assertSame(1, DB::table('audit_events')->where('action_code', 'hr.leave.request_withdrawn')->where('target_ref', 'leave_request:'.$submitted['request_id'])->count());
    }

    public function test_pending_al_with_a_started_leave_date_cannot_release_its_hold_automatically(): void
    {
        $fixture = $this->fixture('withdrawstarted');
        $this->staffApprovers($fixture);
        $service = app(LeaveRequestWorkflowService::class);
        $submitted = $service->submit($fixture['user']->id, $fixture['annual_id'], $fixture['tabung_id'], 'withdraw-start-submit-'.$fixture['suffix'], 'AL', [['date' => '2026-03-02', 'session' => 'AM']], 'Synthetic emergency reason');

        $this->assertDomainException(fn () => $service->withdrawPendingAnnualLeave($fixture['user']->id, $submitted['request_id'], 'withdraw-start-'.$fixture['suffix']));

        $this->assertSame('WAITING_UNIT_MANAGER_SUPPORT', DB::table('leave_requests')->where('id', $submitted['request_id'])->value('status'));
        $this->assertSame('ACTIVE', DB::table('leave_reservations')->where('id', $submitted['reservation_id'])->value('status'));
        $this->assertSame(0, DB::table('leave_request_withdrawals')->where('request_id', $submitted['request_id'])->count());
    }

    public function test_pre_start_annual_only_cancellation_is_gm_confirmed_reversible_and_idempotent(): void
    {
        $fixture = $this->fixture('cancel');
        $approvers = $this->staffApprovers($fixture);
        $service = app(LeaveRequestWorkflowService::class);
        $submitted = $service->submit($fixture['user']->id, $fixture['annual_id'], $fixture['tabung_id'], 'cancel-submit-'.$fixture['suffix'], 'AL', [['date' => '2026-03-06', 'session' => 'AM']]);
        $service->decide($approvers['pu']->id, $submitted['request_id'], 'cancel-support-'.$fixture['suffix'], 'SUPPORT');
        $service->decide($approvers['pb']->id, $submitted['request_id'], 'cancel-approve-'.$fixture['suffix'], 'APPROVE');
        $original = DB::table('leave_ledger_entries')->where('kind', 'approved_leave_use')->sole();
        $this->assertDomainException(fn () => $service->requestApprovedAnnualLeaveCancellation($fixture['user']->id, $submitted['request_id'], 'cancel-missing-reason-'.$fixture['suffix'], null));
        $this->assertSame('APPROVED', DB::table('leave_requests')->where('id', $submitted['request_id'])->value('status'));

        $requestKey = 'cancel-request-'.$fixture['suffix'];
        $requested = $service->requestApprovedAnnualLeaveCancellation($fixture['user']->id, $submitted['request_id'], $requestKey, 'Synthetic change of plan');
        $requestReplay = $service->requestApprovedAnnualLeaveCancellation($fixture['user']->id, $submitted['request_id'], $requestKey, 'Synthetic change of plan');
        $this->assertSame('WAITING_CANCELLATION', $requested['status']);
        $this->assertTrue($requestReplay['replayed']);
        $this->assertSame('COMMITTED', DB::table('leave_reservations')->where('id', $submitted['reservation_id'])->value('status'));
        $this->assertSame(-1, (int) DB::table('leave_ledger_entries')->where('id', $original->id)->value('signed_half_day_units'));

        $declined = $service->decideApprovedAnnualLeaveCancellation($approvers['pb']->id, $submitted['request_id'], 'cancel-decline-'.$fixture['suffix'], 'REJECT', 'Synthetic cancellation not accepted');
        $this->assertSame('APPROVED', $declined['status']);
        $this->assertSame('COMMITTED', DB::table('leave_reservations')->where('id', $submitted['reservation_id'])->value('status'));
        $this->assertSame(0, DB::table('leave_ledger_entries')->where('kind', 'approved_leave_cancellation')->count());
        $requestedAgain = $service->requestApprovedAnnualLeaveCancellation($fixture['user']->id, $submitted['request_id'], 'cancel-request-retry-'.$fixture['suffix'], 'Synthetic revised reason');
        $this->assertSame('WAITING_CANCELLATION', $requestedAgain['status']);

        $decisionKey = 'cancel-confirm-'.$fixture['suffix'];
        $cancelled = $service->decideApprovedAnnualLeaveCancellation($approvers['pb']->id, $submitted['request_id'], $decisionKey, 'CONFIRM');
        $replay = $service->decideApprovedAnnualLeaveCancellation($approvers['pb']->id, $submitted['request_id'], $decisionKey, 'CONFIRM');
        $reversal = DB::table('leave_ledger_entries')->where('reverses_entry_id', $original->id)->sole();

        $this->assertSame('CANCELLED', $cancelled['status']);
        $this->assertTrue($replay['replayed']);
        $this->assertSame('CANCELLED', DB::table('leave_reservations')->where('id', $submitted['reservation_id'])->value('status'));
        $this->assertSame(1, (int) $reversal->signed_half_day_units);
        $this->assertSame($original->annual_account_id, $reversal->annual_account_id);
        $this->assertSame(1, DB::table('leave_ledger_reversal_totals')->where('original_entry_id', $original->id)->where('reversed_units', 1)->count());
        $this->assertSame(1, DB::table('leave_active_slots')->where('reservation_id', $submitted['reservation_id'])->where('status', 'RELEASED')->whereNull('active_slot_key')->count());
        $this->assertSame(1, DB::table('leave_request_cancellation_actions')->where('request_id', $submitted['request_id'])->where('action_code', 'CONFIRMED')->count());

        $again = $service->submit($fixture['user']->id, $fixture['annual_id'], $fixture['tabung_id'], 'cancel-reuse-slot-'.$fixture['suffix'], 'AL', [['date' => '2026-03-06', 'session' => 'AM']]);
        $this->assertSame('WAITING_UNIT_MANAGER_SUPPORT', $again['status']);
    }

    public function test_ct_dec_046_keeps_same_day_pending_al_unacted_and_unreleased(): void
    {
        $currentDay = $this->fixture('cancelday');
        $currentDayApprovers = $this->staffApprovers($currentDay);
        $service = app(LeaveRequestWorkflowService::class);
        $todayRequest = $service->submit($currentDay['user']->id, $currentDay['annual_id'], $currentDay['tabung_id'], 'cancel-today-'.$currentDay['suffix'], 'AL', [['date' => '2026-03-02', 'session' => 'AM']], 'Synthetic same-day reason');
        $this->assertDomainException(fn () => $service->decide($currentDayApprovers['pu']->id, $todayRequest['request_id'], 'cancel-today-support-'.$currentDay['suffix'], 'SUPPORT'));
        $this->assertDomainException(fn () => $service->requestApprovedAnnualLeaveCancellation($currentDay['user']->id, $todayRequest['request_id'], 'cancel-today-request-'.$currentDay['suffix'], 'Synthetic reason'));
        $this->assertSame('WAITING_UNIT_MANAGER_SUPPORT', DB::table('leave_requests')->where('id', $todayRequest['request_id'])->value('status'));
        $this->assertSame('ACTIVE', DB::table('leave_reservations')->where('id', $todayRequest['reservation_id'])->value('status'));
        $this->assertSame(0, DB::table('leave_workflow_actions')->where('revision_id', DB::table('leave_request_revisions')->where('request_id', $todayRequest['request_id'])->value('id'))->count());
        $this->assertSame(0, DB::table('leave_request_cancellation_actions')->where('request_id', $todayRequest['request_id'])->count());
    }

    public function test_tabung_funded_cancellation_stays_isolated_without_a_verified_cap(): void
    {
        $tabung = $this->fixture('canceltabung');
        $tabungApprovers = $this->staffApprovers($tabung);
        $this->seedAnnualDeficitAndTabungCredit($tabung);
        $service = app(LeaveRequestWorkflowService::class);
        $tabungRequest = $service->submit($tabung['user']->id, $tabung['annual_id'], $tabung['tabung_id'], 'cancel-tabung-'.$tabung['suffix'], 'AL', [['date' => '2026-03-06', 'session' => 'AM']]);
        $this->assertSame(0, (int) DB::table('leave_reservations')->where('id', $tabungRequest['reservation_id'])->value('annual_units'));
        $this->assertSame(1, (int) DB::table('leave_reservations')->where('id', $tabungRequest['reservation_id'])->value('tabung_units'));
        $service->decide($tabungApprovers['pu']->id, $tabungRequest['request_id'], 'cancel-tabung-support-'.$tabung['suffix'], 'SUPPORT');
        $service->decide($tabungApprovers['pb']->id, $tabungRequest['request_id'], 'cancel-tabung-approve-'.$tabung['suffix'], 'APPROVE');

        $this->assertDomainException(fn () => $service->requestApprovedAnnualLeaveCancellation($tabung['user']->id, $tabungRequest['request_id'], 'cancel-tabung-request-'.$tabung['suffix'], 'Synthetic reason'));
        $this->assertSame('APPROVED', DB::table('leave_requests')->where('id', $tabungRequest['request_id'])->value('status'));
        $this->assertSame('COMMITTED', DB::table('leave_reservations')->where('id', $tabungRequest['reservation_id'])->value('status'));
        $this->assertSame(0, DB::table('leave_request_cancellation_actions')->where('request_id', $tabungRequest['request_id'])->count());
    }

    public function test_only_the_current_general_manager_can_confirm_cancellation(): void
    {
        $fixture = $this->fixture('cancelactor');
        $approvers = $this->staffApprovers($fixture);
        $service = app(LeaveRequestWorkflowService::class);
        $submitted = $service->submit($fixture['user']->id, $fixture['annual_id'], $fixture['tabung_id'], 'cancel-actor-submit-'.$fixture['suffix'], 'AL', [['date' => '2026-03-06', 'session' => 'AM']]);
        $service->decide($approvers['pu']->id, $submitted['request_id'], 'cancel-actor-support-'.$fixture['suffix'], 'SUPPORT');
        $service->decide($approvers['pb']->id, $submitted['request_id'], 'cancel-actor-approve-'.$fixture['suffix'], 'APPROVE');
        $service->requestApprovedAnnualLeaveCancellation($fixture['user']->id, $submitted['request_id'], 'cancel-actor-request-'.$fixture['suffix'], 'Synthetic reason');

        $this->assertDomainException(fn () => $service->decideApprovedAnnualLeaveCancellation($approvers['secretary']->id, $submitted['request_id'], 'cancel-actor-wrong-'.$fixture['suffix'], 'CONFIRM'));

        $this->assertSame('WAITING_CANCELLATION', DB::table('leave_requests')->where('id', $submitted['request_id'])->value('status'));
        $this->assertSame(1, DB::table('leave_request_cancellation_actions')->where('request_id', $submitted['request_id'])->where('action_code', 'REQUESTED')->count());
        $this->assertSame(0, DB::table('leave_request_cancellation_actions')->where('request_id', $submitted['request_id'])->where('action_code', 'CONFIRMED')->count());
    }

    public function test_current_reviewer_can_return_future_annual_leave_for_correction_once(): void
    {
        $fixture = $this->fixture('correction');
        $approvers = $this->staffApprovers($fixture);
        $service = app(LeaveRequestWorkflowService::class);
        $submitted = $service->submit($fixture['user']->id, $fixture['annual_id'], $fixture['tabung_id'], 'correction-submit-'.$fixture['suffix'], 'AL', [['date' => '2026-03-06', 'session' => 'AM']]);
        $key = 'correction-return-'.$fixture['suffix'];

        $returned = $service->decide($approvers['pu']->id, $submitted['request_id'], $key, 'RETURN_FOR_CORRECTION', 'Synthetic missing information');
        $replay = $service->decide($approvers['pu']->id, $submitted['request_id'], $key, 'RETURN_FOR_CORRECTION', 'Synthetic missing information');

        $this->assertSame('DRAFT', $returned['status']);
        $this->assertTrue($replay['replayed']);
        $this->assertSame('DRAFT', DB::table('leave_requests')->where('id', $submitted['request_id'])->value('status'));
        $this->assertNull(DB::table('leave_requests')->where('id', $submitted['request_id'])->value('current_step_order'));
        $this->assertSame('RELEASED', DB::table('leave_reservations')->where('id', $submitted['reservation_id'])->value('status'));
        $this->assertSame(1, DB::table('leave_reservation_events')->where('reservation_id', $submitted['reservation_id'])->where('event_code', 'RELEASED')->count());
        $this->assertSame(1, DB::table('leave_workflow_actions')->where('revision_id', DB::table('leave_request_revisions')->where('request_id', $submitted['request_id'])->value('id'))->where('action_code', 'RETURN_FOR_CORRECTION')->count());
    }

    public function test_mid_leave_partial_cancellation_requires_hr_verification_and_gm_confirmation(): void
    {
        $fixture = $this->fixture('partialcancel');
        $approvers = $this->staffApprovers($fixture);
        $hr = $this->user('partial-hr');
        $hr->givePermissionTo('leave.partial-cancellation.review');
        $service = app(LeaveRequestWorkflowService::class);
        $submitted = $service->submit(
            $fixture['user']->id,
            $fixture['annual_id'],
            $fixture['tabung_id'],
            'partial-submit-'.$fixture['suffix'],
            'AL',
            [
                ['date' => '2026-03-06', 'session' => 'AM'],
                ['date' => '2026-03-06', 'session' => 'PM'],
                ['date' => '2026-03-09', 'session' => 'AM'],
                ['date' => '2026-03-09', 'session' => 'PM'],
            ],
            null,
        );
        $service->decide($approvers['pu']->id, $submitted['request_id'], 'partial-support-'.$fixture['suffix'], 'SUPPORT');
        $service->decide($approvers['pb']->id, $submitted['request_id'], 'partial-approve-'.$fixture['suffix'], 'APPROVE');
        Carbon::setTestNow('2026-03-08T09:00:00+08:00');
        $requestKey = 'partial-request-'.$fixture['suffix'];
        $partial = $service->requestApprovedAnnualLeavePartialCancellation(
            $fixture['user']->id, $submitted['request_id'], $requestKey, 'Synthetic early return request',
        );
        $requestReplay = $service->requestApprovedAnnualLeavePartialCancellation(
            $fixture['user']->id, $submitted['request_id'], $requestKey, 'Synthetic early return request',
        );
        $this->assertDomainException(fn () => $service->requestApprovedAnnualLeavePartialCancellation(
            $fixture['user']->id, $submitted['request_id'], 'partial-request-duplicate-'.$fixture['suffix'], 'Synthetic duplicate request',
        ));

        Carbon::setTestNow('2026-03-08T09:00:00+08:00');
        $verificationKey = 'partial-verify-'.$fixture['suffix'];
        $verified = $service->verifyApprovedAnnualLeavePartialCancellation(
            $hr->id, $submitted['request_id'], $partial['partial_cancellation_id'], $verificationKey, '2026-03-07', 'Synthetic HR return verification',
        );
        $verificationReplay = $service->verifyApprovedAnnualLeavePartialCancellation(
            $hr->id, $submitted['request_id'], $partial['partial_cancellation_id'], $verificationKey, '2026-03-07', 'Synthetic HR return verification',
        );
        $decisionKey = 'partial-confirm-'.$fixture['suffix'];
        $confirmed = $service->decideApprovedAnnualLeavePartialCancellation(
            $approvers['pb']->id, $submitted['request_id'], $partial['partial_cancellation_id'], $decisionKey, 'CONFIRM',
        );
        $decisionReplay = $service->decideApprovedAnnualLeavePartialCancellation(
            $approvers['pb']->id, $submitted['request_id'], $partial['partial_cancellation_id'], $decisionKey, 'CONFIRM',
        );

        $this->assertTrue($requestReplay['replayed']);
        $this->assertSame(2, $verified['unused_half_day_units']);
        $this->assertTrue($verificationReplay['replayed']);
        $this->assertSame('APPROVED', $confirmed['status']);
        $this->assertSame(2, $confirmed['refunded_half_day_units']);
        $this->assertTrue($decisionReplay['replayed']);
        $originalUsage = DB::table('leave_ledger_entries')->where('kind', 'approved_leave_use')->sole();
        $partialRefund = DB::table('leave_ledger_entries')->where('kind', 'approved_leave_partial_cancellation')->sole();
        $this->assertSame('APPROVED', DB::table('leave_requests')->where('id', $submitted['request_id'])->value('status'));
        $this->assertSame('COMMITTED', DB::table('leave_reservations')->where('id', $submitted['reservation_id'])->value('status'));
        $this->assertSame(-4, (int) $originalUsage->signed_half_day_units);
        $this->assertSame(2, (int) $partialRefund->signed_half_day_units);
        $this->assertSame($originalUsage->id, $partialRefund->reverses_entry_id);
        $this->assertSame($originalUsage->annual_account_id, $partialRefund->annual_account_id);
        $this->assertSame(2, (int) DB::table('leave_ledger_reversal_totals')->where('original_entry_id', $originalUsage->id)->value('reversed_units'));
        $this->assertSame(1, DB::table('leave_ledger_entries')->where('kind', 'approved_leave_partial_cancellation')->count());
        $this->assertSame(2, DB::table('leave_active_slots')->where('reservation_id', $submitted['reservation_id'])->where('status', 'COMMITTED')->count());
        $this->assertSame(2, DB::table('leave_active_slots')->where('reservation_id', $submitted['reservation_id'])->where('status', 'RELEASED')->count());
        $this->assertSame(1, DB::table('leave_reservation_events')->where('reservation_id', $submitted['reservation_id'])->where('event_code', 'PARTIAL_REFUND')->count());
        $this->assertSame(3, DB::table('leave_request_partial_cancellation_events')->where('partial_cancellation_id', $partial['partial_cancellation_id'])->count());
        $this->assertSame('CONFIRMED', DB::table('leave_request_partial_cancellations')->where('id', $partial['partial_cancellation_id'])->value('status'));
    }

    public function test_partial_cancellation_of_tabung_funded_leave_fails_closed(): void
    {
        $fixture = $this->fixture('partialtabung');
        $approvers = $this->staffApprovers($fixture);
        $this->seedAnnualDeficitAndTabungCredit($fixture);
        $service = app(LeaveRequestWorkflowService::class);
        $submitted = $service->submit(
            $fixture['user']->id,
            $fixture['annual_id'],
            $fixture['tabung_id'],
            'partial-tabung-submit-'.$fixture['suffix'],
            'AL',
            [
                ['date' => '2026-03-06', 'session' => 'AM'],
                ['date' => '2026-03-09', 'session' => 'AM'],
            ],
        );
        $this->assertSame(1, (int) DB::table('leave_reservations')->where('id', $submitted['reservation_id'])->value('tabung_units'));
        $service->decide($approvers['pu']->id, $submitted['request_id'], 'partial-tabung-support-'.$fixture['suffix'], 'SUPPORT');
        $service->decide($approvers['pb']->id, $submitted['request_id'], 'partial-tabung-approve-'.$fixture['suffix'], 'APPROVE');

        $this->assertDomainException(fn () => $service->requestApprovedAnnualLeavePartialCancellation(
            $fixture['user']->id, $submitted['request_id'], 'partial-tabung-request-'.$fixture['suffix'], 'Synthetic early return request',
        ));

        $this->assertSame('APPROVED', DB::table('leave_requests')->where('id', $submitted['request_id'])->value('status'));
        $this->assertSame('COMMITTED', DB::table('leave_reservations')->where('id', $submitted['reservation_id'])->value('status'));
        $this->assertSame(0, DB::table('leave_request_partial_cancellations')->where('request_id', $submitted['request_id'])->count());
    }

    public function test_partial_cancellation_does_not_guess_usage_on_a_requested_return_date(): void
    {
        $fixture = $this->fixture('partialreturnboundary');
        $approvers = $this->staffApprovers($fixture);
        $hr = $this->user('partial-boundary-hr');
        $hr->givePermissionTo('leave.partial-cancellation.review');
        $service = app(LeaveRequestWorkflowService::class);
        $submitted = $service->submit(
            $fixture['user']->id,
            $fixture['annual_id'],
            $fixture['tabung_id'],
            'partial-boundary-submit-'.$fixture['suffix'],
            'AL',
            [
                ['date' => '2026-03-06', 'session' => 'AM'],
                ['date' => '2026-03-06', 'session' => 'PM'],
                ['date' => '2026-03-09', 'session' => 'AM'],
                ['date' => '2026-03-11', 'session' => 'AM'],
            ],
        );
        $service->decide($approvers['pu']->id, $submitted['request_id'], 'partial-boundary-support-'.$fixture['suffix'], 'SUPPORT');
        $service->decide($approvers['pb']->id, $submitted['request_id'], 'partial-boundary-approve-'.$fixture['suffix'], 'APPROVE');
        Carbon::setTestNow('2026-03-08T09:00:00+08:00');
        $partial = $service->requestApprovedAnnualLeavePartialCancellation(
            $fixture['user']->id, $submitted['request_id'], 'partial-boundary-request-'.$fixture['suffix'], 'Synthetic early return request',
        );

        Carbon::setTestNow('2026-03-10T09:00:00+08:00');
        $this->assertDomainException(fn () => $service->verifyApprovedAnnualLeavePartialCancellation(
            $hr->id, $submitted['request_id'], $partial['partial_cancellation_id'], 'partial-boundary-verify-'.$fixture['suffix'], '2026-03-09', 'Synthetic HR return verification',
        ));

        $this->assertSame('APPROVED', DB::table('leave_requests')->where('id', $submitted['request_id'])->value('status'));
        $this->assertSame('COMMITTED', DB::table('leave_reservations')->where('id', $submitted['reservation_id'])->value('status'));
        $this->assertSame('REQUESTED', DB::table('leave_request_partial_cancellations')->where('id', $partial['partial_cancellation_id'])->value('status'));
        $this->assertSame(1, DB::table('leave_request_partial_cancellation_events')->where('partial_cancellation_id', $partial['partial_cancellation_id'])->count());
    }

    public function test_correction_return_does_not_release_a_hold_after_leave_starts(): void
    {
        $fixture = $this->fixture('correctionstarted');
        $approvers = $this->staffApprovers($fixture);
        $service = app(LeaveRequestWorkflowService::class);
        $submitted = $service->submit($fixture['user']->id, $fixture['annual_id'], $fixture['tabung_id'], 'correction-started-'.$fixture['suffix'], 'AL', [['date' => '2026-03-02', 'session' => 'AM']], 'Synthetic same-day reason');

        $this->assertDomainException(fn () => $service->decide($approvers['pu']->id, $submitted['request_id'], 'correction-started-return-'.$fixture['suffix'], 'RETURN_FOR_CORRECTION', 'Synthetic correction reason'));

        $this->assertSame('WAITING_UNIT_MANAGER_SUPPORT', DB::table('leave_requests')->where('id', $submitted['request_id'])->value('status'));
        $this->assertSame('ACTIVE', DB::table('leave_reservations')->where('id', $submitted['reservation_id'])->value('status'));
        $this->assertSame(0, DB::table('leave_workflow_actions')->where('operation_key', 'correction-started-return-'.$fixture['suffix'])->count());
    }

    public function test_ct_dec_027_holds_el_submission_without_an_hr_configured_window(): void
    {
        $fixture = $this->fixture('retroel');
        $this->assertDomainException(fn () => app(LeaveRequestWorkflowService::class)->submit(
            $fixture['user']->id, $fixture['annual_id'], $fixture['tabung_id'], 'retro-el-'.$fixture['suffix'], 'EL', [['date' => '2026-02-27', 'session' => 'AM']], 'Synthetic emergency reason',
        ));

        $this->assertSame(0, DB::table('leave_requests')->count());
        $this->assertSame(0, DB::table('leave_reservations')->count());
        $this->assertSame(0, DB::table('leave_balance_operations')->count());
        $this->assertSame(0, DB::table('leave_ledger_entries')->count());
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

    public function test_wrong_assignee_missing_correction_reason_and_changed_submission_replay_are_rejected_without_side_effects(): void
    {
        $fixture = $this->fixture('security');
        $approvers = $this->staffApprovers($fixture);
        $service = app(LeaveRequestWorkflowService::class);
        $slots = [['date' => '2026-03-06', 'session' => 'AM']];
        $submitted = $service->submit($fixture['user']->id, $fixture['annual_id'], $fixture['tabung_id'], 'security-'.$fixture['suffix'], 'AL', $slots);

        $this->assertDomainException(fn () => $service->decide($approvers['pb']->id, $submitted['request_id'], 'wrong-assignee-'.$fixture['suffix'], 'SUPPORT'));
        $this->assertDomainException(fn () => $service->decide($approvers['pu']->id, $submitted['request_id'], 'correction-missing-reason-'.$fixture['suffix'], 'RETURN_FOR_CORRECTION'));
        $this->assertDomainException(fn () => $service->decide($approvers['pb']->id, $submitted['request_id'], 'correction-wrong-assignee-'.$fixture['suffix'], 'RETURN_FOR_CORRECTION', 'Synthetic correction request'));
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

    /** @param array{suffix:string,user:User,employee_id:int,unit_id:int,annual_id:int,tabung_id:int} $fixture */
    private function seedAnnualDeficitAndTabungCredit(array $fixture): void
    {
        foreach ([['annual', -1, $fixture['annual_id'], null], ['tabung', 1, null, $fixture['tabung_id']]] as [$label, $units, $annualId, $tabungId]) {
            $key = 'synthetic-'.$label.'-'.$fixture['suffix'];
            $operationId = DB::table('leave_balance_operations')->insertGetId([
                'employee_id' => $fixture['employee_id'],
                'actor_id' => $fixture['user']->id,
                'operation_key' => $key,
                'operation_type' => 'SYNTHETIC',
                'payload_hash' => hash('sha256', $key),
                'created_at' => now(),
            ]);
            DB::table('leave_ledger_entries')->insert([
                'annual_account_id' => $annualId,
                'tabung_account_id' => $tabungId,
                'balance_operation_id' => $operationId,
                'signed_half_day_units' => $units,
                'kind' => 'synthetic_fixture',
                'source_year' => $tabungId === null ? null : 2026,
                'operation_key' => $key,
                'actor_id' => $fixture['user']->id,
            ]);
        }
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
