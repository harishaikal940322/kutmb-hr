<?php

namespace Tests\Feature\Leave;

use App\Models\User;
use App\Services\Leave\LeaveBalanceTransactionService;
use App\Services\Leave\LedgerPostedTotals;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeaveBalanceTransactionServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_reservations_allocate_annual_before_tabung_count_pending_once_and_replay_idempotently(): void
    {
        $fixture = $this->fixture('reserve', 4, [2097 => 4]);
        $service = app(LeaveBalanceTransactionService::class);
        $requestKey = 'request-'.$fixture['suffix'];
        $operationKey = 'reserve-'.$fixture['suffix'];
        $slots = $this->workingSlots(8, $fixture['leave_year']);

        $first = $service->reserve($fixture['employee_user']->id, $requestKey, 1, $fixture['annual_id'], $fixture['tabung_id'], $slots, $operationKey);
        $replay = $service->reserve($fixture['employee_user']->id, $requestKey, 1, $fixture['annual_id'], $fixture['tabung_id'], $slots, $operationKey);

        $this->assertSame($first, $replay);
        $this->assertSame(4, $first['annual_units']);
        $this->assertSame(4, $first['tabung_units']);
        $this->assertSame(1, DB::table('leave_reservations')->where('request_key', $requestKey)->count());
        $this->assertSame(1, DB::table('leave_reservation_events')->where('event_code', 'RESERVED')->count());
        $this->assertSame(1, DB::table('leave_employee_history_markers')->where('request_key', $requestKey)->count());
        $this->assertSame(4, DB::table('leave_calendar_usage_snapshots')->where('history_marker_id', DB::table('leave_reservations')->where('id', $first['reservation_id'])->value('history_marker_id'))->count());
        $this->assertSame(8, (int) DB::table('leave_calendar_usage_snapshots')->where('history_marker_id', DB::table('leave_reservations')->where('id', $first['reservation_id'])->value('history_marker_id'))->sum('leave_units'));

        // HR's recorded answer permits a negative AL projection without a configured cap.
        $secondDate = new \DateTimeImmutable($slots[count($slots) - 1]['date']);
        do {
            $secondDate = $secondDate->modify('+1 day');
        } while ((int) $secondDate->format('N') > 5);
        $second = $service->reserve($fixture['employee_user']->id, 'request-second-'.$fixture['suffix'], 1, $fixture['annual_id'], $fixture['tabung_id'], [['date' => $secondDate->format('Y-m-d'), 'session' => 'AM']], 'reserve-second-'.$fixture['suffix']);
        $this->assertSame(1, $second['annual_units']);
        $this->assertSame(0, $second['tabung_units']);
        $this->assertSame(5, (int) DB::table('leave_reservations')->where('annual_account_id', $fixture['annual_id'])->where('status', 'ACTIVE')->sum('annual_units'));
        $this->assertSame(4, (int) DB::table('leave_reservations')->where('tabung_account_id', $fixture['tabung_id'])->where('status', 'ACTIVE')->sum('tabung_units'));
    }

    public function test_final_commit_posts_reserved_sources_once_and_replays_without_duplicate_entries(): void
    {
        $fixture = $this->fixture('commit', 2, [2097 => 2]);
        $approver = $this->approver('approver-'.$fixture['suffix']);
        $assignmentId = DB::table('approval_assignments')->insertGetId([
            'user_id' => $approver->id,
            'office_code' => 'PB',
            'effective_from' => '2020-01-01',
        ]);
        $service = app(LeaveBalanceTransactionService::class);
        $reservation = $service->reserve($fixture['employee_user']->id, 'request-'.$fixture['suffix'], 1, $fixture['annual_id'], $fixture['tabung_id'], $this->workingSlots(4, $fixture['leave_year']), 'reserve-'.$fixture['suffix']);
        $operationKey = 'commit-'.$fixture['suffix'];

        $committed = $service->commitUsage($approver->id, $reservation['reservation_id'], $assignmentId, $operationKey);
        $replay = $service->commitUsage($approver->id, $reservation['reservation_id'], $assignmentId, $operationKey);

        $this->assertSame('COMMITTED', $committed['status']);
        $this->assertSame($committed, $replay);
        $entries = DB::table('leave_ledger_entries')->where('kind', 'approved_leave_use')->get();
        $this->assertCount(2, $entries);
        $this->assertSame([-2, -2], $entries->pluck('signed_half_day_units')->sort()->values()->all());
        $this->assertSame(2, DB::table('leave_reservation_events')->where('reservation_id', $reservation['reservation_id'])->count());
        $this->assertSame(4, DB::table('leave_active_slots')->where('reservation_id', $reservation['reservation_id'])->where('status', 'COMMITTED')->where('active_slot_key', 1)->count());
        $this->assertSame(1, DB::table('audit_events')->where('action_code', 'hr.leave.balance_committed')->where('target_ref', 'leave_reservation:'.$reservation['reservation_id'])->count());
        $this->assertSame(0, app(LedgerPostedTotals::class)->forAccounts($fixture['annual_id'], $fixture['tabung_id'])['annual_half_day_units']);
        $this->assertSame(0, app(LedgerPostedTotals::class)->forAccounts($fixture['annual_id'], $fixture['tabung_id'])['tabung_half_day_units']);
    }

    public function test_only_final_rejection_or_employee_withdrawal_releases_and_each_release_is_idempotent(): void
    {
        $fixture = $this->fixture('release', 2, []);
        $approver = $this->approver('approver-'.$fixture['suffix']);
        $assignmentId = DB::table('approval_assignments')->insertGetId([
            'user_id' => $approver->id,
            'office_code' => 'SECRETARY_ALK',
            'effective_from' => '2020-01-01',
        ]);
        $service = app(LeaveBalanceTransactionService::class);

        $withdrawn = $service->reserve($fixture['employee_user']->id, 'withdraw-'.$fixture['suffix'], 1, $fixture['annual_id'], $fixture['tabung_id'], $this->workingSlots(1, $fixture['leave_year']), 'reserve-withdraw-'.$fixture['suffix']);
        $service->release($fixture['employee_user']->id, $withdrawn['reservation_id'], 'WITHDRAWAL', 'withdraw-op-'.$fixture['suffix']);
        $withdrawalReplay = $service->release($fixture['employee_user']->id, $withdrawn['reservation_id'], 'WITHDRAWAL', 'withdraw-op-'.$fixture['suffix']);
        $this->assertSame('RELEASED', $withdrawalReplay['status']);

        $rejected = $service->reserve($fixture['employee_user']->id, 'reject-'.$fixture['suffix'], 1, $fixture['annual_id'], $fixture['tabung_id'], $this->workingSlots(1, $fixture['leave_year']), 'reserve-reject-'.$fixture['suffix']);
        try {
            $service->release($approver->id, $rejected['reservation_id'], 'UNIT_MANAGER_NOT_SUPPORTED', 'unsupported-'.$fixture['suffix'], $assignmentId);
            $this->fail('A not-supported result must not be treated as a final rejection.');
        } catch (DomainException) {
            $this->assertSame('ACTIVE', DB::table('leave_reservations')->where('id', $rejected['reservation_id'])->value('status'));
        }
        $service->release($approver->id, $rejected['reservation_id'], 'FINAL_REJECTION', 'reject-op-'.$fixture['suffix'], $assignmentId);
        $rejectionReplay = $service->release($approver->id, $rejected['reservation_id'], 'FINAL_REJECTION', 'reject-op-'.$fixture['suffix'], $assignmentId);
        $this->assertSame('RELEASED', $rejectionReplay['status']);
        $this->assertSame(0, DB::table('leave_reservations')->where('status', 'ACTIVE')->count());
        $this->assertSame(0, DB::table('leave_ledger_entries')->where('kind', 'approved_leave_use')->count());
    }

    public function test_same_session_overlap_is_blocked_distinct_sessions_are_allowed_and_withdrawal_frees_the_slot(): void
    {
        $fixture = $this->fixture('slots', 4, []);
        $service = app(LeaveBalanceTransactionService::class);
        $date = $this->workingSlots(1, $fixture['leave_year'])[0]['date'];
        $am = $service->reserve($fixture['employee_user']->id, 'slot-am-'.$fixture['suffix'], 1, $fixture['annual_id'], $fixture['tabung_id'], [['date' => $date, 'session' => 'AM']], 'slot-am-op-'.$fixture['suffix']);
        $pm = $service->reserve($fixture['employee_user']->id, 'slot-pm-'.$fixture['suffix'], 1, $fixture['annual_id'], $fixture['tabung_id'], [['date' => $date, 'session' => 'PM']], 'slot-pm-op-'.$fixture['suffix']);

        $this->assertSame('ACTIVE', $am['status']);
        $this->assertSame('ACTIVE', $pm['status']);
        $this->assertSame(2, DB::table('leave_active_slots')->where('employee_id', $fixture['employee_id'])->where('active_slot_key', 1)->count());
        try {
            $service->reserve($fixture['employee_user']->id, 'slot-am-again-'.$fixture['suffix'], 1, $fixture['annual_id'], $fixture['tabung_id'], [['date' => $date, 'session' => 'AM']], 'slot-am-again-op-'.$fixture['suffix']);
            $this->fail('An active AM slot cannot be reserved a second time.');
        } catch (DomainException) {
            $this->assertSame(2, DB::table('leave_reservations')->where('employee_id', $fixture['employee_id'])->count());
        }

        $service->release($fixture['employee_user']->id, $am['reservation_id'], 'WITHDRAWAL', 'slot-withdraw-'.$fixture['suffix']);
        $released = DB::table('leave_active_slots')->where('reservation_id', $am['reservation_id'])->sole();
        $this->assertSame('RELEASED', $released->status);
        $this->assertNull($released->active_slot_key);
        $replacement = $service->reserve($fixture['employee_user']->id, 'slot-am-retry-'.$fixture['suffix'], 1, $fixture['annual_id'], $fixture['tabung_id'], [['date' => $date, 'session' => 'AM']], 'slot-am-retry-op-'.$fixture['suffix']);
        $this->assertSame('ACTIVE', $replacement['status']);
        $this->assertSame(2, DB::table('leave_active_slots')->where('employee_id', $fixture['employee_id'])->where('active_slot_key', 1)->count());
    }

    public function test_cross_year_holiday_ambiguous_sources_and_reused_operation_payload_fail_closed(): void
    {
        $fixture = $this->fixture('failclosed', 0, [2096 => 2, 2097 => 2]);
        $service = app(LeaveBalanceTransactionService::class);
        $weekday = $this->workingSlots(1, $fixture['leave_year'])[0];

        try {
            $service->reserve($fixture['employee_user']->id, 'cross-year-'.$fixture['suffix'], 1, $fixture['annual_id'], $fixture['tabung_id'], [['date' => '2099-03-02', 'session' => 'AM']], 'cross-year-op-'.$fixture['suffix']);
            $this->fail('A same-year transaction cannot span into another calendar year.');
        } catch (DomainException) {
            $this->assertSame(0, DB::table('leave_reservations')->count());
        }

        try {
            $service->reserve($fixture['employee_user']->id, 'ambiguous-'.$fixture['suffix'], 1, $fixture['annual_id'], $fixture['tabung_id'], [$weekday], 'ambiguous-op-'.$fixture['suffix']);
            $this->fail('Multiple available Tabung source years must not be selected implicitly.');
        } catch (DomainException) {
            $this->assertSame(0, DB::table('leave_reservations')->where('employee_id', $fixture['employee_id'])->count());
            $this->assertSame(0, DB::table('leave_employee_history_markers')->where('employee_id', $fixture['employee_id'])->count());
        }

        $nullFixture = $this->fixture('nullsource', 0, ['null' => 2]);
        try {
            $service->reserve($nullFixture['employee_user']->id, 'null-source-'.$nullFixture['suffix'], 1, $nullFixture['annual_id'], $nullFixture['tabung_id'], $this->workingSlots(1, $nullFixture['leave_year']), 'null-source-op-'.$nullFixture['suffix']);
            $this->fail('A Tabung allocation without source-year provenance must fail closed.');
        } catch (DomainException) {
            $this->assertSame(0, DB::table('leave_reservations')->where('employee_id', $nullFixture['employee_id'])->count());
        }

        $holidayFixture = $this->fixture('holiday', 1, []);
        $holidaySlot = $this->workingSlots(1, $holidayFixture['leave_year'])[0];
        DB::table('leave_calendar_days')->insert([
            'calendar_version_id' => $holidayFixture['calendar_id'],
            'date' => $holidaySlot['date'],
            'name' => 'Synthetic holiday',
            'kind' => 'holiday',
        ]);
        try {
            $service->reserve($holidayFixture['employee_user']->id, 'holiday-'.$holidayFixture['suffix'], 1, $holidayFixture['annual_id'], $holidayFixture['tabung_id'], [$holidaySlot], 'holiday-op-'.$holidayFixture['suffix']);
            $this->fail('A confirmed holiday must block a reservation snapshot.');
        } catch (DomainException) {
            $this->assertSame(0, DB::table('leave_reservations')->where('employee_id', $holidayFixture['employee_id'])->count());
            $this->assertSame(0, DB::table('leave_employee_history_markers')->where('employee_id', $holidayFixture['employee_id'])->count());
        }

        $okFixture = $this->fixture('payload', 1, []);
        $payloadKey = 'payload-op-'.$okFixture['suffix'];
        $requestKey = 'payload-'.$okFixture['suffix'];
        $first = $service->reserve($okFixture['employee_user']->id, $requestKey, 1, $okFixture['annual_id'], $okFixture['tabung_id'], $this->workingSlots(1, $okFixture['leave_year']), $payloadKey);
        try {
            $service->reserve($okFixture['employee_user']->id, $requestKey, 1, $okFixture['annual_id'], $okFixture['tabung_id'], $this->workingSlots(2, $okFixture['leave_year']), $payloadKey);
            $this->fail('An idempotency key cannot be reused with a changed payload.');
        } catch (DomainException) {
            $this->assertSame(1, DB::table('leave_reservations')->where('id', $first['reservation_id'])->count());
            $this->assertSame(1, DB::table('leave_employee_history_markers')->where('request_key', $requestKey)->count());
        }
    }

    /**
     * @param  array<int|string, int>  $tabungUnitsBySourceYear
     * @return array{suffix:string,leave_year:int,employee_id:int,employee_user:User,annual_id:int,tabung_id:int,calendar_id:int}
     */
    private function fixture(string $label, int $annualUnits, array $tabungUnitsBySourceYear): array
    {
        $suffix = Str::lower(Str::random(10));
        $leaveYear = match ($label) {
            'nullsource' => 2099,
            'holiday' => 2100,
            'payload' => 2101,
            default => 2098,
        };
        $employeeUser = User::factory()->create(['username' => $label.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $employeeId = DB::table('employees')->insertGetId([
            'employee_no' => $label.$suffix,
            'display_name' => 'Synthetic '.$label,
            'user_id' => $employeeUser->id,
            'service_start' => '2097-01-01',
        ]);
        $annualId = DB::table('leave_annual_accounts')->insertGetId(['employee_id' => $employeeId, 'leave_year' => $leaveYear]);
        $tabungId = DB::table('leave_tabung_accounts')->insertGetId(['employee_id' => $employeeId]);
        $calendarId = DB::table('leave_calendar_versions')->insertGetId([
            'label' => 'Synthetic calendar '.$suffix,
            'effective_from' => $leaveYear.'-01-01',
            'effective_to' => $leaveYear.'-12-31',
            'confirmed_by' => $employeeUser->id,
            'confirmed_at' => now(),
        ]);
        DB::table('leave_policy_versions')->insert([
            'label' => 'Synthetic schedule '.$suffix,
            'effective_from' => $leaveYear.'-01-01',
            'effective_to' => $leaveYear.'-12-31',
            'schedule_simulated_by' => $employeeUser->id,
            'schedule_simulated_at' => now(),
            'working_weekdays' => '[1,2,3,4,5]',
            'work_start' => '08:00',
            'session_boundary' => '13:00',
            'work_end' => '17:00',
        ]);
        if ($annualUnits !== 0) {
            $operationId = $this->operation($employeeId, $employeeUser->id, 'opening-a-'.$suffix);
            DB::table('leave_ledger_entries')->insert([
                'annual_account_id' => $annualId,
                'balance_operation_id' => $operationId,
                'signed_half_day_units' => $annualUnits,
                'kind' => 'synthetic_opening',
                'operation_key' => 'opening-a-'.$suffix,
                'actor_id' => $employeeUser->id,
            ]);
        }
        foreach ($tabungUnitsBySourceYear as $sourceYear => $units) {
            $storedSourceYear = $sourceYear === 'null' ? null : (int) $sourceYear;
            $keySuffix = $storedSourceYear === null ? 'null' : (string) $storedSourceYear;
            $operationId = $this->operation($employeeId, $employeeUser->id, 'opening-t-'.$keySuffix.'-'.$suffix);
            DB::table('leave_ledger_entries')->insert([
                'tabung_account_id' => $tabungId,
                'balance_operation_id' => $operationId,
                'signed_half_day_units' => $units,
                'kind' => 'synthetic_opening',
                'source_year' => $storedSourceYear,
                'operation_key' => 'opening-t-'.$keySuffix.'-'.$suffix,
                'actor_id' => $employeeUser->id,
            ]);
        }

        return ['suffix' => $suffix, 'leave_year' => $leaveYear, 'employee_id' => $employeeId, 'employee_user' => $employeeUser, 'annual_id' => $annualId, 'tabung_id' => $tabungId, 'calendar_id' => $calendarId];
    }

    /** @return list<array{date:string,session:string}> */
    private function workingSlots(int $units, int $year = 2098): array
    {
        $slots = [];
        $date = new \DateTimeImmutable($year.'-03-01');
        while (count($slots) < $units) {
            if ((int) $date->format('N') <= 5) {
                foreach (['AM', 'PM'] as $session) {
                    if (count($slots) < $units) {
                        $slots[] = ['date' => $date->format('Y-m-d'), 'session' => $session];
                    }
                }
            }
            $date = $date->modify('+1 day');
        }

        return $slots;
    }

    private function approver(string $username): User
    {
        return User::factory()->create(['username' => $username, 'email' => null, 'status' => 'ACTIVE']);
    }

    private function operation(int $employeeId, int $actorId, string $key): int
    {
        return DB::table('leave_balance_operations')->insertGetId([
            'employee_id' => $employeeId,
            'actor_id' => $actorId,
            'operation_key' => $key,
            'operation_type' => 'SYNTHETIC_TEST',
            'payload_hash' => hash('sha256', $key),
            'created_at' => now(),
        ]);
    }
}
