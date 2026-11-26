<?php

namespace Tests\OwnerReview;

use App\Models\User;
use App\Services\Leave\AnnualEntitlementCreditService;
use App\Services\Leave\AnnualLeaveYearCloseService;
use App\Services\Leave\ConfirmedVersionLookup;
use App\Services\Leave\LeaveAdjustmentService;
use App\Services\Leave\LeaveRequestWorkflowService;
use App\Services\Leave\LedgerPostedTotals;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

/** Explicit opt-in only. Never include this in the general feature suite or run in parallel. */
class AnnualLeaveReviewTest extends TestCase
{
    use DatabaseTransactions {
        beginDatabaseTransaction as private beginRollbackTransaction;
    }

    private int $year;

    public function beginDatabaseTransaction(): void
    {
        $config = config('database.connections.mysql');
        if (getenv('KUTMB_OWNER_LEAVE_REVIEW') !== '1'
            || ! app()->environment('testing') || app()->configurationIsCached()
            || config('database.default') !== 'mysql'
            || ($config['driver'] ?? null) !== 'mysql'
            || ($config['host'] ?? null) !== 'mysql'
            || ($config['database'] ?? null) !== 'kutmb_hr_dev'
            || ! empty($config['url']) || ! empty($config['unix_socket'])
            || isset($config['read']) || isset($config['write'])
            || config('cache.default') !== 'array' || config('session.driver') !== 'array'
            || config('queue.default') !== 'sync' || config('mail.default') !== 'array') {
            throw new RuntimeException('Owner review refused: use the explicit local Docker command, uncached config and array session/cache/mail.');
        }

        $identity = DB::selectOne('SELECT DATABASE() AS db_name');
        if ($identity?->db_name !== 'kutmb_hr_dev' || DB::transactionLevel() !== 0) {
            throw new RuntimeException('Owner review refused: unexpected effective database or transaction state.');
        }

        $tables = DB::table('information_schema.tables')->whereRaw('table_schema = DATABASE()')
            ->where('table_type', 'BASE TABLE')->get(['TABLE_NAME as name', 'ENGINE as engine']);
        if ($tables->contains(fn ($row): bool => strtoupper((string) $row->engine) !== 'INNODB')) {
            throw new RuntimeException('Owner review refused: nontransactional table found.');
        }
        foreach (['leave_balance_adjustments', 'leave_annual_commitments', 'leave_annual_year_closes', 'leave_attendance_review_outcomes',
            'leave_reservation_slot_allocations', 'leave_tabung_cap_confirmations', 'leave_late_release_claims', 'leave_late_reconciliations', 'leave_attendance_non_use_slots', 'approval_assignments', 'leave_ledger_entries', 'users', 'audit_events'] as $table) {
            if (! $tables->contains('name', $table)) {
                throw new RuntimeException('Required migration missing: '.$table);
            }
        }
        $this->beginRollbackTransaction();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertGreaterThan(0, DB::transactionLevel());
        // These tests assert backend authorization and Inertia props, not assets.
        $this->withoutVite();
        $this->year = (int) now('Asia/Kuala_Lumpur')->format('Y');
        Carbon::setTestNow(Carbon::parse($this->year.'-12-15 09:00:00', 'Asia/Kuala_Lumpur'));
        // Read existing synthetic calendar/schedule and office configuration, never overwrite it.
        $this->demoActor('unit-manager', 'leave.request.decide');
        $this->demoActor('general-manager', 'leave.request.decide');
        $this->demoActor('hr-manager', 'leave.year-end.post');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_ct_040_adjustment_requires_independent_approval_and_posts_once(): void
    {
        $f = $this->fixture();
        $maker = $this->actor('maker', ['leave.adjustment.prepare', 'leave.adjustment.decide']);
        $checker = $this->actor('checker', ['leave.adjustment.decide']);
        $outsider = $this->actor('outsider', []);
        $service = app(LeaveAdjustmentService::class);
        $payload = ['employee' => $f['employee'], 'bucket' => 'AL', 'units' => 3,
            'reason' => 'Synthetic correction', 'evidence_reference' => 'SYNTHETIC-EVIDENCE', 'key' => $this->key()];

        $this->denied(fn () => $service->prepare($outsider->id, $payload));
        $id = $service->prepare($maker->id, $payload);
        $this->assertSame($id, $service->prepare($maker->id, $payload));
        $this->assertSame(40, $this->annual($f['annual']));
        $this->assertDatabaseHas('leave_balance_adjustments', ['id' => $id, 'status' => 'PENDING', 'ledger_entry_id' => null]);
        $decision = ['decision' => 'APPROVED', 'reason' => 'Synthetic verification', 'key' => $this->key()];
        $this->denied(fn () => $service->decide($maker->id, $id, $decision));
        $this->denied(fn () => $service->decide($outsider->id, $id, $decision));
        $service->decide($checker->id, $id, $decision);
        $service->decide($checker->id, $id, $decision);
        $this->assertSame(43, $this->annual($f['annual']));
        $this->assertDatabaseHas('leave_balance_adjustments', ['id' => $id, 'status' => 'APPROVED', 'before_units' => 40, 'after_units' => 43]);
        $this->assertSame(1, DB::table('leave_ledger_entries')->where('annual_account_id', $f['annual'])->where('kind', 'hr_manual_adjustment')->count());

        $rejected = $service->prepare($maker->id, [...$payload, 'key' => $this->key(), 'units' => -2]);
        $service->decide($checker->id, $rejected, ['decision' => 'REJECTED', 'reason' => 'Synthetic rejection', 'key' => $this->key()]);
        $this->assertSame(43, $this->annual($f['annual']));
        $this->assertDatabaseHas('leave_balance_adjustments', ['id' => $rejected, 'status' => 'REJECTED', 'ledger_entry_id' => null]);
    }

    public function test_ct_037_future_approval_is_a_commitment_until_january_credit(): void
    {
        $f = $this->fixture();
        $date = $this->workingDate(($this->year + 1).'-01-05', ($this->year + 1).'-01-20');
        $payload = ['leave_type' => 'AL', 'start' => $date, 'end' => $date, 'session' => 'FULL', 'key' => $this->key()];
        $this->actingAs($f['staff'])->from('/leave/annual')->post('/leave/annual/requests', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $id = (int) DB::table('leave_requests')->where('submission_key', $payload['key'])->value('id');
        $this->assertGreaterThan(0, $id);
        $this->assertSame(2, app(LedgerPostedTotals::class)->pendingAnnualUnits($f['next']));
        $this->assertSame(0, $this->annual($f['next']));
        $this->approve($f, $id);
        $this->assertDatabaseHas('leave_requests', ['id' => $id, 'status' => 'APPROVED']);
        $this->assertDatabaseHas('leave_annual_commitments', ['annual_account_id' => $f['next'], 'units' => 2, 'status' => 'COMMITTED']);
        $this->assertSame(0, $this->annual($f['next']));
        $this->assertSame(0, app(LedgerPostedTotals::class)->pendingAnnualUnits($f['next']));
        $this->actingAs($f['staff'])->get('/leave/annual?section=apply&view=workflow')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('leave/AnnualLeave')
                ->where('workspace.future_balance.confirmed', 40)->where('workspace.future_balance.committed', 2)
                ->where('workspace.future_balance.available', 38));

        Carbon::setTestNow(Carbon::parse(($this->year + 1).'-01-01 09:00:00', 'Asia/Kuala_Lumpur'));
        $credit = app(AnnualEntitlementCreditService::class);
        $credit->creditConfirmedEntitlementOnJanuaryFirst($f['hr']->id, $f['next_confirmation']);
        $again = $credit->creditConfirmedEntitlementOnJanuaryFirst($f['hr']->id, $f['next_confirmation']);
        $this->assertTrue($again['replayed']);
        $this->assertSame(38, $this->annual($f['next']));
        $this->assertDatabaseHas('leave_annual_commitments', ['annual_account_id' => $f['next'], 'status' => 'SETTLED']);
        $this->assertSame(1, DB::table('leave_ledger_entries')->where('annual_account_id', $f['next'])->where('kind', 'approved_leave_use')->count());
    }

    public function test_ct_034_cross_year_approval_and_cancellation_preserve_each_source(): void
    {
        $f = $this->fixture();
        $dec = $this->workingDate($this->year.'-12-23', $this->year.'-12-31');
        $jan = $this->workingDate(($this->year + 1).'-01-05', ($this->year + 1).'-01-20');
        $workflow = app(LeaveRequestWorkflowService::class);
        $result = $workflow->submit($f['staff']->id, $f['annual'], $f['tabung'], $this->key(), 'AL',
            [['date' => $dec, 'session' => 'AM'], ['date' => $jan, 'session' => 'PM']]);
        $this->assertSame(1, app(LedgerPostedTotals::class)->pendingAnnualUnits($f['annual']));
        $this->assertSame(1, app(LedgerPostedTotals::class)->pendingAnnualUnits($f['next']));
        $this->assertDatabaseHas('leave_reservation_slot_allocations', ['reservation_id' => $result['reservation_id'], 'annual_account_id' => $f['annual'], 'leave_year' => $this->year, 'leave_date' => $dec]);
        $this->assertDatabaseHas('leave_reservation_slot_allocations', ['reservation_id' => $result['reservation_id'], 'annual_account_id' => $f['next'], 'leave_year' => $this->year + 1, 'leave_date' => $jan]);
        $this->approve($f, $result['request_id']);
        $this->assertSame(39, $this->annual($f['annual']));
        $this->assertSame(0, $this->annual($f['next']));
        $this->assertDatabaseHas('leave_annual_commitments', ['reservation_id' => $result['reservation_id'], 'annual_account_id' => $f['next'], 'units' => 1, 'status' => 'COMMITTED']);

        $workflow->requestApprovedAnnualLeaveCancellation($f['staff']->id, $result['request_id'], $this->key(), 'Synthetic cancellation');
        $workflow->decideApprovedAnnualLeaveCancellation($f['pb']->id, $result['request_id'], $this->key(), 'CONFIRM');
        $this->assertSame(40, $this->annual($f['annual']));
        $this->assertSame(0, $this->annual($f['next']));
        $this->assertDatabaseHas('leave_annual_commitments', ['reservation_id' => $result['reservation_id'], 'status' => 'CANCELLED']);
        $this->assertDatabaseHas('leave_requests', ['id' => $result['request_id'], 'status' => 'CANCELLED']);
    }

    public function test_ct_033_tabung_full_rate_is_independent_of_annual_credit(): void
    {
        $f = $this->fixture();
        $capacity = app(\App\Services\Leave\TabungCapacity::class);
        $key = $this->key();
        $capacity->confirmRate($f['hr']->id, $f['annual'], $this->year.'-07-01', 50, 'Synthetic promotion rate', $key);
        $capacity->confirmRate($f['hr']->id, $f['annual'], $this->year.'-07-01', 50, 'Synthetic promotion rate', $key);
        $this->assertSame(50, $capacity->confirmedCapFor($f['employee'], $this->year));
        $this->assertSame(40, $this->annual($f['annual']));
        $this->assertSame(0, $this->tabung($f['tabung']));
        $this->assertSame(2, DB::table('leave_tabung_cap_confirmations')->where('annual_account_id', $f['annual'])->count());
        $this->denied(fn () => $capacity->confirmRate($f['hr']->id, $f['annual'], $this->year.'-08-01', 40, 'Synthetic decrease', $this->key()));
    }

    public function test_ct_016_tabung_full_cancellation_restores_source_and_disposes_unambiguous_excess(): void
    {
        $f = $this->fixture();
        $maker = $this->actor('maker', ['leave.adjustment.prepare']);
        $checker = $this->actor('checker', ['leave.adjustment.decide']);
        $adjust = app(LeaveAdjustmentService::class);
        $post = function (string $bucket, int $units) use ($adjust, $maker, $checker, $f): void {
            $id = $adjust->prepare($maker->id, ['employee' => $f['employee'], 'bucket' => $bucket,
                'source_year' => $this->year, 'units' => $units, 'reason' => 'Synthetic adjustment',
                'evidence_reference' => 'SYNTHETIC', 'key' => $this->key()]);
            $adjust->decide($checker->id, $id, ['decision' => 'APPROVED', 'reason' => 'Synthetic checked', 'key' => $this->key()]);
        };
        $post('AL', -40);
        $post('TABUNG', 40);
        $date = $this->workingDate($this->year.'-12-23', $this->year.'-12-31');
        $workflow = app(LeaveRequestWorkflowService::class);
        $result = $workflow->submit($f['staff']->id, $f['annual'], $f['tabung'], $this->key(), 'AL',
            [['date' => $date, 'session' => 'AM'], ['date' => $date, 'session' => 'PM']]);
        $this->approve($f, $result['request_id']);
        $this->assertSame(38, $this->tabung($f['tabung']));
        $post('TABUNG', 2);
        $workflow->requestApprovedAnnualLeaveCancellation($f['staff']->id, $result['request_id'], $this->key(), 'Synthetic cancellation');
        $decision = $this->key();
        $workflow->decideApprovedAnnualLeaveCancellation($f['pb']->id, $result['request_id'], $decision, 'CONFIRM');
        $workflow->decideApprovedAnnualLeaveCancellation($f['pb']->id, $result['request_id'], $decision, 'CONFIRM');
        $this->assertSame(40, $this->tabung($f['tabung']));
        $this->assertSame(0, $this->annual($f['annual']));
        $this->assertDatabaseHas('leave_requests', ['id' => $result['request_id'], 'status' => 'CANCELLED']);
        $this->assertDatabaseHas('leave_ledger_entries', ['tabung_account_id' => $f['tabung'], 'kind' => 'approved_leave_cancellation',
            'source_year' => $this->year, 'signed_half_day_units' => 2]);
        $this->assertDatabaseHas('leave_ledger_entries', ['tabung_account_id' => $f['tabung'], 'kind' => 'tabung_cancellation_excess_disposal',
            'source_year' => $this->year, 'signed_half_day_units' => -2]);
        $this->assertSame(1, DB::table('leave_ledger_entries')->where('tabung_account_id', $f['tabung'])
            ->where('kind', 'tabung_cancellation_excess_disposal')->count());
    }

    public function test_ct_038_manual_close_retains_pending_and_carries_only_free_balance(): void
    {
        $f = $this->fixture();
        $date = $this->workingDate($this->year.'-12-23', $this->year.'-12-31');
        app(LeaveRequestWorkflowService::class)->submit($f['staff']->id, $f['annual'], $f['tabung'], $this->key(), 'AL',
            [['date' => $date, 'session' => 'AM'], ['date' => $date, 'session' => 'PM']]);
        $service = app(AnnualLeaveYearCloseService::class);
        $this->assertFalse($service->eligibility($f['employee'], $this->year, 40)['can_post']);
        Carbon::setTestNow(Carbon::parse(($this->year + 1).'-01-01 09:00:00', 'Asia/Kuala_Lumpur'));
        app(AnnualEntitlementCreditService::class)->creditConfirmedEntitlementOnJanuaryFirst($f['hr']->id, $f['next_confirmation']);
        $this->assertFalse($service->eligibility($f['employee'], $this->year, 39)['can_post']);
        $preview = $service->eligibility($f['employee'], $this->year, 40);
        $this->assertTrue($preview['can_post'], (string) $preview['reason']);
        $this->assertSame(['annual' => 40, 'pending' => 2, 'tabung' => 0, 'transfer' => 38], $preview['snapshot']);
        $this->denied(fn () => $service->close($f['hr']->id, $f['employee'], $this->year, 40, [...$preview['snapshot'], 'transfer' => 37], 'Synthetic close'));
        $service->close($f['hr']->id, $f['employee'], $this->year, 40, $preview['snapshot'], 'Synthetic close');
        $service->close($f['hr']->id, $f['employee'], $this->year, 40, $preview['snapshot'], 'Synthetic close');
        $this->assertSame(2, $this->annual($f['annual']));
        $this->assertSame(2, app(LedgerPostedTotals::class)->pendingAnnualUnits($f['annual']));
        $this->assertSame(38, $this->tabung($f['tabung']));
        $this->assertSame(40, $this->annual($f['next']));
        $this->assertSame(1, DB::table('leave_annual_year_closes')->where('annual_account_id', $f['annual'])->count());
    }

    public function test_ct_051_assisted_submission_requires_consent_and_preserves_applicant(): void
    {
        $f = $this->fixture();
        $date = $this->workingDate($this->year.'-12-23', $this->year.'-12-31');
        // MySQL's immutable consent trigger checks its real clock, while Carbon
        // is frozen in December for leave scenarios. Consent must predate both.
        $databaseNow = CarbonImmutable::parse(DB::selectOne('SELECT UTC_TIMESTAMP() AS utc_now')->utc_now, 'UTC');
        $applicationNow = CarbonImmutable::instance(now('Asia/Kuala_Lumpur'))->utc();
        $confirmed = ($databaseNow->lessThan($applicationNow) ? $databaseNow : $applicationNow)
            ->subMinutes(30)->startOfMinute();
        $payload = ['employee' => $f['employee'], 'leave_type' => 'AL', 'start' => $date, 'end' => $date,
            'session' => 'AM', 'reason' => 'Synthetic leave', 'recording_reason' => 'Synthetic assistance',
            'consent_reference' => 'SYNTHETIC-CONSENT', 'confirmed_at' => $confirmed->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d\TH:i'),
            'consent_acknowledged' => true, 'key' => $this->key()];
        $this->actingAs($f['staff'])->post('/leave/annual/record', $payload)->assertForbidden();
        $this->actingAs($f['hr'])->from('/leave/annual/record')->post('/leave/annual/record', [...$payload, 'consent_acknowledged' => false])
            ->assertSessionHasErrors('consent_acknowledged');
        $this->post('/leave/annual/record', [...$payload, 'confirmed_at' => now('Asia/Kuala_Lumpur')->addMinute()->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors('start');
        $this->post('/leave/annual/record', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/leave/annual/record', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $id = (int) DB::table('leave_requests')->where('submission_key', $payload['key'])->value('id');
        $this->assertGreaterThan(0, $id);
        $this->assertDatabaseHas('leave_requests', ['id' => $id, 'employee_id' => $f['employee'], 'requester_user_id' => $f['staff']->id]);
        $this->assertDatabaseHas('leave_request_on_behalf_submissions', ['request_id' => $id, 'hr_actor_user_id' => $f['hr']->id,
            'applicant_user_id' => $f['staff']->id, 'consent_evidence_reference' => 'SYNTHETIC-CONSENT',
            'staff_confirmed_at' => $confirmed->utc()->format('Y-m-d H:i:s')]);
        $this->assertSame(1, DB::table('leave_request_on_behalf_submissions')->where('request_id', $id)->count());
        $this->assertSame(40, $this->annual($f['annual']));
        $this->assertSame(1, app(LedgerPostedTotals::class)->pendingAnnualUnits($f['annual']));
    }

    public function test_ct_051_assisted_future_and_cross_year_requests_use_actual_year_accounts(): void
    {
        $f = $this->fixture();
        $nextYear = $this->year + 1;
        $futureDate = $this->workingDate($nextYear.'-01-15', $nextYear.'-01-31');
        $dec = $this->workingDate($this->year.'-12-29', $this->year.'-12-31');
        $jan = $this->workingDate($nextYear.'-01-01', $nextYear.'-01-10');
        $databaseNow = CarbonImmutable::parse(DB::selectOne('SELECT UTC_TIMESTAMP() AS utc_now')->utc_now, 'UTC');
        $applicationNow = CarbonImmutable::instance(now('Asia/Kuala_Lumpur'))->utc();
        $confirmed = ($databaseNow->lessThan($applicationNow) ? $databaseNow : $applicationNow)->subMinutes(30)->startOfMinute();
        $payload = ['employee' => $f['employee'], 'leave_type' => 'AL', 'start' => $futureDate, 'end' => $futureDate,
            'session' => 'AM', 'reason' => 'Synthetic leave', 'recording_reason' => 'Synthetic assistance',
            'consent_reference' => 'SYNTHETIC-FUTURE-CONSENT', 'confirmed_at' => $confirmed->setTimezone('Asia/Kuala_Lumpur')->format('Y-m-d\TH:i'),
            'consent_acknowledged' => true, 'key' => $this->key()];
        $this->actingAs($f['hr'])->from('/leave/annual/record')->post('/leave/annual/record', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $id = (int) DB::table('leave_requests')->where('submission_key', $payload['key'])->value('id');
        $this->assertGreaterThan(0, $id);
        $this->assertDatabaseHas('leave_request_on_behalf_submissions', ['request_id' => $id, 'applicant_user_id' => $f['staff']->id, 'hr_actor_user_id' => $f['hr']->id]);
        $this->assertSame(1, app(LedgerPostedTotals::class)->pendingAnnualUnits($f['next']));
        $this->approve($f, $id);
        $this->assertSame(0, $this->annual($f['next']));
        $this->assertDatabaseHas('leave_annual_commitments', ['annual_account_id' => $f['next'], 'units' => 1, 'status' => 'COMMITTED']);

        $cross = [...$payload, 'start' => $dec, 'end' => $jan, 'session' => 'FULL', 'key' => $this->key()];
        $this->post('/leave/annual/record', $cross)->assertRedirect()->assertSessionHasNoErrors();
        $crossId = (int) DB::table('leave_requests')->where('submission_key', $cross['key'])->value('id');
        $this->assertGreaterThan(0, $crossId);
        $reservation = DB::table('leave_request_revisions')->where('request_id', $crossId)->value('reservation_id');
        $this->assertDatabaseHas('leave_reservation_slot_allocations', ['reservation_id' => $reservation, 'annual_account_id' => $f['annual'], 'leave_date' => $dec]);
        $this->assertDatabaseHas('leave_reservation_slot_allocations', ['reservation_id' => $reservation, 'annual_account_id' => $f['next'], 'leave_date' => $jan]);
        $this->assertGreaterThan(0, app(LedgerPostedTotals::class)->pendingAnnualUnits($f['annual']));
        $this->assertGreaterThan(0, app(LedgerPostedTotals::class)->pendingAnnualUnits($f['next']));
        $this->assertSame(40, $this->annual($f['annual']));
        $this->assertSame(0, $this->annual($f['next']));
        $this->post('/leave/annual/record', [...$payload, 'leave_type' => 'EL', 'key' => $this->key()])->assertSessionHasErrors('start');
    }

    public function test_ct_029_hr_review_records_finding_without_second_debit(): void
    {
        $f = $this->fixture();
        $date = $this->workingDate($this->year.'-12-01', $this->year.'-12-10');
        $workflow = app(LeaveRequestWorkflowService::class);
        $result = $workflow->submit($f['staff']->id, $f['annual'], $f['tabung'], $this->key(), 'EL',
            [['date' => $date, 'session' => 'PM']], 'Synthetic emergency');
        $workflow->decide($f['pu']->id, $result['request_id'], $this->key(), 'SUPPORT');
        $workflow->decide($f['pb']->id, $result['request_id'], $this->key(), 'REJECT', 'Synthetic rejection');
        $review = DB::table('leave_attendance_reviews as review')->join('leave_request_revisions as revision', 'revision.id', '=', 'review.revision_id')
            ->where('revision.request_id', $result['request_id'])->first(['review.id', 'review.review_code']);
        $this->assertNotNull($review);
        $this->assertSame('RETROSPECTIVE_EL_REJECTION', $review->review_code);
        $this->assertSame(39, $this->annual($f['annual']));
        $url = '/leave/annual/reports/reviews/'.$review->id.'/outcome';
        $payload = ['finding' => 'ABSENCE_CONFIRMED', 'note' => 'Synthetic verified absence', 'evidence_reference' => 'SYNTHETIC-ATTENDANCE', 'operation_key' => $this->key()];
        $this->actingAs($f['staff'])->post($url, $payload)->assertForbidden();
        $this->actingAs($f['hr'])->from('/leave/annual/reports')->post($url, $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url, $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('leave_attendance_review_outcomes')->where('review_id', $review->id)->count());
        $this->assertDatabaseHas('leave_attendance_review_outcomes', ['review_id' => $review->id, 'finding' => 'ABSENCE_CONFIRMED', 'reviewer_id' => $f['hr']->id]);
        $this->assertSame(39, $this->annual($f['annual']));
        $this->assertDatabaseHas('leave_requests', ['id' => $result['request_id'], 'status' => 'REJECTED']);
    }

    public function test_permission_driven_pages_do_not_expose_hr_data_to_staff(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['staff'])->get('/leave/annual/ledger?year='.$this->year)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('leave/Operations')->where('mode', 'ledger')
                ->where('balance.annual_half_day_units', 40)->has('entries.data', 1)->missing('accounts')->missing('reviews'));
        foreach (['reports', 'adjustments', 'year-end', 'record'] as $page) {
            $this->get('/leave/annual/'.$page)->assertForbidden();
        }
        $this->actingAs($f['hr'])->get('/leave/annual/reports?year='.$this->year)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('leave/Operations')->where('mode', 'reports')->has('accounts.data'));
        $this->get('/leave/annual/year-end')->assertOk();
        $this->get('/leave/annual/record')->assertOk();
        $this->get('/leave/annual/adjustments')->assertOk();
    }

    public function test_cr_q03_after_end_special_adjustment_requires_three_distinct_actors(): void
    {
        $f = $this->fixture();
        $maker = $this->actor('special-maker', ['leave.adjustment.prepare', 'leave.adjustment.decide', 'leave.partial-cancellation.review']);
        $reviewer = $this->actor('special-reviewer', ['leave.adjustment.decide', 'leave.partial-cancellation.review']);
        $date = $this->workingDate($this->year.'-12-21', $this->year.'-12-24');
        $workflow = app(LeaveRequestWorkflowService::class);
        $result = $workflow->submit($f['staff']->id, $f['annual'], $f['tabung'], $this->key(), 'AL',
            [['date' => $date, 'session' => 'AM'], ['date' => $date, 'session' => 'PM']]);
        $id = $result['request_id'];
        $this->approve($f, $id);
        $this->assertSame(38, $this->annual($f['annual']));
        $this->denied(fn () => $workflow->prepareAfterEndCancellation($maker->id, $id, $this->key(), 'Synthetic return', 'SYNTHETIC'));
        Carbon::setTestNow(Carbon::parse($date.' 18:00:00', 'Asia/Kuala_Lumpur'));
        $this->denied(fn () => $workflow->requestApprovedAnnualLeavePartialCancellation($f['staff']->id, $id, $this->key(), 'Late ordinary request'));
        $this->denied(fn () => $workflow->prepareAfterEndCancellation($f['staff']->id, $id, $this->key(), 'Synthetic return', 'SYNTHETIC'));
        $key = $this->key();
        $case = $workflow->prepareAfterEndCancellation($maker->id, $id, $key, 'Synthetic return', 'SYNTHETIC')['partial_cancellation_id'];
        $this->assertSame($case, $workflow->prepareAfterEndCancellation($maker->id, $id, $key, 'Synthetic return', 'SYNTHETIC')['partial_cancellation_id']);
        $this->denied(fn () => $workflow->verifyApprovedAnnualLeavePartialCancellation($maker->id, $id, $case, $this->key(), $date, 'Verified', 'PM'));
        $workflow->verifyApprovedAnnualLeavePartialCancellation($reviewer->id, $id, $case, $this->key(), $date, 'Verified', 'PM');
        $this->assertSame(38, $this->annual($f['annual']));
        $decision = $this->key();
        $workflow->decideApprovedAnnualLeavePartialCancellation($f['pb']->id, $id, $case, $decision, 'CONFIRM');
        $workflow->decideApprovedAnnualLeavePartialCancellation($f['pb']->id, $id, $case, $decision, 'CONFIRM');
        $this->assertSame(39, $this->annual($f['annual']));
        $this->assertDatabaseHas('leave_request_partial_cancellations', ['id' => $case, 'case_kind' => 'AFTER_END', 'status' => 'CONFIRMED', 'unused_half_day_units' => 1]);
        $this->assertDatabaseHas('leave_requests', ['id' => $id, 'status' => 'APPROVED']);
        $this->denied(fn () => $workflow->prepareAfterEndCancellation($maker->id, $id, $this->key(), 'Duplicate', 'SYNTHETIC'));
    }

    public function test_cr_q04_cross_year_partial_refund_cancels_unused_unposted_commitment(): void
    {
        $f = $this->fixture();
        $reviewer = $this->actor('cross-year-reviewer', ['leave.partial-cancellation.review']);
        $dec = $this->workingDate($this->year.'-12-23', $this->year.'-12-31');
        $jan = $this->workingDate(($this->year + 1).'-01-05', ($this->year + 1).'-01-20');
        $workflow = app(LeaveRequestWorkflowService::class);
        $result = $workflow->submit($f['staff']->id, $f['annual'], $f['tabung'], $this->key(), 'AL',
            [['date' => $dec, 'session' => 'AM'], ['date' => $dec, 'session' => 'PM'], ['date' => $jan, 'session' => 'AM']]);
        $id = $result['request_id'];
        $this->approve($f, $id);
        $this->assertSame(38, $this->annual($f['annual']));
        Carbon::setTestNow(Carbon::parse($dec.' 14:00:00', 'Asia/Kuala_Lumpur'));
        $case = $workflow->requestApprovedAnnualLeavePartialCancellation($f['staff']->id, $id, $this->key(), 'Synthetic early return')['partial_cancellation_id'];
        $workflow->verifyApprovedAnnualLeavePartialCancellation($reviewer->id, $id, $case, $this->key(), $dec, 'Synthetic verified', 'PM');
        $key = $this->key();
        $workflow->decideApprovedAnnualLeavePartialCancellation($f['pb']->id, $id, $case, $key, 'CONFIRM');
        $workflow->decideApprovedAnnualLeavePartialCancellation($f['pb']->id, $id, $case, $key, 'CONFIRM');
        $this->assertSame(39, $this->annual($f['annual']));
        $this->assertSame(0, $this->annual($f['next']));
        $this->assertDatabaseHas('leave_annual_commitments', ['reservation_id' => $result['reservation_id'], 'status' => 'CANCELLED']);
        $this->assertSame(2, DB::table('leave_active_slots')->where('reservation_id', $result['reservation_id'])->where('status', 'RELEASED')->count());
        $this->assertSame(1, DB::table('leave_active_slots')->where('reservation_id', $result['reservation_id'])->where('status', 'COMMITTED')->count());
    }

    public function test_tb_05_late_rejection_waits_for_explicit_non_use_then_reconciles_once(): void
    {
        $f = $this->fixture();
        $date = $this->workingDate($this->year.'-12-23', $this->year.'-12-31');
        $workflow = app(LeaveRequestWorkflowService::class);
        $result = $workflow->submit($f['staff']->id, $f['annual'], $f['tabung'], $this->key(), 'AL',
            [['date' => $date, 'session' => 'AM'], ['date' => $date, 'session' => 'PM']]);
        $workflow->decide($f['pu']->id, $result['request_id'], $this->key(), 'SUPPORT');
        Carbon::setTestNow(Carbon::parse(($this->year + 1).'-01-01 09:00:00', 'Asia/Kuala_Lumpur'));
        app(AnnualEntitlementCreditService::class)->creditConfirmedEntitlementOnJanuaryFirst($f['hr']->id, $f['next_confirmation']);
        $close = app(AnnualLeaveYearCloseService::class);
        $preview = $close->eligibility($f['employee'], $this->year, 40);
        $this->assertTrue($preview['can_post'], (string) $preview['reason']);
        $close->close($f['hr']->id, $f['employee'], $this->year, 40, $preview['snapshot'], 'Synthetic close');
        $workflow->decide($f['pb']->id, $result['request_id'], $this->key(), 'REJECT', 'Synthetic late rejection');
        $this->assertSame(2, $this->annual($f['annual']));
        $this->assertSame(38, $this->tabung($f['tabung']));
        $this->assertSame(2, app(LedgerPostedTotals::class)->pendingHistoricalReviewUnits($f['annual']));
        $review = DB::table('leave_attendance_reviews as review')->join('leave_request_revisions as revision', 'revision.id', '=', 'review.revision_id')
            ->where('revision.request_id', $result['request_id'])->value('review.id');
        $url = '/leave/annual/reports/reviews/'.$review.'/outcome';
        $payload = ['finding' => 'NO_ABSENCE_CONFIRMED', 'note' => 'Synthetic all sessions not used', 'evidence_reference' => 'SYNTHETIC-ATTENDANCE', 'operation_key' => $this->key()];
        $this->actingAs($f['hr'])->from('/leave/annual/reports')->post($url, $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url, $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0, $this->annual($f['annual']));
        $this->assertSame(40, $this->tabung($f['tabung']));
        $this->assertSame(0, app(LedgerPostedTotals::class)->pendingHistoricalReviewUnits($f['annual']));
        $this->assertDatabaseHas('leave_late_reconciliations', ['annual_account_id' => $f['annual'], 'eligible_units' => 2, 'transfer_units' => 2, 'burn_units' => 0]);
        $this->assertSame(1, DB::table('leave_late_reconciliations')->where('annual_account_id', $f['annual'])->count());
    }

    public function test_tb_05_partial_non_use_reconciles_only_selected_session(): void
    {
        $f = $this->fixture();
        $date = $this->workingDate($this->year.'-12-23', $this->year.'-12-31');
        $workflow = app(LeaveRequestWorkflowService::class);
        $result = $workflow->submit($f['staff']->id, $f['annual'], $f['tabung'], $this->key(), 'AL',
            [['date' => $date, 'session' => 'AM'], ['date' => $date, 'session' => 'PM']]);
        $workflow->decide($f['pu']->id, $result['request_id'], $this->key(), 'SUPPORT');
        Carbon::setTestNow(Carbon::parse(($this->year + 1).'-01-01 09:00:00', 'Asia/Kuala_Lumpur'));
        app(AnnualEntitlementCreditService::class)->creditConfirmedEntitlementOnJanuaryFirst($f['hr']->id, $f['next_confirmation']);
        $close = app(AnnualLeaveYearCloseService::class);
        $preview = $close->eligibility($f['employee'], $this->year, 40);
        $this->assertTrue($preview['can_post'], (string) $preview['reason']);
        $close->close($f['hr']->id, $f['employee'], $this->year, 40, $preview['snapshot'], 'Synthetic close');
        $workflow->decide($f['pb']->id, $result['request_id'], $this->key(), 'REJECT', 'Synthetic late rejection');
        $this->assertSame(2, $this->annual($f['annual']));
        $this->assertSame(38, $this->tabung($f['tabung']));
        $this->assertSame(2, app(LedgerPostedTotals::class)->pendingHistoricalReviewUnits($f['annual']));
        $review = DB::table('leave_attendance_reviews as review')->join('leave_request_revisions as revision', 'revision.id', '=', 'review.revision_id')
            ->where('revision.request_id', $result['request_id'])->value('review.id');
        $url = '/leave/annual/reports/reviews/'.$review.'/outcome';
        $slots = DB::table('leave_reservation_slot_allocations')->where('reservation_id', $result['reservation_id'])->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $payload = ['finding' => 'PARTIAL_NON_USE_CONFIRMED', 'note' => 'Synthetic one session not used', 'evidence_reference' => 'SYNTHETIC-PARTIAL', 'operation_key' => $this->key(), 'unused_slots' => [$slots[0]]];
        $this->actingAs($f['hr'])->from('/leave/annual/reports')->post($url, [...$payload, 'unused_slots' => $slots])->assertRedirect()->assertSessionHasErrors('finding');
        $this->assertSame(2, $this->annual($f['annual']));
        $this->post($url, $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url, $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->post($url, [...$payload, 'unused_slots' => [$slots[1]]])->assertRedirect()->assertSessionHasErrors('finding');
        $this->assertSame(1, $this->annual($f['annual']));
        $this->assertSame(39, $this->tabung($f['tabung']));
        $this->assertSame(1, app(LedgerPostedTotals::class)->pendingHistoricalReviewUnits($f['annual']));
        $this->assertDatabaseHas('leave_attendance_non_use_slots', ['allocation_id' => $slots[0]]);
        $this->assertDatabaseHas('leave_late_reconciliations', ['annual_account_id' => $f['annual'], 'eligible_units' => 1, 'transfer_units' => 1, 'burn_units' => 0]);
        $this->assertSame(1, DB::table('leave_late_reconciliations')->where('annual_account_id', $f['annual'])->count());
    }

    public function test_historical_deficit_report_does_not_offset_new_year_or_allow_negative_adjustment(): void
    {
        $f = $this->fixture();
        $maker = $this->actor('deficit-report-maker', ['leave.adjustment.prepare']);
        $adjustments = app(LeaveAdjustmentService::class);
        $id = $adjustments->prepare($maker->id, ['employee' => $f['employee'], 'bucket' => 'AL',
            'units' => -40, 'reason' => 'Synthetic current-year correction', 'evidence_reference' => 'SYNTHETIC-ZERO',
            'key' => $this->key()]);
        $adjustments->decide($f['hr']->id, $id, ['decision' => 'APPROVED', 'reason' => 'Verified', 'key' => $this->key()]);
        $date = $this->workingDate($this->year.'-12-21', $this->year.'-12-24');
        $workflow = app(LeaveRequestWorkflowService::class);
        $leave = $workflow->submit($f['staff']->id, $f['annual'], $f['tabung'], $this->key(), 'AL',
            [['date' => $date, 'session' => 'AM']]);
        $workflow->decide($f['pu']->id, $leave['request_id'], $this->key(), 'SUPPORT', 'Synthetic support');
        $workflow->decide($f['pb']->id, $leave['request_id'], $this->key(), 'APPROVE', 'Synthetic deficit approved');
        $this->assertSame(-1, $this->annual($f['annual']));
        Carbon::setTestNow(Carbon::parse(($this->year + 1).'-01-01 09:00:00', 'Asia/Kuala_Lumpur'));
        $url = '/leave/annual/reports?year='.$this->year.'&deficits_only=1';
        $this->actingAs($f['staff'])->get($url)->assertForbidden();
        $this->actingAs($f['hr'])->get($url)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('leave/Operations')->where('deficitsOnly', true)
                ->has('accounts.data')
                ->where('accounts.data', fn ($rows) => collect($rows)->every(fn ($row) => (int) data_get($row, 'historical_deficit_units') > 0)));
        $this->denied(fn () => $adjustments->prepare($maker->id, ['employee' => $f['employee'], 'bucket' => 'AL',
            'leave_year' => $this->year, 'historical_eligible' => true, 'units' => -1, 'reason' => 'Synthetic historical correction',
            'evidence_reference' => 'SYNTHETIC-HISTORIC-NEGATIVE', 'key' => $this->key()]));
        $this->assertSame(-1, $this->annual($f['annual']));
        $this->assertSame(0, $this->annual($f['next']));
        $this->assertSame(0, $this->tabung($f['tabung']));
    }

    public function test_tb_05_historical_special_adjustment_reconciles_only_original_unused_session(): void
    {
        $f = $this->fixture();
        $maker = $this->actor('historic-special-maker', ['leave.adjustment.prepare']);
        $reviewer = $this->actor('historic-special-reviewer', ['leave.adjustment.decide', 'leave.partial-cancellation.review']);
        $date = $this->workingDate($this->year.'-12-23', $this->year.'-12-31');
        $workflow = app(LeaveRequestWorkflowService::class);
        $result = $workflow->submit($f['staff']->id, $f['annual'], $f['tabung'], $this->key(), 'AL',
            [['date' => $date, 'session' => 'AM'], ['date' => $date, 'session' => 'PM']]);
        $id = $result['request_id'];
        $this->approve($f, $id);
        Carbon::setTestNow(Carbon::parse(($this->year + 1).'-01-01 09:00:00', 'Asia/Kuala_Lumpur'));
        $this->denied(fn () => $workflow->prepareAfterEndCancellation($maker->id, $id, $this->key(), 'Synthetic return', 'SYNTHETIC-HISTORIC'));
        app(AnnualEntitlementCreditService::class)->creditConfirmedEntitlementOnJanuaryFirst($f['hr']->id, $f['next_confirmation']);
        $close = app(AnnualLeaveYearCloseService::class);
        $preview = $close->eligibility($f['employee'], $this->year, 40);
        $this->assertTrue($preview['can_post'], (string) $preview['reason']);
        $close->close($f['hr']->id, $f['employee'], $this->year, 40, $preview['snapshot'], 'Synthetic close');
        $case = $workflow->prepareAfterEndCancellation($maker->id, $id, $this->key(), 'Synthetic return', 'SYNTHETIC-HISTORIC')['partial_cancellation_id'];
        $workflow->verifyApprovedAnnualLeavePartialCancellation($reviewer->id, $id, $case, $this->key(), $date, 'Verified', 'PM');
        $this->assertSame(0, $this->annual($f['annual']));
        $this->assertSame(38, $this->tabung($f['tabung']));
        $key = $this->key();
        $workflow->decideApprovedAnnualLeavePartialCancellation($f['pb']->id, $id, $case, $key, 'CONFIRM');
        $workflow->decideApprovedAnnualLeavePartialCancellation($f['pb']->id, $id, $case, $key, 'CONFIRM');
        $this->assertSame(0, $this->annual($f['annual']));
        $this->assertSame(39, $this->tabung($f['tabung']));
        $this->assertSame(40, $this->annual($f['next']));
        $this->assertDatabaseHas('leave_partial_refund_reconciliations', ['partial_cancellation_id' => $case,
            'annual_account_id' => $f['annual'], 'eligible_units' => 1, 'transfer_units' => 1, 'burn_units' => 0]);
        $this->assertSame(1, DB::table('leave_partial_refund_reconciliations')->where('partial_cancellation_id', $case)->count());
        $this->denied(fn () => $workflow->prepareAfterEndCancellation($maker->id, $id, $this->key(), 'Duplicate', 'SYNTHETIC-HISTORIC'));
    }

    public function test_tb_05_historical_positive_adjustment_requires_attestation_and_burns_excess_once(): void
    {
        $f = $this->fixture();
        $maker = $this->actor('historical-maker', ['leave.adjustment.prepare']);
        $checker = $this->actor('historical-checker', ['leave.adjustment.decide']);
        Carbon::setTestNow(Carbon::parse(($this->year + 1).'-01-01 09:00:00', 'Asia/Kuala_Lumpur'));
        app(AnnualEntitlementCreditService::class)->creditConfirmedEntitlementOnJanuaryFirst($f['hr']->id, $f['next_confirmation']);
        $close = app(AnnualLeaveYearCloseService::class);
        $preview = $close->eligibility($f['employee'], $this->year, 40);
        $this->assertTrue($preview['can_post'], (string) $preview['reason']);
        $close->close($f['hr']->id, $f['employee'], $this->year, 40, $preview['snapshot'], 'Synthetic close');
        $service = app(LeaveAdjustmentService::class);
        $payload = ['employee' => $f['employee'], 'leave_year' => $this->year, 'bucket' => 'AL', 'units' => 2,
            'reason' => 'Synthetic newly eligible correction', 'evidence_reference' => 'SYNTHETIC-HISTORICAL', 'key' => $this->key()];
        $this->denied(fn () => $service->prepare($maker->id, $payload));
        $id = $service->prepare($maker->id, [...$payload, 'historical_eligible' => true]);
        $decision = ['decision' => 'APPROVED', 'reason' => 'Synthetic independent verification', 'key' => $this->key()];
        $this->denied(fn () => $service->decide($checker->id, $id, $decision));
        $service->decide($checker->id, $id, [...$decision, 'historical_verified' => true]);
        $service->decide($checker->id, $id, [...$decision, 'historical_verified' => true]);
        $this->assertSame(0, $this->annual($f['annual']));
        $this->assertSame(40, $this->tabung($f['tabung']));
        $this->assertDatabaseHas('leave_late_reconciliations', ['source_kind' => 'HR_ADJUSTMENT', 'source_id' => $id,
            'eligible_units' => 2, 'transfer_units' => 0, 'burn_units' => 2]);
        $this->denied(fn () => $service->prepare($maker->id, [...$payload, 'historical_eligible' => true, 'key' => $this->key()]));
    }

    private function fixture(): array
    {
        $suffix = Str::lower(Str::random(10));
        $staff = $this->actor('staff', ['leave.request.self']);
        $hr = $this->actor('hr', ['leave.entitlement.confirm', 'leave.entitlement.credit', 'leave.entitlement.view',
            'leave.reports.view', 'leave.attendance.review', 'leave.request.record-on-behalf', 'leave.year-end.preview',
            'leave.year-end.post', 'leave.adjustment.decide']);
        $pu = $this->demoActor('unit-manager', 'leave.request.decide');
        $pb = $this->demoActor('general-manager', 'leave.request.decide');
        $unit = DB::table('organization_units')->where('code', 'DEV-OPERASI')->where('active', true)->value('id');
        $category = DB::table('employment_categories')->where('code', 'DEV-PEKERJA')->where('active', true)->value('id');
        $this->assertNotNull($unit, 'Run DevelopmentEmployeeOrganizationSeeder first.');
        $this->assertNotNull($category);
        $employee = DB::table('employees')->insertGetId(['employee_no' => 'QA-'.$suffix, 'display_name' => 'Synthetic QA '.$suffix,
            'user_id' => $staff->id, 'service_start' => ($this->year - 3).'-01-01']);
        DB::table('employee_assignments')->insert(['employee_id' => $employee, 'unit_id' => $unit, 'category_id' => $category,
            'effective_from' => ($this->year - 3).'-01-01']);
        $annual = DB::table('leave_annual_accounts')->insertGetId(['employee_id' => $employee, 'leave_year' => $this->year]);
        $next = DB::table('leave_annual_accounts')->insertGetId(['employee_id' => $employee, 'leave_year' => $this->year + 1]);
        $tabung = DB::table('leave_tabung_accounts')->insertGetId(['employee_id' => $employee]);
        $service = app(AnnualEntitlementCreditService::class);
        Carbon::setTestNow(Carbon::parse($this->year.'-01-01 09:00:00', 'Asia/Kuala_Lumpur'));
        $confirmation = $service->confirmAnnualEntitlement($hr->id, $annual, 40, 'Synthetic QA current', $this->key());
        $service->creditConfirmedEntitlementOnJanuaryFirst($hr->id, $confirmation['confirmation_id']);
        Carbon::setTestNow(Carbon::parse($this->year.'-12-15 09:00:00', 'Asia/Kuala_Lumpur'));
        $future = $service->confirmAnnualEntitlement($hr->id, $next, 40, 'Synthetic QA future', $this->key());
        $capacity = app(\App\Services\Leave\TabungCapacity::class);
        $capacity->confirmRate($hr->id, $annual, $this->year.'-01-01', 40, 'Synthetic QA current full rate', $this->key());
        $capacity->confirmRate($hr->id, $next, ($this->year + 1).'-01-01', 40, 'Synthetic QA future full rate', $this->key());

        return ['employee' => $employee, 'staff' => $staff, 'hr' => $hr, 'pu' => $pu, 'pb' => $pb,
            'annual' => $annual, 'next' => $next, 'tabung' => $tabung, 'next_confirmation' => $future['confirmation_id']];
    }

    private function actor(string $label, array $permissions): User
    {
        $this->assertGreaterThan(0, DB::transactionLevel());
        $user = User::factory()->create(['username' => 'qa-'.$label.'-'.Str::lower(Str::random(10)), 'email' => null, 'status' => 'ACTIVE']);
        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }
        return $user;
    }

    private function demoActor(string $role, string $permission): User
    {
        $user = User::query()->where('username_canonical', 'dev-'.$role)->where('status', 'ACTIVE')->first();
        $this->assertNotNull($user, 'Missing synthetic development account: '.$role);
        $this->assertTrue(DB::table('audit_events')->where('action_code', 'iam.development_user.seeded')->where('target_ref', 'user:'.$user->id)->exists());
        $this->assertTrue($user->can($permission), 'Run DevelopmentLeaveAccessSeeder first: '.$permission);
        return $user;
    }

    private function approve(array $fixture, int $id): void
    {
        $workflow = app(LeaveRequestWorkflowService::class);
        $workflow->decide($fixture['pu']->id, $id, $this->key(), 'SUPPORT');
        $workflow->decide($fixture['pb']->id, $id, $this->key(), 'APPROVE');
    }

    private function workingDate(string $from, string $to): string
    {
        $lookup = app(ConfirmedVersionLookup::class);
        for ($date = CarbonImmutable::parse($from, 'Asia/Kuala_Lumpur'); $date->toDateString() <= $to; $date = $date->addDay()) {
            $day = $date->toDateString();
            $schedule = $lookup->workingScheduleFor($day);
            $calendar = $lookup->calendarFor($day);
            if (in_array((int) $date->format('N'), $schedule->working_weekdays, true) && ! in_array($day, $calendar['holidays'], true)) {
                return $day;
            }
        }
        throw new RuntimeException('No configured working day in the synthetic review range.');
    }

    private function annual(int $id): int
    {
        return (int) DB::table('leave_ledger_entries')->where('annual_account_id', $id)->sum('signed_half_day_units');
    }

    private function tabung(int $id): int
    {
        return (int) DB::table('leave_ledger_entries')->where('tabung_account_id', $id)->sum('signed_half_day_units');
    }

    private function key(): string
    {
        return (string) Str::uuid();
    }

    private function denied(callable $operation): void
    {
        try {
            $operation();
        } catch (DomainException) {
            $this->addToAssertionCount(1);
            return;
        }
        $this->fail('Expected operation to fail closed.');
    }
}
