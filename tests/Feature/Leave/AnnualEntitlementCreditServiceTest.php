<?php

namespace Tests\Feature\Leave;

use App\Models\User;
use App\Services\Leave\AnnualEntitlementCreditService;
use App\Services\Leave\LeaveBalanceTransactionService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AnnualEntitlementCreditServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_explicit_synthetic_confirmation_is_immutable_and_january_credit_is_once_only(): void
    {
        Carbon::setTestNow('2025-12-15T09:00:00+08:00');
        $fixture = $this->fixture('credit');
        $actor = $this->user('confirmer');
        $actor->givePermissionTo('leave.entitlement.confirm');
        $actor->givePermissionTo('leave.entitlement.credit');
        $service = app(AnnualEntitlementCreditService::class);

        $confirmation = $service->confirmAnnualEntitlement(
            $actor->id,
            $fixture['annual_id'],
            12,
            'SYNTHETIC-HR-CONFIRMATION-'.$fixture['suffix'],
            'confirm-'.$fixture['suffix'],
        );
        $replay = $service->confirmAnnualEntitlement(
            $actor->id,
            $fixture['annual_id'],
            12,
            'SYNTHETIC-HR-CONFIRMATION-'.$fixture['suffix'],
            'confirm-'.$fixture['suffix'],
        );

        $this->assertSame($confirmation['confirmation_id'], $replay['confirmation_id']);
        $this->assertTrue($replay['replayed']);
        $this->assertSame(12, $confirmation['confirmed_half_day_units']);
        $this->assertSame(0, DB::table('leave_ledger_entries')->where('annual_account_id', $fixture['annual_id'])->count());
        $this->assertDomainException(fn () => $service->confirmAnnualEntitlement(
            $actor->id,
            $fixture['annual_id'],
            14,
            'SYNTHETIC-HR-CONFIRMATION-'.$fixture['suffix'],
            'confirm-'.$fixture['suffix'],
        ));
        $this->assertDomainException(fn () => $service->confirmAnnualEntitlement(
            $actor->id,
            $fixture['annual_id'],
            12,
            'SYNTHETIC-HR-CONFIRMATION-'.$fixture['suffix'],
            'confirm-again-'.$fixture['suffix'],
        ));

        Carbon::setTestNow('2025-12-31T23:59:59+08:00');
        $this->assertDomainException(fn () => $service->creditConfirmedEntitlementOnJanuaryFirst($actor->id, $confirmation['confirmation_id']));
        $this->assertSame(0, DB::table('leave_balance_operations')->where('operation_key', 'annual-credit:'.$fixture['annual_id'].':2026')->count());

        Carbon::setTestNow('2026-01-01T00:00:00+08:00');
        $credited = $service->creditConfirmedEntitlementOnJanuaryFirst($actor->id, $confirmation['confirmation_id']);
        $creditReplay = $service->creditConfirmedEntitlementOnJanuaryFirst($actor->id, $confirmation['confirmation_id']);
        $entry = DB::table('leave_ledger_entries')->where('id', $credited['ledger_entry_id'])->sole();

        $this->assertFalse($credited['replayed']);
        $this->assertTrue($creditReplay['replayed']);
        $this->assertSame($credited['ledger_entry_id'], $creditReplay['ledger_entry_id']);
        $this->assertSame(12, (int) $entry->signed_half_day_units);
        $this->assertSame('annual_entitlement_credit', $entry->kind);
        $this->assertSame('annual-credit:'.$fixture['annual_id'].':2026', $entry->operation_key);
        $this->assertNull($entry->source_year);
        $this->assertSame(1, DB::table('leave_ledger_entries')->where('annual_account_id', $fixture['annual_id'])->count());
        $this->assertSame(1, DB::table('leave_balance_operations')->where('operation_key', $entry->operation_key)->count());
        $this->assertSame(1, DB::table('audit_events')->where('action_code', 'hr.leave.annual_entitlement_confirmed')->count());
        $this->assertSame(1, DB::table('audit_events')->where('action_code', 'hr.leave.annual_entitlement_credited')->count());
    }

    public function test_credit_fails_closed_without_authority_and_after_the_january_first_window(): void
    {
        Carbon::setTestNow('2026-01-02T09:00:00+08:00');
        $fixture = $this->fixture('late');
        $actor = $this->user('unprivileged');
        $service = app(AnnualEntitlementCreditService::class);

        $this->assertDomainException(fn () => $service->confirmAnnualEntitlement(
            $actor->id,
            $fixture['annual_id'],
            10,
            'SYNTHETIC-LATE-CONFIRMATION-'.$fixture['suffix'],
            'late-confirm-'.$fixture['suffix'],
        ));
        $actor->givePermissionTo('leave.entitlement.confirm');
        $confirmation = $service->confirmAnnualEntitlement(
            $actor->id,
            $fixture['annual_id'],
            10,
            'SYNTHETIC-LATE-CONFIRMATION-'.$fixture['suffix'],
            'late-confirm-'.$fixture['suffix'],
        );
        $actor->givePermissionTo('leave.entitlement.credit');

        $this->assertDomainException(fn () => $service->creditConfirmedEntitlementOnJanuaryFirst($actor->id, $confirmation['confirmation_id']));
        $this->assertSame(0, DB::table('leave_balance_operations')->where('employee_id', $fixture['employee_id'])->count());
        $this->assertSame(0, DB::table('leave_ledger_entries')->where('annual_account_id', $fixture['annual_id'])->count());
    }

    public function test_future_year_reservation_waits_for_the_actual_january_credit(): void
    {
        Carbon::setTestNow('2026-12-15T09:00:00+08:00');
        $fixture = $this->fixture('future', 2027);
        DB::table('leave_calendar_versions')->insert([
            'label' => 'Synthetic confirmed future calendar '.$fixture['suffix'],
            'effective_from' => '2027-01-01',
            'effective_to' => '2027-12-31',
            'confirmed_by' => $fixture['employee_user']->id,
            'confirmed_at' => now(),
        ]);
        DB::table('leave_policy_versions')->insert([
            'label' => 'Synthetic confirmed future schedule '.$fixture['suffix'],
            'effective_from' => '2027-01-01',
            'effective_to' => '2027-12-31',
            'working_weekdays' => '[1,2,3,4,5]',
            'work_start' => '08:00',
            'session_boundary' => '13:00',
            'work_end' => '17:00',
            'schedule_simulated_by' => $fixture['employee_user']->id,
            'schedule_simulated_at' => now(),
        ]);
        $hrActor = $this->user('future-confirmer');
        $hrActor->givePermissionTo('leave.entitlement.confirm');
        $hrActor->givePermissionTo('leave.entitlement.credit');
        $entitlements = app(AnnualEntitlementCreditService::class);
        $balances = app(LeaveBalanceTransactionService::class);
        $slots = [['date' => '2027-01-04', 'session' => 'AM'], ['date' => '2027-01-04', 'session' => 'PM']];

        $this->assertDomainException(fn () => $balances->reserve(
            $fixture['employee_user']->id,
            'future-request-before-confirm-'.$fixture['suffix'],
            1,
            $fixture['annual_id'],
            $fixture['tabung_id'],
            $slots,
            'future-reserve-before-confirm-'.$fixture['suffix'],
        ));
        $this->assertSame(0, DB::table('leave_balance_operations')->count());

        $tabungSeedKey = 'synthetic-future-tabung-'.$fixture['suffix'];
        $tabungSeedOperationId = DB::table('leave_balance_operations')->insertGetId([
            'employee_id' => $fixture['employee_id'],
            'actor_id' => $fixture['employee_user']->id,
            'operation_key' => $tabungSeedKey,
            'operation_type' => 'SYNTHETIC',
            'payload_hash' => hash('sha256', $tabungSeedKey),
            'created_at' => now(),
        ]);
        DB::table('leave_ledger_entries')->insert([
            'tabung_account_id' => $fixture['tabung_id'],
            'balance_operation_id' => $tabungSeedOperationId,
            'signed_half_day_units' => 2,
            'kind' => 'synthetic_future_tabung',
            'source_year' => 2025,
            'operation_key' => $tabungSeedKey,
            'actor_id' => $fixture['employee_user']->id,
        ]);

        $confirmation = $entitlements->confirmAnnualEntitlement(
            $hrActor->id,
            $fixture['annual_id'],
            10,
            'SYNTHETIC-FUTURE-ENTITLEMENT-'.$fixture['suffix'],
            'future-confirm-'.$fixture['suffix'],
        );
        $this->assertSame(10, $confirmation['confirmed_half_day_units']);
        $this->assertDomainException(fn () => $balances->reserve(
            $fixture['employee_user']->id,
            'future-request-before-credit-'.$fixture['suffix'],
            1,
            $fixture['annual_id'],
            $fixture['tabung_id'],
            $slots,
            'future-reserve-before-credit-'.$fixture['suffix'],
        ));
        $this->assertSame(0, DB::table('leave_reservations')->count());
        $this->assertSame(0, DB::table('leave_balance_operations')->where('operation_type', 'RESERVE')->count());

        Carbon::setTestNow('2027-01-01T00:00:00+08:00');
        $entitlements->creditConfirmedEntitlementOnJanuaryFirst($hrActor->id, $confirmation['confirmation_id']);
        $reservation = $balances->reserve(
            $fixture['employee_user']->id,
            'future-request-after-credit-'.$fixture['suffix'],
            1,
            $fixture['annual_id'],
            $fixture['tabung_id'],
            $slots,
            'future-reserve-after-credit-'.$fixture['suffix'],
        );
        $replay = $balances->reserve(
            $fixture['employee_user']->id,
            'future-request-after-credit-'.$fixture['suffix'],
            1,
            $fixture['annual_id'],
            $fixture['tabung_id'],
            $slots,
            'future-reserve-after-credit-'.$fixture['suffix'],
        );

        $this->assertSame(2, $reservation['annual_units']);
        $this->assertSame(0, $reservation['tabung_units']);
        $this->assertSame($reservation, $replay);
        $this->assertSame(1, DB::table('leave_reservations')->where('id', $reservation['reservation_id'])->count());
        $this->assertSame(1, DB::table('leave_ledger_entries')->where('annual_account_id', $fixture['annual_id'])->where('kind', 'annual_entitlement_credit')->count());
        $afterCreditSlots = [];
        foreach (['2027-01-05', '2027-01-06', '2027-01-07', '2027-01-08'] as $date) {
            $afterCreditSlots[] = ['date' => $date, 'session' => 'AM'];
            $afterCreditSlots[] = ['date' => $date, 'session' => 'PM'];
        }
        $afterCreditSlots[] = ['date' => '2027-01-11', 'session' => 'AM'];
        $afterCreditReservation = $balances->reserve(
            $fixture['employee_user']->id,
            'future-request-later-after-credit-'.$fixture['suffix'],
            1,
            $fixture['annual_id'],
            $fixture['tabung_id'],
            $afterCreditSlots,
            'future-reserve-later-after-credit-'.$fixture['suffix'],
        );

        $this->assertSame(8, $afterCreditReservation['annual_units']);
        $this->assertSame(1, $afterCreditReservation['tabung_units']);
        $this->assertSame(1, DB::table('leave_ledger_entries')->where('annual_account_id', $fixture['annual_id'])->where('kind', 'annual_entitlement_credit')->count());
    }

    public function test_annual_entitlement_credit_is_unavailable_outside_local_and_testing(): void
    {
        $this->app['env'] = 'production';

        try {
            app(AnnualEntitlementCreditService::class)->confirmAnnualEntitlement(1, 1, 10, 'SYNTHETIC-PROD-DENIAL', 'prod-denial');
            $this->fail('Annual entitlement input must remain unavailable outside local development and testing.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    /** @return array{suffix:string,employee_id:int,employee_user:User,annual_id:int,tabung_id:int} */
    private function fixture(string $label, int $leaveYear = 2026): array
    {
        $suffix = Str::lower(Str::random(8));
        $user = $this->user($label);
        $employeeId = DB::table('employees')->insertGetId([
            'employee_no' => $label.'-'.$suffix,
            'display_name' => 'Synthetic employee '.$label,
            'user_id' => $user->id,
            'service_start' => '2020-01-01',
        ]);
        $annualId = DB::table('leave_annual_accounts')->insertGetId(['employee_id' => $employeeId, 'leave_year' => $leaveYear]);
        $tabungId = DB::table('leave_tabung_accounts')->insertGetId(['employee_id' => $employeeId]);

        return ['suffix' => $suffix, 'employee_id' => $employeeId, 'employee_user' => $user, 'annual_id' => $annualId, 'tabung_id' => $tabungId];
    }

    private function user(string $label): User
    {
        return User::factory()->create([
            'username' => $label.'-'.Str::lower(Str::random(8)),
            'email' => null,
            'status' => 'ACTIVE',
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

        $this->fail('An unauthorized or out-of-window annual entitlement operation must fail closed.');
    }
}
