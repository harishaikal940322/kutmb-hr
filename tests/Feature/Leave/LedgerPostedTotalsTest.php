<?php

namespace Tests\Feature\Leave;

use App\Models\User;
use App\Services\Leave\LedgerPostedTotals;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use OverflowException;
use Tests\TestCase;

class LedgerPostedTotalsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_returns_integer_totals_from_explicit_employee_accounts(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $suffix = Str::lower(Str::random(16));
        $actor = User::factory()->create(['username' => 'ledgerread'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $employee = DB::table('employees')->insertGetId(['employee_no' => 'read'.$suffix, 'display_name' => 'Synthetic', 'service_start' => '2026-01-01']);
        $annual = DB::table('leave_annual_accounts')->insertGetId(['employee_id' => $employee, 'leave_year' => 2026]);
        $tabung = DB::table('leave_tabung_accounts')->insertGetId(['employee_id' => $employee]);
        DB::table('leave_ledger_entries')->insert(['annual_account_id' => $annual, 'balance_operation_id' => $this->operation($employee, $actor->id, 'annual-credit-op-'.$suffix), 'signed_half_day_units' => 5, 'kind' => 'synthetic', 'operation_key' => 'annual-credit-'.$suffix, 'actor_id' => $actor->id]);
        DB::table('leave_ledger_entries')->insert(['annual_account_id' => $annual, 'balance_operation_id' => $this->operation($employee, $actor->id, 'annual-debit-op-'.$suffix), 'signed_half_day_units' => -1, 'kind' => 'synthetic', 'operation_key' => 'annual-debit-'.$suffix, 'actor_id' => $actor->id]);
        DB::table('leave_ledger_entries')->insert(['tabung_account_id' => $tabung, 'balance_operation_id' => $this->operation($employee, $actor->id, 'tabung-credit-op-'.$suffix), 'signed_half_day_units' => 6, 'kind' => 'synthetic', 'source_year' => 2025, 'operation_key' => 'tabung-credit-'.$suffix, 'actor_id' => $actor->id]);

        $this->assertSame([
            'annual_half_day_units' => 4,
            'tabung_half_day_units' => 6,
        ], app(LedgerPostedTotals::class)->forAccounts($annual, $tabung));
    }

    public function test_rejects_accounts_for_different_employees(): void
    {
        $suffix = Str::lower(Str::random(16));
        $employeeOne = DB::table('employees')->insertGetId(['employee_no' => 'one'.$suffix, 'display_name' => 'Synthetic One', 'service_start' => '2026-01-01']);
        $employeeTwo = DB::table('employees')->insertGetId(['employee_no' => 'two'.$suffix, 'display_name' => 'Synthetic Two', 'service_start' => '2026-01-01']);
        $annual = DB::table('leave_annual_accounts')->insertGetId(['employee_id' => $employeeOne, 'leave_year' => 2026]);
        $tabung = DB::table('leave_tabung_accounts')->insertGetId(['employee_id' => $employeeTwo]);

        $this->expectException(InvalidArgumentException::class);
        app(LedgerPostedTotals::class)->forAccounts($annual, $tabung);
    }

    public function test_rejects_total_outside_the_supported_integer_range(): void
    {
        $suffix = Str::lower(Str::random(16));
        $actor = User::factory()->create(['username' => 'ledgeroverflow'.$suffix, 'email' => null, 'status' => 'ACTIVE']);
        $employee = DB::table('employees')->insertGetId(['employee_no' => 'overflow'.$suffix, 'display_name' => 'Synthetic', 'service_start' => '2026-01-01']);
        $annual = DB::table('leave_annual_accounts')->insertGetId(['employee_id' => $employee, 'leave_year' => 2026]);
        $tabung = DB::table('leave_tabung_accounts')->insertGetId(['employee_id' => $employee]);
        DB::table('leave_ledger_entries')->insert(['annual_account_id' => $annual, 'balance_operation_id' => $this->operation($employee, $actor->id, 'overflow-a-op-'.$suffix), 'signed_half_day_units' => PHP_INT_MAX, 'kind' => 'synthetic', 'operation_key' => 'overflow-a-'.$suffix, 'actor_id' => $actor->id]);
        DB::table('leave_ledger_entries')->insert(['annual_account_id' => $annual, 'balance_operation_id' => $this->operation($employee, $actor->id, 'overflow-b-op-'.$suffix), 'signed_half_day_units' => 1, 'kind' => 'synthetic', 'operation_key' => 'overflow-b-'.$suffix, 'actor_id' => $actor->id]);

        $this->expectException(OverflowException::class);
        app(LedgerPostedTotals::class)->forAccounts($annual, $tabung);
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
